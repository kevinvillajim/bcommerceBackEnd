<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_can_be_verified()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        // Create a signed URL for email verification
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        // Create JWT token for authentication
        $token = JWTAuth::fromUser($user);

        // Send verification request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get($verificationUrl);

        // Assert events and user state
        Event::assertDispatched(Verified::class);

        // Refresh user model and check verification status
        $updatedUser = $user->fresh();
        $this->assertTrue($updatedUser->hasVerifiedEmail());

        // Check redirect response
        $response->assertRedirect(config('app.frontend_url').'/dashboard?verified=1');
    }

    #[Test]
    public function email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        // Create a signed URL with an incorrect hash
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong-email'),
            ]
        );

        // Create JWT token for authentication
        $token = JWTAuth::fromUser($user);

        // Send verification request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get($verificationUrl);

        // Check that email remains unverified
        $updatedUser = $user->fresh();
        $this->assertFalse($updatedUser->hasVerifiedEmail());

        // Check for error response
        $response->assertStatus(400);
    }
}
