<?php

namespace Tests\Unit\Services;

use App\Infrastructure\Services\JwtService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $jwtService;

    protected $user;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = new JwtService;

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Generate a real token for the user
        $this->token = JWTAuth::fromUser($this->user);
    }

    #[Test]
    public function it_can_generate_token()
    {
        $token = $this->jwtService->generateToken($this->user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    #[Test]
    public function it_can_get_user_from_token()
    {
        $user = $this->jwtService->getUserFromToken($this->token);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($this->user->id, $user->id);
    }

    #[Test]
    public function it_can_validate_token()
    {
        $isValid = $this->jwtService->validateToken($this->token);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_returns_false_for_invalid_token()
    {
        $invalidToken = 'invalid.token.string';
        $isValid = $this->jwtService->validateToken($invalidToken);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_can_refresh_token()
    {
        $newToken = $this->jwtService->refreshToken($this->token);

        $this->assertIsString($newToken);
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($this->token, $newToken);
    }

    #[Test]
    public function it_can_invalidate_token()
    {
        $result = $this->jwtService->invalidateToken($this->token);

        $this->assertTrue($result);

        // Try to use the invalidated token
        $this->expectException(\Tymon\JWTAuth\Exceptions\TokenBlacklistedException::class);
        JWTAuth::setToken($this->token)->authenticate();
    }
}
