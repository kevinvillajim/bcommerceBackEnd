<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\JwtService;
use App\Models\User; // Cambiado para usar la implementación directamente
use Illuminate\Console\Command;

class JwtDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:debug {email? : User email for token generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug JWT configuration and generate a test token';

    /**
     * @var JwtService
     */
    protected $jwtService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(JwtService $jwtService)
    {
        parent::__construct();
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== JWT Configuration Debug ===');

        // Check JWT Secret
        $secret = config('jwt.secret');
        if (empty($secret)) {
            $this->error('⨯ JWT Secret is not set. Run: php artisan jwt:secret');

            return 1;
        } else {
            $this->info('✓ JWT Secret is set');
        }

        // TTL Configuration
        $ttl = config('jwt.ttl');
        $this->info("✓ JWT TTL: {$ttl} minutes");

        // Check Auth Guard configuration
        $guards = config('auth.guards');
        if (isset($guards['api']) && $guards['api']['driver'] === 'jwt') {
            $this->info('✓ JWT auth guard configured correctly');
        } else {
            $this->warn('⚠ JWT auth guard may not be configured correctly in config/auth.php');
            $this->line('  Guard config: '.json_encode($guards['api'] ?? 'Not defined'));
        }

        // Check User Model implementing JWT
        if (in_array('Tymon\JWTAuth\Contracts\JWTSubject', class_implements(User::class))) {
            $this->info('✓ User model implements JWTSubject');
        } else {
            $this->error('⨯ User model does not implement JWTSubject');
        }

        // Generate a test token
        $email = $this->argument('email') ?? 'test@example.com';
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->warn("⚠ No user found with email: {$email}");

            return 1;
        }

        try {
            $token = $this->jwtService->generateToken($user);
            $this->info('✓ Successfully generated test token');
            $this->line("Token: {$token}");

            // Validate the token
            $isValid = $this->jwtService->validateToken($token);
            if ($isValid) {
                $this->info('✓ Token validation successful');
            } else {
                $this->error('⨯ Token validation failed');
            }

            // Get user from token
            try {
                $tokenUser = $this->jwtService->getUserFromToken($token);
                if ($tokenUser && $tokenUser->id === $user->id) {
                    $this->info('✓ Successfully retrieved user from token');
                } else {
                    $this->error('⨯ Retrieved incorrect user from token');
                }
            } catch (\Exception $e) {
                $this->error('⨯ Error retrieving user from token: '.$e->getMessage());
            }
        } catch (\Exception $e) {
            $this->error('⨯ Error generating token: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
