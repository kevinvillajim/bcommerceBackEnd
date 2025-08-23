<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConfigurationController extends Controller
{
    protected ConfigurationService $configService;

    /**
     * Constructor
     */
    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener todas las configuraciones
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Obtener configuraciones desde la base de datos
            $query = DB::table('configurations');

            // Filtrar por grupo si se especifica
            if ($request->has('group')) {
                $query->where('group', $request->input('group'));
            }

            $configurations = $query->get();

            // Formatear valores según su tipo
            $configurations = $configurations->map(function ($config) {
                $value = $config->value;

                // Convertir según el tipo
                switch ($config->type) {
                    case 'boolean':
                        $value = $value === 'true';
                        break;
                    case 'number':
                        $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }

                $config->value = $value;

                return $config;
            });

            return response()->json([
                'status' => 'success',
                'data' => $configurations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una configuración específica
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $key)
    {
        try {
            $config = DB::table('configurations')->where('key', $key)->first();

            if (! $config) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuración no encontrada',
                ], 404);
            }

            // Formatear valor según tipo
            $value = $config->value;
            switch ($config->type) {
                case 'boolean':
                    $value = $value === 'true';
                    break;
                case 'number':
                    $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }

            $config->value = $value;

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la configuración: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar configuraciones
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'configs' => 'required|array',
                'configs.*.key' => 'required|string',
                'configs.*.value' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $results = [];

            foreach ($request->input('configs') as $config) {
                $key = $config['key'];
                $value = $config['value'];

                $success = $this->configService->setConfig($key, $value);

                $results[$key] = $success ? 'Actualizado correctamente' : 'Error al actualizar';
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones actualizadas',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener configuraciones específicas para ratings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRatingConfigs()
    {
        try {
            $configs = DB::table('configurations')
                ->where('group', 'ratings')
                ->get();

            // Formatear valores
            $formattedConfigs = [];
            foreach ($configs as $config) {
                $value = $config->value;

                // Convertir según el tipo
                switch ($config->type) {
                    case 'boolean':
                        $value = $value === 'true';
                        break;
                    case 'number':
                        $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value;
                        break;
                }

                $formattedConfigs[$config->key] = [
                    'value' => $value,
                    'description' => $config->description,
                    'type' => $config->type,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $formattedConfigs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones de valoraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar configuraciones de ratings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRatingConfigs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'auto_approve_all' => 'required|boolean',
                'auto_approve_threshold' => 'required|numeric|min:1|max:5',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Actualizar configuraciones
            $this->configService->setConfig('ratings.auto_approve_all', $request->input('auto_approve_all'));
            $this->configService->setConfig('ratings.auto_approve_threshold', $request->input('auto_approve_threshold'));

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones de valoraciones actualizadas correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones de valoraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener configuraciones por categoría
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCategory(Request $request)
    {
        try {
            $category = $request->input('category');

            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoría requerida',
                ], 400);
            }

            $configs = DB::table('configurations')
                ->where('group', $category)
                ->get();

            // Formatear valores
            $formattedConfigs = [];
            foreach ($configs as $config) {
                $value = $config->value;

                // Convertir según el tipo
                switch ($config->type) {
                    case 'boolean':
                        $value = $value === 'true';
                        break;
                    case 'number':
                        $value = is_numeric($value) ? (strpos($value, '.') !== false ? (float) $value : (int) $value) : $value;
                        break;
                }

                // Usar solo la parte después del punto para la clave
                $shortKey = substr($config->key, strpos($config->key, '.') + 1);
                $formattedConfigs[$shortKey] = $value;
            }

            return response()->json([
                'status' => 'success',
                'data' => $formattedConfigs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar configuraciones por categoría
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateByCategory(Request $request)
    {
        try {
            $category = $request->input('category');
            $configurations = $request->input('configurations', []);

            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Categoría requerida',
                ], 400);
            }

            // Mapeo de claves snake_case a camelCase para compatibilidad
            $keyMapping = [
                'password_min_length' => 'passwordMinLength',
                'password_require_special' => 'passwordRequireSpecial',
                'password_require_uppercase' => 'passwordRequireUppercase',
                'password_require_numbers' => 'passwordRequireNumbers',
                'account_lock_attempts' => 'accountLockAttempts',
                'session_timeout' => 'sessionTimeout',
                'enable_two_factor' => 'enableTwoFactor',
                'require_email_verification' => 'requireEmailVerification',
                'admin_ip_restriction' => 'adminIpRestriction',
                'enable_captcha' => 'enableCaptcha',
            ];

            $results = [];
            foreach ($configurations as $key => $value) {
                // Usar mapeo si existe, sino usar la clave tal como viene
                $mappedKey = $keyMapping[$key] ?? $key;
                $fullKey = $category.'.'.$mappedKey;

                // Convertir valor a string para almacenamiento
                $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

                $success = $this->configService->setConfig($fullKey, $stringValue);
                $results[$key] = $success ? 'Actualizado correctamente' : 'Error al actualizar';
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones actualizadas',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener reglas de validación de contraseñas dinámicas
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPasswordValidationRules()
    {
        try {
            $minLength = $this->configService->getConfig('security.passwordMinLength', 8);
            $requireSpecial = $this->configService->getConfig('security.passwordRequireSpecial', true);
            $requireUppercase = $this->configService->getConfig('security.passwordRequireUppercase', true);
            $requireNumbers = $this->configService->getConfig('security.passwordRequireNumbers', true);

            // Construir mensaje dinámico
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
            $message = "La contraseña debe tener al menos $minLength caracteres$requirementsText.";

            return response()->json([
                'status' => 'success',
                'data' => [
                    'minLength' => $minLength,
                    'requireSpecial' => $requireSpecial,
                    'requireUppercase' => $requireUppercase,
                    'requireNumbers' => $requireNumbers,
                    'validationMessage' => $message,
                    'requirements' => $requirements,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener reglas de validación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get public volume discount configuration
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVolumeDiscountConfigs()
    {
        try {
            // Obtener configuraciones de descuentos por volumen
            $enabled = $this->configService->getConfig('volume_discounts.enabled', true);
            $stackable = $this->configService->getConfig('volume_discounts.stackable', true);
            $defaultTiers = $this->configService->getConfig('volume_discounts.default_tiers', 
                '[{"quantity":5,"discount":5,"label":"Descuento 5+"},{"quantity":6,"discount":10,"label":"Descuento 10+"},{"quantity":19,"discount":15,"label":"Descuento 15+"}]'
            );
            $showSavingsMessage = $this->configService->getConfig('volume_discounts.show_savings_message', true);

            // Parsear el JSON de los tiers
            $tiersArray = [];
            if (is_string($defaultTiers)) {
                $tiersArray = json_decode($defaultTiers, true) ?? [];
            } elseif (is_array($defaultTiers)) {
                $tiersArray = $defaultTiers;
            }

            // Ordenar tiers por cantidad ascendente
            usort($tiersArray, function($a, $b) {
                return ($a['quantity'] ?? 0) - ($b['quantity'] ?? 0);
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'enabled' => filter_var($enabled, FILTER_VALIDATE_BOOLEAN),
                    'stackable' => filter_var($stackable, FILTER_VALIDATE_BOOLEAN),
                    'default_tiers' => $tiersArray,
                    'show_savings_message' => filter_var($showSavingsMessage, FILTER_VALIDATE_BOOLEAN),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener configuración de descuentos por volumen: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración de descuentos por volumen',
            ], 500);
        }
    }

    /**
     * Get moderation configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModerationConfigs()
    {
        try {
            $moderationConfig = [
                'userStrikesThreshold' => $this->configService->getConfig('moderation.userStrikesThreshold', 3),
                'contactScorePenalty' => $this->configService->getConfig('moderation.contactScorePenalty', 3),
                'businessScoreBonus' => $this->configService->getConfig('moderation.businessScoreBonus', 15),
                'contactPenaltyHeavy' => $this->configService->getConfig('moderation.contactPenaltyHeavy', 20),
                'minimumContactScore' => $this->configService->getConfig('moderation.minimumContactScore', 8),
                'scoreDifferenceThreshold' => $this->configService->getConfig('moderation.scoreDifferenceThreshold', 5),
                'consecutiveNumbersLimit' => $this->configService->getConfig('moderation.consecutiveNumbersLimit', 7),
                'numbersWithContextLimit' => $this->configService->getConfig('moderation.numbersWithContextLimit', 3),
                'lowStockThreshold' => $this->configService->getConfig('moderation.lowStockThreshold', 10),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $moderationConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones de moderación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update moderation configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateModerationConfigs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userStrikesThreshold' => 'required|integer|min:1|max:10',
                'contactScorePenalty' => 'required|integer|min:1|max:20',
                'businessScoreBonus' => 'required|integer|min:5|max:50',
                'contactPenaltyHeavy' => 'required|integer|min:10|max:50',
                'minimumContactScore' => 'required|integer|min:5|max:20',
                'scoreDifferenceThreshold' => 'required|integer|min:3|max:15',
                'consecutiveNumbersLimit' => 'required|integer|min:5|max:15',
                'numbersWithContextLimit' => 'required|integer|min:2|max:8',
                'lowStockThreshold' => 'required|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $configs = $request->all();
            $configMapping = [
                'userStrikesThreshold' => 'moderation.userStrikesThreshold',
                'contactScorePenalty' => 'moderation.contactScorePenalty',
                'businessScoreBonus' => 'moderation.businessScoreBonus',
                'contactPenaltyHeavy' => 'moderation.contactPenaltyHeavy',
                'minimumContactScore' => 'moderation.minimumContactScore',
                'scoreDifferenceThreshold' => 'moderation.scoreDifferenceThreshold',
                'consecutiveNumbersLimit' => 'moderation.consecutiveNumbersLimit',
                'numbersWithContextLimit' => 'moderation.numbersWithContextLimit',
                'lowStockThreshold' => 'moderation.lowStockThreshold',
            ];

            // Update each configuration
            foreach ($configMapping as $requestKey => $dbKey) {
                if (isset($configs[$requestKey])) {
                    $this->configService->setConfig($dbKey, $configs[$requestKey]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones de moderación actualizadas correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones de moderación: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get shipping configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getShippingConfigs()
    {
        try {
            $shippingConfig = [
                'enabled' => $this->configService->getConfig('shipping.enabled', true),
                'freeThreshold' => $this->configService->getConfig('shipping.free_threshold', 50.00),
                'defaultCost' => $this->configService->getConfig('shipping.default_cost', 5.00),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $shippingConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones de envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update shipping configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShippingConfigs(Request $request)
    {
        try {
            \Log::info('🔍 updateShippingConfigs - Datos recibidos:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'freeThreshold' => 'nullable|numeric|min:0',
                'defaultCost' => 'nullable|numeric|min:0',
                'free_threshold' => 'nullable|numeric|min:0',
                'default_cost' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                \Log::error('❌ Validación falló:', $validator->errors()->toArray());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $configs = $request->all();
            \Log::info('✅ Datos validados:', $configs);
            
            // Normalizar los datos - manejar tanto camelCase como snake_case
            $normalizedConfigs = [
                'enabled' => $configs['enabled'],
                'freeThreshold' => $configs['freeThreshold'] ?? $configs['free_threshold'] ?? 0,
                'defaultCost' => $configs['defaultCost'] ?? $configs['default_cost'] ?? 0,
            ];
            
            \Log::info('🔄 Datos normalizados:', $normalizedConfigs);
            
            $configMapping = [
                'enabled' => 'shipping.enabled',
                'freeThreshold' => 'shipping.free_threshold',
                'defaultCost' => 'shipping.default_cost',
            ];

            // Update each configuration
            foreach ($configMapping as $requestKey => $dbKey) {
                if (isset($normalizedConfigs[$requestKey])) {
                    $value = $normalizedConfigs[$requestKey];
                    \Log::info("💾 Guardando: {$dbKey} = " . json_encode($value) . " (tipo: " . gettype($value) . ")");
                    
                    $result = $this->configService->setConfig($dbKey, $value);
                    \Log::info("📊 Resultado setConfig para {$dbKey}: " . ($result ? 'SUCCESS' : 'FAILED'));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones de envío actualizadas correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones de envío: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get development configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDevelopmentConfigs()
    {
        try {
            $developmentConfig = [
                'mode' => $this->configService->getConfig('development.mode', false),
                'allowAdminOnlyAccess' => $this->configService->getConfig('development.allowAdminOnlyAccess', false),
                'bypassEmailVerification' => $this->configService->getConfig('email.bypassVerification', true),
                'requireEmailVerification' => $this->configService->getConfig('email.requireVerification', false),
                'emailVerificationTimeout' => $this->configService->getConfig('email.verificationTimeout', 24),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $developmentConfig,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones de desarrollo: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update development configurations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDevelopmentConfigs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mode' => 'required|boolean',
                'allowAdminOnlyAccess' => 'required|boolean',
                'bypassEmailVerification' => 'required|boolean',
                'requireEmailVerification' => 'required|boolean',
                'emailVerificationTimeout' => 'required|integer|min:1|max:168', // Max 1 week
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $configs = $request->all();
            $configMapping = [
                'mode' => 'development.mode',
                'allowAdminOnlyAccess' => 'development.allowAdminOnlyAccess',
                'bypassEmailVerification' => 'email.bypassVerification',
                'requireEmailVerification' => 'email.requireVerification',
                'emailVerificationTimeout' => 'email.verificationTimeout',
            ];

            // Update each configuration
            foreach ($configMapping as $requestKey => $dbKey) {
                if (isset($configs[$requestKey])) {
                    $this->configService->setConfig($dbKey, $configs[$requestKey]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones de desarrollo actualizadas correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones de desarrollo: '.$e->getMessage(),
            ], 500);
        }
    }
}
