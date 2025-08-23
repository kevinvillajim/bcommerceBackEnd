<?php

namespace Tests\Unit;

use App\Domain\Entities\UserInteractionEntity;
use App\Domain\Formatters\ProductFormatter;
use App\Domain\Formatters\UserProfileFormatter;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use App\Domain\Services\DemographicProfileGenerator;
use App\Domain\Services\ProfileCompletenessCalculator;
use App\Domain\Services\UserProfileEnricher;
use App\Domain\ValueObjects\UserProfile;
use App\Infrastructure\Services\RecommendationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    protected $userProfileRepositoryMock;

    protected $productRepositoryMock;

    protected $userProfileEnricherMock;

    protected $demographicGeneratorMock;

    protected $completenessCalculatorMock;

    protected $productFormatterMock;

    protected $userProfileFormatterMock;

    protected $service;

    protected $userId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear todos los mocks
        $this->userProfileRepositoryMock = Mockery::mock(UserProfileRepositoryInterface::class);
        $this->productRepositoryMock = Mockery::mock(ProductRepositoryInterface::class);
        $this->userProfileEnricherMock = Mockery::mock(UserProfileEnricher::class);
        $this->demographicGeneratorMock = Mockery::mock(DemographicProfileGenerator::class);
        $this->completenessCalculatorMock = Mockery::mock(ProfileCompletenessCalculator::class);
        $this->productFormatterMock = Mockery::mock(ProductFormatter::class);
        $this->userProfileFormatterMock = Mockery::mock(UserProfileFormatter::class);

        // Crear el servicio con los mocks
        $this->service = new RecommendationService(
            $this->userProfileRepositoryMock,
            $this->productRepositoryMock,
            $this->userProfileEnricherMock,
            $this->demographicGeneratorMock,
            $this->completenessCalculatorMock,
            $this->productFormatterMock,
            $this->userProfileFormatterMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_tracks_user_interaction()
    {
        // Create a UserInteractionEntity to return
        $interaction = new UserInteractionEntity(
            $this->userId,
            'view_product',
            1,
            ['view_time' => 60]
        );

        // Configure mock
        $this->userProfileRepositoryMock->shouldReceive('saveUserInteraction')
            ->once()
            ->andReturn($interaction);

        // Execute method
        $this->service->trackUserInteraction(
            $this->userId,
            'view_product',
            1,
            ['view_time' => 60]
        );

        // La verificación se hace con el método once() de Mockery
        $this->assertTrue(true); // Para que el test tenga al menos una aserción
    }

    #[Test]
    public function it_gets_user_profile()
    {
        // Create a user profile
        $profile = new UserProfile(
            ['smartphones' => 5, 'laptops' => 3],
            [['term' => 'iPhone', 'timestamp' => time()]],
            [['product_id' => 1, 'timestamp' => time()]],
            ['age' => 30, 'gender' => 'male'],
            10
        );

        // Configure expectations for the mock
        $this->userProfileRepositoryMock->shouldReceive('buildUserProfile')
            ->once()
            ->with($this->userId)
            ->andReturn($profile);

        $this->userProfileEnricherMock->shouldReceive('enrichProfile')
            ->once()
            ->with(Mockery::type(UserProfile::class), $this->userId)
            ->andReturn($profile);

        // Get profile
        $result = $this->service->getUserProfile($this->userId);

        // Verify result
        $this->assertInstanceOf(UserProfile::class, $result);
        $this->assertEquals($profile->getInterests(), $result->getInterests());
    }

    #[Test]
    public function it_generates_recommendations_based_on_preferences()
    {
        // Create a user profile with interests and viewed products
        $profile = new UserProfile(
            ['smartphones' => 5, 'laptops' => 3],
            [['term' => 'iPhone', 'timestamp' => time()]],
            [['product_id' => 1, 'timestamp' => time()]],
            ['age' => 30, 'gender' => 'male'],
            10
        );

        // Mock products for recommendations
        $products = [
            ['id' => 1, 'name' => 'iPhone 13', 'price' => 999.99, 'category_id' => 1],
            ['id' => 2, 'name' => 'Samsung Galaxy S21', 'price' => 899.99, 'category_id' => 1],
            ['id' => 3, 'name' => 'Google Pixel 6', 'price' => 799.99, 'category_id' => 1],
        ];

        // Expected recommendations with type
        $recommendedProducts = [
            [
                'id' => 1,
                'name' => 'iPhone 13',
                'price' => 999.99,
                'category_id' => 1,
                'recommendation_type' => 'interest_based',
            ],
            [
                'id' => 2,
                'name' => 'Samsung Galaxy S21',
                'price' => 899.99,
                'category_id' => 1,
                'recommendation_type' => 'interest_based',
            ],
        ];

        // Set up mock expectations - but with mayBeCalled() instead of once()
        $this->userProfileRepositoryMock->shouldReceive('buildUserProfile')
            ->once()
            ->with($this->userId)
            ->andReturn($profile);

        $this->userProfileRepositoryMock->shouldReceive('getCategoryPreferences')
            ->with($this->userId)
            ->andReturn([1 => 10]);

        $this->userProfileRepositoryMock->shouldReceive('getViewedProductIds')
            ->once()
            ->with($this->userId)
            ->andReturn([3]);

        $this->userProfileEnricherMock->shouldReceive('enrichProfile')
            ->once()
            ->with(Mockery::type(UserProfile::class), $this->userId)
            ->andReturn($profile);

        // Mock the strategy
        $strategyMock = Mockery::mock('App\Domain\Services\RecommendationStrategies\StrategyInterface');
        $strategyMock->shouldReceive('getName')
            ->andReturn('popular');
        $strategyMock->shouldReceive('getRecommendations')
            ->andReturn($recommendedProducts);

        // Add the strategy to the service
        $this->service->addStrategy($strategyMock);

        // Generate recommendations
        $recommendations = $this->service->generateRecommendations($this->userId, 2);

        // Verify results
        $this->assertIsArray($recommendations);
        $this->assertNotEmpty($recommendations);
    }

    #[Test]
    public function it_generates_demographic_profile()
    {
        // Test demographic profile generation
        $demographics = [
            'age' => 30,
            'gender' => 'male',
            'location' => 'Ecuador',
        ];

        $expectedInterests = [
            'hogar' => 8,
            'electronica' => 7,
            'deportes' => 6,
        ];

        // Set up mock expectations
        $this->demographicGeneratorMock->shouldReceive('generate')
            ->once()
            ->with($demographics)
            ->andReturn($expectedInterests);

        // Generate interests
        $interests = $this->service->generateDemographicProfile($demographics);

        // Verify that there are demographics-based interests
        $this->assertIsArray($interests);
        $this->assertEquals($expectedInterests, $interests);
    }

    #[Test]
    public function it_enriches_profile_with_demographic_data_when_needed()
    {
        // Create a profile with few interests but with demographic data
        $basicProfile = new UserProfile(
            ['smartphones' => 5], // Only one interest
            [],
            [],
            ['age' => 30, 'gender' => 'male'],
            10
        );

        // Enhanced profile with more interests
        $enhancedProfile = new UserProfile(
            [
                'smartphones' => 5,
                'hogar' => 8,
                'electronica' => 7,
                'deportes' => 6,
            ],
            [],
            [],
            ['age' => 30, 'gender' => 'male'],
            10
        );

        // Configure expectations for the mocks
        $this->userProfileRepositoryMock->shouldReceive('buildUserProfile')
            ->once()
            ->with($this->userId)
            ->andReturn($basicProfile);

        $this->userProfileEnricherMock->shouldReceive('enrichProfile')
            ->once()
            ->with(Mockery::type(UserProfile::class), $this->userId)
            ->andReturn($enhancedProfile);

        // Get profile
        $result = $this->service->getUserProfile($this->userId);

        // Verify that it was enriched with demographic data
        $this->assertInstanceOf(UserProfile::class, $result);
        $this->assertGreaterThan(1, count($result->getInterests()));
        $this->assertEquals($enhancedProfile->getInterests(), $result->getInterests());
    }
}
