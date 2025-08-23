<?php

namespace Tests\Unit\Middleware;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Http\Middleware\JwtMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwtMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected $jwtServiceMock;

    protected $middleware;

    protected $blockedUser;

    protected $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear y registrar el mock de JwtServiceInterface
        $this->jwtServiceMock = Mockery::mock(JwtServiceInterface::class);
        $this->app->instance(JwtServiceInterface::class, $this->jwtServiceMock);

        // Ahora podemos usar el contenedor para crear el middleware
        $this->middleware = app(JwtMiddleware::class);

        // Crear usuarios de prueba
        $this->normalUser = User::factory()->create(['is_blocked' => false]);
        $this->blockedUser = User::factory()->create(['is_blocked' => true]);
    }

    #[Test]
    public function it_rejects_request_from_blocked_user()
    {
        // Crear request
        $request = new Request;
        $request->headers->set('Authorization', 'Bearer fake-token');

        // Configurar expectativas del mock
        $this->jwtServiceMock->shouldReceive('validateToken')
            ->once()
            ->with('fake-token')
            ->andReturn(true);

        $this->jwtServiceMock->shouldReceive('getUserFromToken')
            ->once()
            ->with('fake-token')
            ->andReturn($this->blockedUser);

        // Ejecutar middleware
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        // Verificar respuesta
        $this->assertEquals(403, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Forbidden', $content['error']);
    }

    #[Test]
    public function it_allows_request_from_non_blocked_user()
    {
        // Crear request
        $request = new Request;
        $request->headers->set('Authorization', 'Bearer fake-token');

        // Configurar expectativas del mock
        $this->jwtServiceMock->shouldReceive('validateToken')
            ->once()
            ->with('fake-token')
            ->andReturn(true);

        $this->jwtServiceMock->shouldReceive('getUserFromToken')
            ->once()
            ->with('fake-token')
            ->andReturn($this->normalUser);

        // Ejecutar middleware
        $response = $this->middleware->handle($request, function () {
            return response('OK');
        });

        // Verificar respuesta
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
