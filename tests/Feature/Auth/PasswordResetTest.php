<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_password_link_generates_token()
    {
        // Create a user
        $user = User::factory()->create();

        // Request password reset
        $response = $this->postJson('/api/auth/forgot-password-email', [
            'email' => $user->email,
        ]);

        // Check response status
        $response->assertStatus(200);

        // Verify token was stored in database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        $this->assertNotNull($tokenRecord);
    }

    #[Test]
    public function can_reset_password_with_valid_token()
    {
        // Create a user
        $user = User::factory()->create();

        // Manually create a token
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Attempt to reset password
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Check response
        $response->assertStatus(200);

        // Verify the password was actually changed
        $updatedUser = User::find($user->id);
        $this->assertTrue(Hash::check('new-password', $updatedUser->password));

        // Verify reset token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    #[Test]
    public function cannot_reset_password_with_invalid_token()
    {
        // Create a user
        $user = User::factory()->create();

        // Attempt to reset password with invalid token
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Check response
        $response->assertStatus(400);

        // Verify the password was not changed
        $updatedUser = User::find($user->id);
        $this->assertTrue(Hash::check('password', $updatedUser->password));
    }

    #[Test]
    public function token_validation_works_correctly()
    {
        // Create a user
        $user = User::factory()->create();

        // Manually create a token
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Validate token
        $response = $this->postJson('/api/auth/reset-password/validate', [
            'email' => $user->email,
            'token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'valid' => true,
        ]);
    }
}
