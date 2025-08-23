<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckArchitecture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-architecture';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica que los archivos de la arquitectura existan y estén configurados correctamente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Verificando arquitectura de la aplicación...');

        // 1. Verificar estructura de carpetas
        $this->checkDirectories();

        // 2. Verificar interfaces
        $this->checkInterfaces();

        // 3. Verificar implementaciones
        $this->checkImplementations();

        // 4. Verificar casos de uso
        $this->checkUseCases();

        // 5. Verificar configuración
        $this->checkConfigurations();

        // 6. Verificar middleware
        $this->checkMiddleware();

        $this->info('Verificación completada.');
    }

    /**
     * Verifica que los directorios esenciales existan
     */
    private function checkDirectories()
    {
        $this->info('Verificando directorios...');

        $directories = [
            app_path('Domain'),
            app_path('Domain/Interfaces'),
            app_path('Domain/Entities'),
            app_path('Domain/Repositories'),
            app_path('Domain/ValueObjects'),
            app_path('Infrastructure'),
            app_path('Infrastructure/Services'),
            app_path('Infrastructure/Repositories'),
            app_path('Infrastructure/External'),
            app_path('UseCases'),
            app_path('UseCases/User'),
        ];

        foreach ($directories as $directory) {
            if (File::isDirectory($directory)) {
                $this->line(" ✓ {$directory}");
            } else {
                $this->error(" ✗ {$directory} no existe");

                // Intentar crear el directorio
                if ($this->confirm("¿Desea crear el directorio {$directory}?")) {
                    File::makeDirectory($directory, 0755, true, true);
                    $this->info("   Directorio {$directory} creado");
                }
            }
        }
    }

    /**
     * Verifica las interfaces esenciales
     */
    private function checkInterfaces()
    {
        $this->info('Verificando interfaces...');

        $interfaces = [
            'JwtServiceInterface' => app_path('Domain/Interfaces/JwtServiceInterface.php'),
        ];

        foreach ($interfaces as $name => $path) {
            if (File::exists($path)) {
                $this->line(" ✓ {$name}");

                // Verificar el contenido para confirmar que es una interfaz
                $content = File::get($path);
                if (! str_contains($content, 'interface '.$name)) {
                    $this->warn("   El archivo {$name} no parece contener una definición de interfaz correcta");
                }
            } else {
                $this->error(" ✗ Interfaz {$name} no existe en {$path}");
            }
        }
    }

    /**
     * Verifica las implementaciones esenciales
     */
    private function checkImplementations()
    {
        $this->info('Verificando implementaciones...');

        $implementations = [
            'JwtService' => app_path('Infrastructure/Services/JwtService.php'),
        ];

        foreach ($implementations as $name => $path) {
            if (File::exists($path)) {
                $this->line(" ✓ {$name}");

                // Verificar el contenido para confirmar que implementa la interfaz
                $content = File::get($path);
                if (! str_contains($content, 'implements') && ! str_contains($content, 'JwtServiceInterface')) {
                    $this->warn("   La clase {$name} no parece implementar la interfaz correcta");
                }
            } else {
                $this->error(" ✗ Implementación {$name} no existe en {$path}");
            }
        }
    }

    /**
     * Verifica los casos de uso esenciales
     */
    private function checkUseCases()
    {
        $this->info('Verificando casos de uso...');

        $useCases = [
            'LoginUserUseCase' => app_path('UseCases/User/LoginUserUseCase.php'),
            'LogoutUserUseCase' => app_path('UseCases/User/LogoutUserUseCase.php'),
            'RefreshTokenUseCase' => app_path('UseCases/User/RefreshTokenUseCase.php'),
            'GetAuthenticatedUserUseCase' => app_path('UseCases/User/GetAuthenticatedUserUseCase.php'),
        ];

        foreach ($useCases as $name => $path) {
            if (File::exists($path)) {
                $this->line(" ✓ {$name}");

                // Verificar el contenido para asegurar namespace correcto
                $content = File::get($path);
                if (! str_contains($content, 'namespace App\UseCases\User')) {
                    $this->warn("   El caso de uso {$name} no tiene el namespace correcto");
                }
            } else {
                $this->error(" ✗ Caso de uso {$name} no existe en {$path}");

                // Generar casos de uso faltantes
                if ($this->confirm("¿Desea generar el caso de uso {$name}?")) {
                    $this->generateUseCase($name, $path);
                }
            }
        }
    }

    /**
     * Verifica la configuración del sistema
     */
    private function checkConfigurations()
    {
        $this->info('Verificando configuraciones...');

        // Verificar config/jwt.php
        if (File::exists(config_path('jwt.php'))) {
            $this->line(' ✓ config/jwt.php');
        } else {
            $this->error(' ✗ config/jwt.php no existe');
        }

        // Verificar config/auth.php para guardias JWT
        if (File::exists(config_path('auth.php'))) {
            $this->line(' ✓ config/auth.php');

            $authConfig = include config_path('auth.php');
            if (
                isset($authConfig['guards']['api']) &&
                isset($authConfig['guards']['api']['driver']) &&
                $authConfig['guards']['api']['driver'] === 'jwt'
            ) {
                $this->line('   ✓ API guard configurado para JWT');
            } else {
                $this->warn('   ✗ API guard no está configurado para JWT');
            }
        } else {
            $this->error(' ✗ config/auth.php no existe');
        }

        // Verificar registro de providers
        $appConfig = include config_path('app.php');
        if (in_array(\Tymon\JWTAuth\Providers\LaravelServiceProvider::class, $appConfig['providers'] ?? [])) {
            $this->line(' ✓ Tymon JWT Provider registrado');
        } else {
            $this->warn(' ✗ Tymon JWT Provider no parece estar registrado');
        }
    }

    /**
     * Verifica los middleware esenciales
     */
    private function checkMiddleware()
    {
        $this->info('Verificando middleware...');

        // Verificar JwtMiddleware
        $jwtMiddlewarePath = app_path('Http/Middleware/JwtMiddleware.php');
        if (File::exists($jwtMiddlewarePath)) {
            $this->line(' ✓ JwtMiddleware');

            // Verificar registro en Kernel.php
            $kernelPath = app_path('Http/Kernel.php');
            if (File::exists($kernelPath)) {
                $kernelContent = File::get($kernelPath);
                if (
                    str_contains($kernelContent, 'jwt.auth') &&
                    str_contains($kernelContent, 'JwtMiddleware')
                ) {
                    $this->line('   ✓ JwtMiddleware registrado en Kernel');
                } else {
                    $this->warn('   ✗ JwtMiddleware no parece estar registrado en Kernel');
                }
            }
        } else {
            $this->error(' ✗ JwtMiddleware no existe');
        }
    }

    /**
     * Genera un caso de uso faltante
     */
    private function generateUseCase($name, $path)
    {
        // Determinar el tipo de caso de uso para generar el contenido apropiado
        $content = '';

        switch ($name) {
            case 'LoginUserUseCase':
                $content = $this->getLoginUseCaseTemplate();
                break;
            case 'LogoutUserUseCase':
                $content = $this->getLogoutUseCaseTemplate();
                break;
            case 'RefreshTokenUseCase':
                $content = $this->getRefreshTokenUseCaseTemplate();
                break;
            case 'GetAuthenticatedUserUseCase':
                $content = $this->getGetAuthenticatedUserUseCaseTemplate();
                break;
            default:
                $content = $this->getDefaultUseCaseTemplate($name);
        }

        // Asegurar que el directorio existe
        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        // Escribir el archivo
        File::put($path, $content);
        $this->info("   Caso de uso {$name} generado en {$path}");
    }

    /**
     * Plantilla para LoginUserUseCase
     */
    private function getLoginUseCaseTemplate()
    {
        return '<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use Illuminate\Support\Facades\Auth;

class LoginUserUseCase
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @param JwtServiceInterface $jwtService
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     *
     * @param array $credentials
     * @return array|null
     */
    public function execute(array $credentials): ?array
    {
        if (!Auth::attempt($credentials)) {
            return null;
        }

        $user = Auth::user();
        
        if ($user->isBlocked()) {
            return [\'error\' => \'Your account has been blocked.\'];
        }

        $token = $this->jwtService->generateToken($user);

        return [
            \'access_token\' => $token,
            \'token_type\' => \'bearer\',
            \'expires_in\' => auth()->factory()->getTTL() * 60,
            \'user\' => $user
        ];
    }
}';
    }

    /**
     * Plantilla para LogoutUserUseCase
     */
    private function getLogoutUseCaseTemplate()
    {
        return '<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;

class LogoutUserUseCase
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @param JwtServiceInterface $jwtService
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     *
     * @return bool
     */
    public function execute(): bool
    {
        try {
            $token = $this->jwtService->parseToken();
            
            if ($token) {
                $this->jwtService->invalidateToken($token);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}';
    }

    /**
     * Plantilla para RefreshTokenUseCase
     */
    private function getRefreshTokenUseCaseTemplate()
    {
        return '<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;

class RefreshTokenUseCase
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @param JwtServiceInterface $jwtService
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     *
     * @return array|null
     */
    public function execute(): ?array
    {
        try {
            $token = $this->jwtService->parseToken();
            
            if (!$token) {
                return null;
            }
            
            $refreshedToken = $this->jwtService->refreshToken($token);
            
            return [
                \'access_token\' => $refreshedToken,
                \'token_type\' => \'bearer\',
                \'expires_in\' => auth()->factory()->getTTL() * 60
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}';
    }

    /**
     * Plantilla para GetAuthenticatedUserUseCase
     */
    private function getGetAuthenticatedUserUseCaseTemplate()
    {
        return '<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;

class GetAuthenticatedUserUseCase
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @param JwtServiceInterface $jwtService
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     *
     * @return mixed
     */
    public function execute()
    {
        try {
            $token = $this->jwtService->parseToken();
            
            if (!$token) {
                return null;
            }
            
            $user = $this->jwtService->getUserFromToken($token);
            
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }
}';
    }

    /**
     * Plantilla para un caso de uso genérico
     */
    private function getDefaultUseCaseTemplate($name)
    {
        return '<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;

class '.$name.'
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @param JwtServiceInterface $jwtService
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     *
     * @return mixed
     */
    public function execute()
    {
        // Implementación pendiente
    }
}';
    }
}
