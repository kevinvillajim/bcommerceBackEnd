<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConfigurationService;
use App\Services\EmailVerificationService;
use App\Services\MailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisteredUserController extends Controller
{
    protected $jwtService;

    protected $configService;

    protected $emailVerificationService;

    protected $mailService;

    /**
     * Create a new use case instance.
     *
     * @return void
     */
    public function __construct(
        JwtServiceInterface $jwtService,
        ConfigurationService $configService,
        EmailVerificationService $emailVerificationService,
        MailService $mailService
    ) {
        $this->jwtService = $jwtService;
        $this->configService = $configService;
        $this->emailVerificationService = $emailVerificationService;
        $this->mailService = $mailService;
    }

    /**
     * Build dynamic password validation rules based on security configuration
     *
     * @return string
     */
    private function buildPasswordValidationRules()
    {
        // Get security configurations
        $minLength = $this->configService->getConfig('security.passwordMinLength', 8);
        $requireSpecial = $this->configService->getConfig('security.passwordRequireSpecial', true);
        $requireUppercase = $this->configService->getConfig('security.passwordRequireUppercase', true);
        $requireNumbers = $this->configService->getConfig('security.passwordRequireNumbers', true);

        $rules = ['required', 'string', "min:$minLength", 'confirmed'];

        // Add regex validation for character requirements
        $regexParts = [];

        if ($requireUppercase === true) {
            $regexParts[] = '(?=.*[A-Z])'; // At least one uppercase letter
        }

        if ($requireNumbers === true) {
            $regexParts[] = '(?=.*[0-9])'; // At least one number
        }

        if ($requireSpecial === true) {
            $regexParts[] = '(?=.*[!@#$%^&*])'; // At least one special character
        }

        if (! empty($regexParts)) {
            $regex = 'regex:/^'.implode('', $regexParts).'.*$/';
            $rules[] = $regex;
        }

        return implode('|', $rules);
    }

    /**
     * Build custom validation messages with dynamic values
     */
    private function buildPasswordValidationMessages()
    {
        $minLength = $this->configService->getConfig('security.passwordMinLength', 8);
        $requireSpecial = $this->configService->getConfig('security.passwordRequireSpecial', true);
        $requireUppercase = $this->configService->getConfig('security.passwordRequireUppercase', true);
        $requireNumbers = $this->configService->getConfig('security.passwordRequireNumbers', true);

        $requirements = [];
        if ($requireUppercase) {
            $requirements[] = 'al menos una letra mayúscula';
        }
        if ($requireNumbers) {
            $requirements[] = 'al menos un número';
        }
        if ($requireSpecial) {
            $requirements[] = 'al menos un carácter especial (!@#$%^&*)';
        }

        $requirementsText = empty($requirements) ? '' : ' y debe incluir '.implode(', ', $requirements);

        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe ser una dirección válida.',
            'email.unique' => 'Este email ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => "La contraseña debe tener al menos $minLength caracteres$requirementsText.",
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.regex' => "La contraseña debe tener al menos $minLength caracteres$requirementsText.",
        ];
    }

    /**
     * Handle an incoming registration request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        Log::info('Registration request received', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => $this->buildPasswordValidationRules(),
            ], $this->buildPasswordValidationMessages());

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Log::info('Validation passed');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Log::info('User created with ID: '.$user->id);

            // NOTE: Removed event(new Registered($user)) to avoid conflicts with our MailManager
            // Laravel's Registered event triggers automatic email verification using MailChannel
            // which conflicts with our custom MailManager system

            // Handle email verification - ALWAYS REQUIRE VERIFICATION
            $requiresVerification = true;
            $emailVerificationResult = null;

            Log::info('Email verification is always required for new registrations');
            $emailVerificationResult = $this->emailVerificationService->sendVerificationEmail($user);

            if ($emailVerificationResult['status'] === 'error') {
                Log::warning('Failed to send verification email', $emailVerificationResult);
            }

            // NOTE: User email is NOT marked as verified here - they must click the verification link

            // Get JWT token
            $token = JWTAuth::fromUser($user);
            Log::info('JWT token generated');

            // Prepare response data
            $responseData = [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $user,
                'email_verification' => [
                    'required' => $requiresVerification,
                    'sent' => $emailVerificationResult ? $emailVerificationResult['status'] === 'success' : false,
                    'message' => $emailVerificationResult['message'] ?? 'Email de verificación requerido',
                ],
            ];

            // If verification failed but is required, include warning
            if ($requiresVerification && $emailVerificationResult && $emailVerificationResult['status'] === 'error') {
                $responseData['email_verification']['warning'] = 'Registro exitoso pero hubo un problema enviando el email de verificación. Puedes solicitar uno nuevo.';
            }

            $response = response()->json($responseData, 201);

            Log::info('Registration completed', [
                'user_id' => $user->id,
                'verification_required' => $requiresVerification,
                'verification_sent' => $emailVerificationResult ? $emailVerificationResult['status'] === 'success' : false,
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Error in registration: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
