<?php

namespace Tests\Unit\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Models\User;
use App\UseCases\User\LoginUserUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginUserUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected $jwtServiceMock;

    protected $useCase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create mock for JWT service
        $this->jwtServiceMock = Mockery::mock(JwtServiceInterface::class);

        // Create use case with mocked service
        $this->useCase = new LoginUserUseCase($this->jwtServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_token_for_valid_credentials()
    {
        // JWT TTL is now managed by centralized session_timeout configuration
        config(['session_timeout.ttl' => 60]);
        // Arrange - setup JWT service mock to return a token
        $this->jwtServiceMock->shouldReceive('generateToken')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn('valid-token');

        // Act - execute the use case
        $result = $this->useCase->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert - check the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals('valid-token', $result['access_token']);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('user', $result);
    }

    #[Test]
    public function it_returns_null_for_invalid_credentials()
    {
        // Act - execute the use case with invalid password
        $result = $this->useCase->execute([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        // Assert - should return null for invalid credentials
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_error_for_blocked_user()
    {
        // Arrange - block the user
        $this->user->block();

        // Act - execute the use case
        $result = $this->useCase->execute([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert - check the error result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('blocked', $result['error']);
    }
}
