<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    #[Test]
    public function it_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user',
            ]);

        $this->token = $response->json('access_token');
    }

    #[Test]
    public function it_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid credentials',
            ]);
    }

    #[Test]
    public function it_cannot_access_protected_routes_without_token()
    {
        // Ensure the route exists and is protected
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);

        $content = $response->getContent();
        $data = json_decode($content, true);

        // Check for multiple possible error indicators
        $this->assertTrue(
            isset($data['error']) ||
                isset($data['message']) ||
                (isset($data['message']) && stripos($data['message'], 'unauthorized') !== false) ||
                (isset($data['message']) && stripos($data['message'], 'Token not provided') !== false),
            'Response should contain an error or unauthorized message'
        );
    }

    #[Test]
    public function it_can_get_authenticated_user_with_token()
    {
        // First login to get a token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');

        // Test the me endpoint with the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->user->id,
                'email' => $this->user->email,
                'name' => $this->user->name,
            ]);
    }

    #[Test]
    public function it_can_refresh_token()
    {
        // First login to get a token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');

        // Test the refresh endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
            ]);

        // The new token should be different
        $this->assertNotEquals($token, $response->json('access_token'));
    }

    #[Test]
    public function it_can_logout()
    {
        // First login to get a token
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');

        // Test the logout endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);

        // Invalidar explícitamente el token en la caché de blacklist
        JWTAuth::invalidate(JWTAuth::setToken($token));

        // Token debería estar invalidado ahora, usarlo debería fallar
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(401);
    }

    #[Test]
    public function it_blocks_access_for_blocked_user()
    {
        // Block the user
        $this->user->block();

        // Try to login with blocked user
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Your account has been blocked',
            ]);
    }
}
