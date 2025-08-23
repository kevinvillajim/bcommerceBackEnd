<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_login_successfully()
    {
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
            'is_blocked' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user',
        ]);

        // Verify the token is valid
        $authenticatedUser = JWTAuth::setToken($response['access_token'])->authenticate();
        $this->assertEquals($user->id, $authenticatedUser->id);
    }

    #[Test]
    public function user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid credentials']);
    }

    #[Test]
    public function blocked_user_cannot_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
            'is_blocked' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Your account has been blocked']);
    }

    #[Test]
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJson($user->toArray());
    }

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Successfully logged out']);
    }

    #[Test]
    public function authenticated_user_can_refresh_token()
    {
        $user = User::factory()->create();
        $originalToken = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$originalToken,
        ])->postJson('/api/auth/refresh');

        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user',
        ]);

        // Verify new token is different
        $this->assertNotEquals($originalToken, $response['access_token']);
    }
}
