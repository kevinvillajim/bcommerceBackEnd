<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\VolumeDiscountController;
use App\Models\Admin;
use App\Models\Configuration;
use App\Models\User;
use App\Services\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ConfigurationSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $adminUser;

    private $normalUser;

    private $adminToken;

    private $configurationController;

    private $volumeDiscountController;

    private $configService;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios de prueba
        $this->normalUser = User::factory()->create([
            'name' => 'Normal User',
            'email' => 'user@test.com',
            'password' => bcrypt('UserTest123!'),
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('AdminTest123!'),
        ]);

        // Crear registro admin
        Admin::create([
            'user_id' => $this->adminUser->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Generar token JWT para admin
        $this->adminToken = JWTAuth::fromUser($this->adminUser);

        // Inicializar controllers y services
        $this->configService = app(ConfigurationService::class);
        $this->configurationController = new ConfigurationController($this->configService);
        $this->volumeDiscountController = app(VolumeDiscountController::class);

        // Crear configuraciones iniciales si no existen
        $this->seedInitialConfigurations();
    }

    private function seedInitialConfigurations()
    {
        $initialConfigs = [
            // General
            ['key' => 'general.siteName', 'value' => 'B-Commerce', 'group' => 'general', 'type' => 'text'],
            ['key' => 'general.siteDescription', 'value' => 'Test platform', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'general.contactEmail', 'value' => 'admin@test.com', 'group' => 'general', 'type' => 'email'],
            ['key' => 'general.adminEmail', 'value' => 'admin@test.com', 'group' => 'general', 'type' => 'email'],
            ['key' => 'general.itemsPerPage', 'value' => '12', 'group' => 'general', 'type' => 'number'],
            ['key' => 'general.maintenanceMode', 'value' => 'false', 'group' => 'general', 'type' => 'boolean'],
            ['key' => 'general.enableRegistration', 'value' => 'true', 'group' => 'general', 'type' => 'boolean'],
            ['key' => 'general.defaultLanguage', 'value' => 'es', 'group' => 'general', 'type' => 'select'],
            ['key' => 'general.defaultCurrency', 'value' => 'USD', 'group' => 'general', 'type' => 'select'],
            ['key' => 'general.timeZone', 'value' => 'America/Guayaquil', 'group' => 'general', 'type' => 'select'],

            // Security
            ['key' => 'security.passwordMinLength', 'value' => '8', 'group' => 'security', 'type' => 'number'],
            ['key' => 'security.passwordRequireSpecial', 'value' => 'true', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'security.passwordRequireUppercase', 'value' => 'true', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'security.passwordRequireNumbers', 'value' => 'true', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'security.accountLockAttempts', 'value' => '5', 'group' => 'security', 'type' => 'number'],
            ['key' => 'security.sessionTimeout', 'value' => '120', 'group' => 'security', 'type' => 'number'],
            ['key' => 'security.enableTwoFactor', 'value' => 'false', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'security.requireEmailVerification', 'value' => 'true', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'security.adminIpRestriction', 'value' => '', 'group' => 'security', 'type' => 'textarea'],
            ['key' => 'security.enableCaptcha', 'value' => 'false', 'group' => 'security', 'type' => 'boolean'],

            // Email
            ['key' => 'email.smtpHost', 'value' => 'smtp.mailserver.com', 'group' => 'email', 'type' => 'text'],
            ['key' => 'email.smtpPort', 'value' => '587', 'group' => 'email', 'type' => 'number'],
            ['key' => 'email.smtpUsername', 'value' => 'noreply@test.com', 'group' => 'email', 'type' => 'text'],
            ['key' => 'email.smtpPassword', 'value' => 'testpassword', 'group' => 'email', 'type' => 'password'],
            ['key' => 'email.smtpEncryption', 'value' => 'tls', 'group' => 'email', 'type' => 'select'],
            ['key' => 'email.senderName', 'value' => 'Test System', 'group' => 'email', 'type' => 'text'],
            ['key' => 'email.senderEmail', 'value' => 'noreply@test.com', 'group' => 'email', 'type' => 'email'],
            ['key' => 'email.notificationEmails', 'value' => 'true', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email.welcomeEmail', 'value' => 'true', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email.orderConfirmationEmail', 'value' => 'true', 'group' => 'email', 'type' => 'boolean'],
            ['key' => 'email.passwordResetEmail', 'value' => 'true', 'group' => 'email', 'type' => 'boolean'],

            // Payment
            ['key' => 'payment.currencySymbol', 'value' => '$', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'payment.currencyCode', 'value' => 'USD', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'payment.enablePayPal', 'value' => 'true', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment.payPalClientId', 'value' => 'test-client-id', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'payment.payPalClientSecret', 'value' => 'test-secret', 'group' => 'payment', 'type' => 'password'],
            ['key' => 'payment.payPalSandboxMode', 'value' => 'true', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment.enableCreditCard', 'value' => 'true', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment.stripePublicKey', 'value' => 'pk_test_sample', 'group' => 'payment', 'type' => 'text'],
            ['key' => 'payment.stripeSecretKey', 'value' => 'sk_test_sample', 'group' => 'payment', 'type' => 'password'],
            ['key' => 'payment.stripeSandboxMode', 'value' => 'true', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment.enableLocalPayments', 'value' => 'true', 'group' => 'payment', 'type' => 'boolean'],
            ['key' => 'payment.taxRate', 'value' => '12', 'group' => 'payment', 'type' => 'number'],

            // Notifications
            ['key' => 'notifications.adminNewOrder', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.adminNewUser', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.adminLowStock', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.adminNewReview', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.adminFailedPayment', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.sellerNewOrder', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.sellerLowStock', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.sellerProductReview', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.sellerMessageReceived', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.sellerReturnRequest', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.userOrderStatus', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.userDeliveryUpdates', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.userPromotions', 'value' => 'false', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.userAccountChanges', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],
            ['key' => 'notifications.userPasswordChanges', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean'],

            // Integrations
            ['key' => 'integrations.googleAnalyticsId', 'value' => 'UA-XXXXXXXXX-X', 'group' => 'integrations', 'type' => 'text'],
            ['key' => 'integrations.enableGoogleAnalytics', 'value' => 'false', 'group' => 'integrations', 'type' => 'boolean'],
            ['key' => 'integrations.facebookPixelId', 'value' => '', 'group' => 'integrations', 'type' => 'text'],
            ['key' => 'integrations.enableFacebookPixel', 'value' => 'false', 'group' => 'integrations', 'type' => 'boolean'],
            ['key' => 'integrations.recaptchaSiteKey', 'value' => '6LxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxE', 'group' => 'integrations', 'type' => 'text'],
            ['key' => 'integrations.recaptchaSecretKey', 'value' => 'secret_key_test', 'group' => 'integrations', 'type' => 'password'],
            ['key' => 'integrations.enableHotjar', 'value' => 'false', 'group' => 'integrations', 'type' => 'boolean'],
            ['key' => 'integrations.hotjarId', 'value' => '', 'group' => 'integrations', 'type' => 'text'],
            ['key' => 'integrations.enableChatbot', 'value' => 'false', 'group' => 'integrations', 'type' => 'boolean'],
            ['key' => 'integrations.chatbotScript', 'value' => '', 'group' => 'integrations', 'type' => 'textarea'],

            // Backup
            ['key' => 'backup.automaticBackups', 'value' => 'true', 'group' => 'backup', 'type' => 'boolean'],
            ['key' => 'backup.backupFrequency', 'value' => 'daily', 'group' => 'backup', 'type' => 'select'],
            ['key' => 'backup.backupTime', 'value' => '02:00', 'group' => 'backup', 'type' => 'time'],
            ['key' => 'backup.backupRetention', 'value' => '30', 'group' => 'backup', 'type' => 'number'],
            ['key' => 'backup.includeMedia', 'value' => 'true', 'group' => 'backup', 'type' => 'boolean'],
            ['key' => 'backup.backupToCloud', 'value' => 'false', 'group' => 'backup', 'type' => 'boolean'],
            ['key' => 'backup.cloudProvider', 'value' => 'none', 'group' => 'backup', 'type' => 'select'],
            ['key' => 'backup.cloudApiKey', 'value' => '', 'group' => 'backup', 'type' => 'password'],
            ['key' => 'backup.cloudSecret', 'value' => '', 'group' => 'backup', 'type' => 'password'],
            ['key' => 'backup.cloudBucket', 'value' => '', 'group' => 'backup', 'type' => 'text'],

            // Volume Discounts
            ['key' => 'volume_discounts.enabled', 'value' => 'true', 'group' => 'volume_discounts', 'type' => 'boolean'],
            ['key' => 'volume_discounts.stackable', 'value' => 'false', 'group' => 'volume_discounts', 'type' => 'boolean'],
            ['key' => 'volume_discounts.default_tiers', 'value' => '[{"quantity":3,"discount":5,"label":"Descuento 3+"},{"quantity":6,"discount":10,"label":"Descuento 6+"}]', 'group' => 'volume_discounts', 'type' => 'json'],
            ['key' => 'volume_discounts.show_savings_message', 'value' => 'true', 'group' => 'volume_discounts', 'type' => 'boolean'],

            // Shipping
            ['key' => 'shipping.free_threshold', 'value' => '50.00', 'group' => 'shipping', 'type' => 'decimal'],
            ['key' => 'shipping.default_cost', 'value' => '5.00', 'group' => 'shipping', 'type' => 'decimal'],
            ['key' => 'shipping.enabled', 'value' => 'true', 'group' => 'shipping', 'type' => 'boolean'],

            // Moderation
            ['key' => 'moderation.userStrikesThreshold', 'value' => '3', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.contactScorePenalty', 'value' => '3', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.businessScoreBonus', 'value' => '15', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.contactPenaltyHeavy', 'value' => '20', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.minimumContactScore', 'value' => '8', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.scoreDifferenceThreshold', 'value' => '5', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.consecutiveNumbersLimit', 'value' => '7', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.numbersWithContextLimit', 'value' => '3', 'group' => 'moderation', 'type' => 'number'],
            ['key' => 'moderation.lowStockThreshold', 'value' => '5', 'group' => 'moderation', 'type' => 'number'],

            // Limits
            ['key' => 'limits.cartMaxItems', 'value' => '100', 'group' => 'limits', 'type' => 'number'],
            ['key' => 'limits.cartMaxQuantityPerItem', 'value' => '99', 'group' => 'limits', 'type' => 'number'],
            ['key' => 'limits.orderTimeout', 'value' => '15', 'group' => 'limits', 'type' => 'number'],
            ['key' => 'limits.recommendationLimit', 'value' => '10', 'group' => 'limits', 'type' => 'number'],
            ['key' => 'limits.maxRecommendationResults', 'value' => '10000', 'group' => 'limits', 'type' => 'number'],
            ['key' => 'limits.tokenRefreshThreshold', 'value' => '15', 'group' => 'limits', 'type' => 'number'],

            // Ratings
            ['key' => 'ratings.auto_approve_all', 'value' => 'false', 'group' => 'ratings', 'type' => 'boolean'],
            ['key' => 'ratings.auto_approve_threshold', 'value' => '2', 'group' => 'ratings', 'type' => 'number'],
        ];

        foreach ($initialConfigs as $config) {
            Configuration::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }

    /** @test */
    public function test_can_get_all_configurations()
    {
        // Actuar como admin
        $this->actingAs($this->adminUser);

        // Probar endpoint de todas las configuraciones
        $response = $this->configurationController->index();
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);

    }

    /** @test */
    public function test_can_get_configurations_by_category()
    {
        $this->actingAs($this->adminUser);

        $categories = [
            'general', 'security', 'email', 'payment', 'notifications',
            'integrations', 'backup', 'volume_discounts', 'shipping',
            'moderation', 'limits', 'ratings',
        ];

        foreach ($categories as $category) {
            $request = new Request(['category' => $category]);
            $response = $this->configurationController->getByCategory($request);
            $data = $response->getData(true);

            $this->assertEquals('success', $data['status']);
            $this->assertArrayHasKey('data', $data);
            $this->assertIsArray($data['data']);

        }
    }

    /** @test */
    public function test_can_update_configurations_by_category()
    {
        $this->actingAs($this->adminUser);

        // Test General configurations
        $generalUpdates = [
            'category' => 'general',
            'configurations' => [
                'siteName' => 'Updated Commerce',
                'siteDescription' => 'Updated description',
                'itemsPerPage' => 20,
                'maintenanceMode' => true,
            ],
        ];

        $request = new Request($generalUpdates);
        $response = $this->configurationController->updateByCategory($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('results', $data);

        // Verificar que se guardaron correctamente
        $this->assertEquals('Updated Commerce', $this->configService->getConfig('general.siteName'));
        $this->assertEquals('Updated description', $this->configService->getConfig('general.siteDescription'));
        $this->assertEquals(20, $this->configService->getConfig('general.itemsPerPage'));
        $this->assertTrue($this->configService->getConfig('general.maintenanceMode'));

        // Test Security configurations with snake_case mapping
        $securityUpdates = [
            'category' => 'security',
            'configurations' => [
                'password_min_length' => 12,
                'password_require_special' => false,
                'account_lock_attempts' => 7,
                'session_timeout' => 180,
            ],
        ];

        $request = new Request($securityUpdates);
        $response = $this->configurationController->updateByCategory($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);

        // Verificar mapeo snake_case -> camelCase
        $this->assertEquals(12, $this->configService->getConfig('security.passwordMinLength'));
        $this->assertFalse($this->configService->getConfig('security.passwordRequireSpecial'));
        $this->assertEquals(7, $this->configService->getConfig('security.accountLockAttempts'));
        $this->assertEquals(180, $this->configService->getConfig('security.sessionTimeout'));

        // Test Email configurations
        $emailUpdates = [
            'category' => 'email',
            'configurations' => [
                'smtpHost' => 'smtp.gmail.com',
                'smtpPort' => 465,
                'senderName' => 'New Sender',
                'welcomeEmail' => false,
            ],
        ];

        $request = new Request($emailUpdates);
        $response = $this->configurationController->updateByCategory($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);

        $this->assertEquals('smtp.gmail.com', $this->configService->getConfig('email.smtpHost'));
        $this->assertEquals(465, $this->configService->getConfig('email.smtpPort'));
        $this->assertEquals('New Sender', $this->configService->getConfig('email.senderName'));
        $this->assertFalse($this->configService->getConfig('email.welcomeEmail'));

        // Test Payment configurations
        $paymentUpdates = [
            'category' => 'payment',
            'configurations' => [
                'currencySymbol' => '€',
                'currencyCode' => 'EUR',
                'taxRate' => 18,
                'enablePayPal' => false,
            ],
        ];

        $request = new Request($paymentUpdates);
        $response = $this->configurationController->updateByCategory($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);

        $this->assertEquals('€', $this->configService->getConfig('payment.currencySymbol'));
        $this->assertEquals('EUR', $this->configService->getConfig('payment.currencyCode'));
        $this->assertEquals(18, $this->configService->getConfig('payment.taxRate'));
        $this->assertFalse($this->configService->getConfig('payment.enablePayPal'));

    }

    /** @test */
    public function test_password_validation_rules()
    {
        $this->actingAs($this->adminUser);

        // Configurar reglas específicas
        $this->configService->setConfig('security.passwordMinLength', '10');
        $this->configService->setConfig('security.passwordRequireSpecial', 'true');
        $this->configService->setConfig('security.passwordRequireUppercase', 'false');
        $this->configService->setConfig('security.passwordRequireNumbers', 'true');

        $response = $this->configurationController->getPasswordValidationRules();
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);

        $rules = $data['data'];
        $this->assertEquals(10, $rules['minLength']);
        $this->assertTrue($rules['requireSpecial']);
        $this->assertFalse($rules['requireUppercase']);
        $this->assertTrue($rules['requireNumbers']);

        $this->assertArrayHasKey('validationMessage', $rules);
        $this->assertArrayHasKey('requirements', $rules);

    }

    /** @test */
    public function test_volume_discount_configurations()
    {
        $this->actingAs($this->adminUser);

        // Test get volume discount configuration
        $response = $this->volumeDiscountController->getConfiguration();
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);

        // Test update volume discount configuration
        $request = new Request([
            'enabled' => false,
            'stackable' => true,
            'show_savings_message' => false,
            'default_tiers' => [
                ['quantity' => 5, 'discount' => 10, 'label' => 'Bulk 5+'],
                ['quantity' => 10, 'discount' => 20, 'label' => 'Bulk 10+'],
            ],
        ]);

        $response = $this->volumeDiscountController->updateConfiguration($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);

        // Verificar que se guardaron
        $this->assertFalse($this->configService->getConfig('volume_discounts.enabled'));
        $this->assertTrue($this->configService->getConfig('volume_discounts.stackable'));
        $this->assertFalse($this->configService->getConfig('volume_discounts.show_savings_message'));

    }

    /** @test */
    public function test_ratings_configurations()
    {
        $this->actingAs($this->adminUser);

        // Test get ratings configuration
        $response = $this->configurationController->getRatingConfigs();
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);

        // Test update ratings configuration
        $request = new Request([
            'auto_approve_all' => true,
            'auto_approve_threshold' => 4,
        ]);

        $response = $this->configurationController->updateRatingConfigs($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);

        // Verificar que se guardaron
        $this->assertTrue($this->configService->getConfig('ratings.auto_approve_all'));
        $this->assertEquals(4, $this->configService->getConfig('ratings.auto_approve_threshold'));

    }

    /** @test */
    public function test_data_type_conversions()
    {
        $this->actingAs($this->adminUser);

        // Test que los tipos de datos se convierten correctamente
        $testUpdates = [
            'category' => 'general',
            'configurations' => [
                'itemsPerPage' => '25',        // string -> int
                'maintenanceMode' => 'true',    // string -> bool
            ],
        ];

        $request = new Request($testUpdates);
        $response = $this->configurationController->updateByCategory($request);

        // Verificar conversiones
        $this->assertIsInt($this->configService->getConfig('general.itemsPerPage'));
        $this->assertEquals(25, $this->configService->getConfig('general.itemsPerPage'));

        $this->assertIsBool($this->configService->getConfig('general.maintenanceMode'));
        $this->assertTrue($this->configService->getConfig('general.maintenanceMode'));

    }

    /** @test */
    public function test_individual_configuration_endpoints()
    {
        $this->actingAs($this->adminUser);

        // Test get individual configuration
        $response = $this->configurationController->show('general.siteName');
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);

        // Test update individual configuration
        $request = new Request([
            'key' => 'general.siteName',
            'value' => 'Individual Updated Site',
        ]);

        $response = $this->configurationController->update($request);
        $data = $response->getData(true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Individual Updated Site', $this->configService->getConfig('general.siteName'));

    }

    /** @test */
    public function test_all_configuration_categories()
    {
        $this->actingAs($this->adminUser);

        $allCategories = [
            'general' => ['siteName', 'siteDescription', 'itemsPerPage'],
            'security' => ['passwordMinLength', 'passwordRequireSpecial', 'accountLockAttempts'],
            'email' => ['smtpHost', 'smtpPort', 'senderName'],
            'payment' => ['currencySymbol', 'currencyCode', 'taxRate'],
            'notifications' => ['adminNewOrder', 'userOrderStatus', 'sellerNewOrder'],
            'integrations' => ['googleAnalyticsId', 'enableGoogleAnalytics', 'facebookPixelId'],
            'backup' => ['automaticBackups', 'backupFrequency', 'backupRetention'],
            'volume_discounts' => ['enabled', 'stackable', 'show_savings_message'],
            'shipping' => ['free_threshold', 'default_cost', 'enabled'],
            'moderation' => ['userStrikesThreshold', 'contactScorePenalty', 'businessScoreBonus'],
            'limits' => ['cartMaxItems', 'cartMaxQuantityPerItem', 'orderTimeout'],
            'ratings' => ['auto_approve_all', 'auto_approve_threshold'],
        ];

        foreach ($allCategories as $category => $sampleKeys) {
            $request = new Request(['category' => $category]);
            $response = $this->configurationController->getByCategory($request);
            $data = $response->getData(true);

            $this->assertEquals('success', $data['status']);
            $this->assertArrayHasKey('data', $data);

            // Verificar que al menos algunas claves están presentes
            $configData = $data['data'];
            $hasAnyKey = false;
            foreach ($sampleKeys as $key) {
                if (array_key_exists($key, $configData)) {
                    $hasAnyKey = true;
                    break;
                }
            }
            $this->assertTrue($hasAnyKey, "Category {$category} should have at least one of the expected keys");

        }
    }

    /** @test */
    public function test_authentication_and_authorization()
    {
        // Test sin autenticación
        $request = new Request(['category' => 'general']);

        try {
            $this->configurationController->getByCategory($request);
            $this->fail('Should have thrown exception for unauthenticated access');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected exception
        }

        // Test con usuario normal (no admin)
        $this->actingAs($this->normalUser);

        try {
            $this->configurationController->getByCategory($request);
            $this->fail('Should have thrown exception for non-admin access');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected exception
        }

    }

    /** @test */
    public function test_configuration_service_directly()
    {
        // Test ConfigurationService methods directly

        // Test setConfig and getConfig
        $this->configService->setConfig('test.directValue', 'direct test value');
        $this->assertEquals('direct test value', $this->configService->getConfig('test.directValue'));

        // Test with default value
        $this->assertEquals('default', $this->configService->getConfig('nonexistent.key', 'default'));

        // Test type conversions
        $this->configService->setConfig('test.numberValue', '42');
        $this->assertEquals(42, $this->configService->getConfig('test.numberValue'));

        $this->configService->setConfig('test.boolValue', 'true');
        $this->assertTrue($this->configService->getConfig('test.boolValue'));

    }

    public function runAllTests()
    {
        try {
            $this->test_can_get_all_configurations();
            $this->test_can_get_configurations_by_category();
            $this->test_can_update_configurations_by_category();
            $this->test_password_validation_rules();
            $this->test_volume_discount_configurations();
            $this->test_ratings_configurations();
            $this->test_data_type_conversions();
            $this->test_individual_configuration_endpoints();
            $this->test_all_configuration_categories();
            $this->test_authentication_and_authorization();
            $this->test_configuration_service_directly();

        } catch (\Exception $e) {
            throw $e;
        }
    }
}
