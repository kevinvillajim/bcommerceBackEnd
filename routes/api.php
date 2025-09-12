<?php

use App\Http\Controllers\AccountingController;
// Controller imports
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminLogController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminRatingController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RecommendationSystemController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartPricingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DatafastController;
use App\Http\Controllers\DeunaPaymentController;
use App\Http\Controllers\DeunaWebhookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HeaderController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemBreakdownController;
use App\Http\Controllers\ConfigurationVersionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RecommendationController;
// Auth-specific controllers
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\ShippingAPISimulatorController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDiscountCodeController;
use App\Http\Middleware\GoogleOAuthMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| PUBLIC Routes (NO Authentication Required)
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::post('forgot-password-email', [PasswordResetController::class, 'sendResetLinkEmail']);
    Route::get('reset-password/verify', [PasswordResetController::class, 'verifyResetToken']); // GET route for email links
    Route::post('forgot-password-token', [PasswordResetController::class, 'requestToken']);
    Route::post('reset-password/validate', [PasswordResetController::class, 'validateToken']);

    // Endpoint público para reglas de validación de contraseñas
    Route::get('password-validation-rules', [ConfigurationController::class, 'getPasswordValidationRules']);
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword']);

    // Protected auth routes
    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('password/update', [PasswordUpdateController::class, 'update']);
    });

    Route::prefix('google')->middleware([
        'web', // Incluye sesiones, cookies, CSRF
        GoogleOAuthMiddleware::class, // Middleware personalizado
    ])->group(function () {
        Route::get('redirect', [GoogleAuthController::class, 'redirectToGoogle']);
        Route::get('callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    });

    // Ruta para autenticación directa (sin sesiones)
    Route::post('google/authenticate', [GoogleAuthController::class, 'authenticateWithGoogle']);
});

/*
|--------------------------------------------------------------------------
| Email Verification Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'email-verification'], function () {
    Route::get('verify', [EmailVerificationController::class, 'verify']);
    Route::post('verify', [EmailVerificationController::class, 'verifyEmail']); // API compatibility
    Route::post('resend', [EmailVerificationController::class, 'resendVerification']);

    // Protected routes
    Route::middleware('jwt.auth')->group(function () {
        Route::get('status', [EmailVerificationController::class, 'status']);
    });
});

// Email verification - Legacy route (keep for compatibility)
Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['jwt.auth', 'signed'])
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Test Routes for Admin Logs (with proper middleware)
|--------------------------------------------------------------------------
*/
Route::middleware('api')->group(function () {
    Route::get('/test-error', function () {
        throw new \Exception('This is a test error for admin logging system!');
    });

    Route::get('/test-detailed-error', function () {
        // Throw a simple exception that should be caught by middleware
        throw new \Exception('This is a detailed test error for middleware logging');
    });
});

/*
|--------------------------------------------------------------------------
| Products - Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/volume-discounts/product/{productId}', [CartController::class, 'getVolumeDiscountInfo']);

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/featured-random', [ProductController::class, 'featuredRandom']);
    Route::get('/trending-offers', [ProductController::class, 'trendingAndOffers']);
    Route::get('/personalized', [ProductController::class, 'personalized']);
    Route::get('/personalized-simple', [ProductController::class, 'personalizedSimple']); // 🚀 SIMPLE: Versión funcional garantizada
    Route::get('/debug-personalized', [ProductController::class, 'debugPersonalized']); // 🔍 DEBUG: Ver qué pasa con recomendaciones
    Route::post('/clear-corrupted-cache', [ProductController::class, 'clearCorruptedCache']); // 🧹 TEMPORAL: Limpiar cache corrupto
    Route::get('/category/{categoryId}', [ProductController::class, 'byCategory'])
        ->middleware(\App\Http\Middleware\TrackInteractionMiddleware::class.':browse_category,route.categoryId'); // 🎯 Auto-track category browsing
    Route::get('/tags', [ProductController::class, 'byTags']);
    Route::get('/seller/{sellerId}', [ProductController::class, 'bySeller']);
    Route::get('/slug/{slug}', [ProductController::class, 'showBySlug'])
        ->middleware(\App\Http\Middleware\TrackInteractionMiddleware::class.':view_product,route.slug'); // 🎯 Auto-track product views
    Route::get('/search/{term?}', [ProductController::class, 'search'])
        ->middleware(\App\Http\Middleware\TrackInteractionMiddleware::class.':search,route.term'); // 🎯 Auto-track searches
    Route::get('/discounted', [ProductController::class, 'discounted']);
    Route::get('/newest', [ProductController::class, 'newest']);
    Route::get('/popular', [ProductController::class, 'popular']);
    Route::get('/{id}', [ProductController::class, 'show'])
        ->middleware(\App\Http\Middleware\TrackInteractionMiddleware::class.':view_product,route.id'); // 🎯 Auto-track product views
    Route::post('/{id}/view', [ProductController::class, 'incrementView']);
});

/*
|--------------------------------------------------------------------------
| Categories - Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/main', [CategoryController::class, 'mainCategories']);
    Route::get('/featured', [CategoryController::class, 'featured']);
    Route::get('/{id}/subcategories', [CategoryController::class, 'subcategories']);
    Route::get('/{id}/products', [CategoryController::class, 'products']);
    Route::get('/slug/{slug}', [CategoryController::class, 'getBySlug']);
    Route::get('/{id}', [CategoryController::class, 'show'])->where('id', '[0-9]+');
});

/*
|--------------------------------------------------------------------------
| Ratings - Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('ratings')->group(function () {
    // Ver valoraciones de vendedor y producto
    Route::get('seller/{sellerId}', [RatingController::class, 'getSellerRatings']);
    Route::get('product/{productId}', [RatingController::class, 'getProductRatings']);
    Route::get('sellers/{sellerId}', [RatingController::class, 'getSellerRatings']);
    Route::get('products/{productId}', [RatingController::class, 'getProductRatings']);

});

/*
|--------------------------------------------------------------------------
| Sellers - Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('sellers')->group(function () {
    Route::get('/top/rating', [SellerController::class, 'getTopSellersByRating']);
    Route::get('/top/sales', [SellerController::class, 'getTopSellersBySales']);
    Route::get('/featured', [SellerController::class, 'getFeaturedSellers']);
});

/*
|--------------------------------------------------------------------------
| Shipping - Public Routes
|--------------------------------------------------------------------------
*/
Route::get('shipping/track/{trackingNumber}', [ShippingController::class, 'trackShipment']);

/*
|--------------------------------------------------------------------------
| Configuration Versions - Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('config')->group(function () {
    Route::get('/versions', [ConfigurationVersionController::class, 'getVersions']);
    Route::get('/version/{configType}', [ConfigurationVersionController::class, 'getVersion']);
});

/*
|--------------------------------------------------------------------------
| External API Integration Routes - Public
|--------------------------------------------------------------------------
*/
Route::post('shipping/external/update', [ShippingController::class, 'externalStatusUpdate']);

/*
|--------------------------------------------------------------------------
| Recommendations Routes - Public (with optional auth)
|--------------------------------------------------------------------------
*/
Route::prefix('recommendations')->group(function () {
    Route::get('/', [RecommendationController::class, 'getRecommendations']);
});

/*
|--------------------------------------------------------------------------
| Development & Testing Routes - Public (but restricted by environment)
|--------------------------------------------------------------------------
*/
Route::prefix('simulator/shipping')->group(function () {
    Route::get('/track/{trackingNumber}', [ShippingAPISimulatorController::class, 'getShippingInfo']);
    Route::post('/simulate-update', [ShippingAPISimulatorController::class, 'simulateStatusUpdate']);
    Route::post('/simulate-cycle/{trackingNumber}', [ShippingAPISimulatorController::class, 'simulateFullShippingCycle']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED User Routes (JWT Auth Required)
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | User Profile Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/upload-avatar', [ProfileController::class, 'uploadAvatar']);
    Route::put('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/user/check-role', [UserController::class, 'checkRole']);

    /*
    |--------------------------------------------------------------------------
    | Seller Application Routes (for users)
    |--------------------------------------------------------------------------
    */
    Route::prefix('seller-applications')->group(function () {
        Route::post('/', [App\Http\Controllers\SellerApplicationController::class, 'store']);
        Route::get('/my-application', [App\Http\Controllers\SellerApplicationController::class, 'getMyApplication']);
    });

    /*
    |--------------------------------------------------------------------------
    | Ratings Routes - Authenticated Users
    |--------------------------------------------------------------------------
    */
    Route::prefix('ratings')->group(function () {
        // Dar valoraciones (solo usuarios pueden valorar)
        Route::post('/seller', [RatingController::class, 'rateSeller']);
        Route::post('/product', [RatingController::class, 'rateProduct']);
        // Ver mis valoraciones dadas
        Route::get('/my/given', [RatingController::class, 'getMyGivenRatings']);
        // Ver valoraciones pendientes
        Route::get('/pending', [RatingController::class, 'getPendingRatings']);
        // Ver detalles de una valoración específica
        Route::get('/{id}', [RatingController::class, 'show']);
        // Ver valoraciones de una orden específica
        Route::get('/order/{orderId}', [RatingController::class, 'getOrderRatings']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Orders Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('user/orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/stats', [OrderController::class, 'userStats']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::get('/{id}/items-breakdown', [OrderItemBreakdownController::class, 'getOrderItemsBreakdown']);
        Route::post('/{id}/reorder', [OrderController::class, 'reorder']);
        Route::post('/{id}/confirm-reception', [OrderController::class, 'confirmReception']);
    });

    /*
    |--------------------------------------------------------------------------
    | Shopping Cart Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::put('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::post('/empty', [CartController::class, 'empty']);
        Route::get('/volume-discount-info/{productId}', [CartController::class, 'getVolumeDiscountInfo']);
        Route::get('/with-pricing', [CartPricingController::class, 'getCartWithPricing']);
        Route::post('/update-pricing', [CartPricingController::class, 'updateCartPricing']);

        // ✅ RUTA MEJORADA: Información de descuentos por volumen (puede reemplazar la existente)
        Route::get('/volume-discounts/product/{productId}', [CartPricingController::class, 'getProductVolumeDiscountInfo']);

        // Discount codes for cart
        Route::post('/discount/validate', [CartController::class, 'validateDiscountCode']);
        Route::post('/discount/apply', [CartController::class, 'applyDiscountCode']);
        Route::post('/discount/remove', [CartController::class, 'removeDiscountCode']);
    });

    /*
    |--------------------------------------------------------------------------
    | Checkout & Payment Routes
    |--------------------------------------------------------------------------
    */
    Route::post('/checkout', [CheckoutController::class, 'process'])->middleware('throttle:checkout');
    
    // 🧮 Pricing Calculator Routes - Centralized calculations
    Route::post('/calculate-totals', [PricingController::class, 'calculateTotals']);
    Route::post('/validate-totals', [PricingController::class, 'validateTotals']);

    Route::prefix('payments')->group(function () {
        Route::post('/credit-card', [PaymentController::class, 'processCreditCard']);
        Route::post('/paypal', [PaymentController::class, 'processPaypal']);
        Route::post('/qr', [PaymentController::class, 'generateQR']);
    });

    /*
    |--------------------------------------------------------------------------
    | Shipping Routes - Authenticated User
    |--------------------------------------------------------------------------
    */
    Route::prefix('shipping')->group(function () {
        Route::get('/{trackingNumber}', [ShippingController::class, 'trackShipment']);
        Route::get('/{trackingNumber}/history', [ShippingController::class, 'getShippingHistory']);
        Route::get('/{trackingNumber}/route', [ShippingController::class, 'getShippingRoute']);
        Route::post('/estimate', [ShippingController::class, 'estimateDelivery']);
        Route::post('/update-status', [ShippingController::class, 'updateShippingStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Chats Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('chats')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::get('/{id}', [ChatController::class, 'show']);
        Route::post('/{id}/messages', [ChatController::class, 'storeMessage']);
        Route::put('/{id}', [ChatController::class, 'update']);
        Route::delete('/{id}', [ChatController::class, 'destroy']);
        Route::get('/{id}/messages', [ChatController::class, 'getMessages']);
        Route::post('/{id}/mark-read', [ChatController::class, 'markAsRead']);
        Route::patch('/{id}/messages/{messageId}/read', [ChatController::class, 'markMessageAsRead']);
        Route::post('/{id}/typing', [ChatController::class, 'updateTypingStatus']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/{id}/status', [UserController::class, 'getStatus']);
        Route::post('/{id}/activity', [UserController::class, 'updateActivity']);
    });

    /*
    |--------------------------------------------------------------------------
    | Recommendations Routes - Authenticated only
    |--------------------------------------------------------------------------
    */
    Route::prefix('recommendations')->group(function () {
        Route::post('/track-interaction', [RecommendationController::class, 'trackInteraction']);
        Route::get('/user-profile', [RecommendationController::class, 'getUserProfile']);
    });

    /*
    |--------------------------------------------------------------------------
    | Feedback & Discount Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('feedback')->group(function () {
        Route::get('/', [FeedbackController::class, 'index']);
        Route::post('/', [FeedbackController::class, 'store']);
        Route::get('/{id}', [FeedbackController::class, 'show']);
    });

    Route::prefix('discounts')->group(function () {
        Route::post('/validate', [FeedbackController::class, 'validateDiscountCode']);
        Route::post('/apply', [FeedbackController::class, 'applyDiscountCode']);
        Route::get('/my-codes', [UserDiscountCodeController::class, 'getUserDiscountCodes']);
    });

    // User Notifications Routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [UserDiscountCodeController::class, 'getNotifications']);
        Route::get('/unread-count', [UserDiscountCodeController::class, 'getUnreadNotificationsCount']);
        Route::put('/{id}/read', [UserDiscountCodeController::class, 'markNotificationAsRead']);
        Route::put('/mark-all-read', [UserDiscountCodeController::class, 'markAllNotificationsAsRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | Favorites Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/toggle', [FavoriteController::class, 'toggle']);
        Route::get('/product/{productId}', [FavoriteController::class, 'check']);
        Route::put('/{id}/notifications', [FavoriteController::class, 'updateNotifications']);
    });

    /*
    |--------------------------------------------------------------------------
    | Notifications Routes - Regular Users
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Header Counters Route
    |--------------------------------------------------------------------------
    */
    Route::get('/user/header-counters', [HeaderController::class, 'counters']);

    /*
    |--------------------------------------------------------------------------
    | Seller Registration Route (for regular users)
    |--------------------------------------------------------------------------
    */
    Route::post('/seller/register', [SellerController::class, 'register']);

    /*
    |--------------------------------------------------------------------------
    | Seller Queries (for regular users)
    |--------------------------------------------------------------------------
    */
    Route::get('/sellers/by-user/{userId}', [SellerController::class, 'getSellerIdByUserId']);
    Route::get('/sellers/active', [SellerController::class, 'getActiveSellers']);

    /*
    |--------------------------------------------------------------------------
    | Invoice Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::post('/generate', [InvoiceController::class, 'generate']);
        Route::post('/{id}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/{id}/download', [InvoiceController::class, 'download']);
    });

    /*
    |--------------------------------------------------------------------------
    | Datafast Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('datafast')->group(function () {
        Route::post('/create-checkout', [DatafastController::class, 'createCheckout'])->middleware('throttle:payment');
        Route::post('/verify-payment', [DatafastController::class, 'verifyPayment'])->middleware('throttle:payment');
        Route::get('/verify-payment/{transactionId}', [DatafastController::class, 'checkPaymentStatus'])->middleware('throttle:payment');
    });
    Route::post('/datafast/webhook', [DatafastController::class, 'webhook'])->middleware('throttle:webhook');
    // Ruta de prueba (solo en desarrollo)
    if (config('app.debug')) {
        Route::get('/test', function () {
            return response()->json([
                'message' => 'API funcionando correctamente',
                'timestamp' => now(),
                'environment' => config('app.env'),
            ]);
        });
    }
});

/*
|--------------------------------------------------------------------------
| SELLER Routes (Requires JWT Auth + Seller Role)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'seller'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Seller Information
    |--------------------------------------------------------------------------
    */
    Route::get('/seller/info', [SellerController::class, 'getSellerInfo']);
    Route::put('/seller/store-info', [SellerController::class, 'updateStoreInfo']);
    Route::get('/seller/dashboard', [SellerController::class, 'dashboard']);

    /*
    |--------------------------------------------------------------------------
    | Seller Products Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::patch('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Shipping Management - NUEVO
    |--------------------------------------------------------------------------
    */
    Route::prefix('shipping')->group(function () {
        Route::get('/', [ShippingController::class, 'getSellerShippingsList']);
        Route::get('/{id}', [ShippingController::class, 'getSellerShippingDetail']);
        Route::patch('/{id}/status', [ShippingController::class, 'updateSellerShippingStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Ratings Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('ratings')->group(function () {
        // Ver valoraciones recibidas (solo vendedores pueden ver sus propias valoraciones recibidas)
        Route::get('/my/received', [RatingController::class, 'getMyReceivedRatings']);
        // Valorar a un usuario (solo vendedores pueden valorar usuarios)
        Route::post('/user', [RatingController::class, 'rateUser']);

        // 🔧 AGREGAR ESTA LÍNEA:
        Route::get('/{id}', [RatingController::class, 'show'])->where('id', '[0-9]+');
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Orders Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('seller/orders')->group(function () {
        // Listado de órdenes con filtros
        Route::get('/', [SellerOrderController::class, 'index']);
        // Estadísticas de pedidos
        Route::get('/stats', [SellerOrderController::class, 'stats']);
        // Pedidos pendientes de envío
        Route::get('/awaiting-shipment', [SellerOrderController::class, 'awaitingShipment']);
        // Clientes del vendedor con sus compras
        Route::get('/customers', [SellerOrderController::class, 'customers']);
        // Órdenes que contienen un producto específico
        Route::get('/product/{productId}', [SellerOrderController::class, 'ordersWithProduct']);
        // Obtener detalles de un pedido específico
        Route::get('/{id}', [SellerOrderController::class, 'show']);
        // Actualizar estado del pedido
        Route::put('/{id}/status', [SellerOrderController::class, 'updateStatus']);
        // Actualizar información de envío
        Route::patch('/{id}/shipping', [SellerOrderController::class, 'updateShipping']);
        // Cancelar un pedido
        Route::post('/{id}/cancel', [SellerOrderController::class, 'cancelOrder']);
        // Completar un pedido
        Route::post('/{id}/complete', [SellerOrderController::class, 'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Chats del Vendedor - NUEVAS RUTAS
    |--------------------------------------------------------------------------
    */
    Route::prefix('seller/chats')->group(function () {
        Route::get('/', [ChatController::class, 'indexSeller']);
        Route::get('/by-seller/{sellerId}', [ChatController::class, 'indexSellerById']);
        Route::get('/{id}', [ChatController::class, 'showSeller']);
        Route::post('/{id}/messages', [ChatController::class, 'storeMessageSeller']);
        Route::put('/{id}', [ChatController::class, 'updateStatus']); // Función específica para actualizar estado como vendedor
        Route::get('/{id}/messages', [ChatController::class, 'getMessagesSeller']);
        Route::post('/{id}/mark-read', [ChatController::class, 'markAsReadSeller']);
        Route::post('/{id}/typing', [ChatController::class, 'updateTypingStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('seller/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Public Configuration Routes (Accesible para todos los visitantes)
|--------------------------------------------------------------------------
*/
// Configuración de envío - Público para que todos vean el umbral de envío gratis
Route::get('/configurations/shipping-public', [App\Http\Controllers\Admin\ConfigurationController::class, 'getShippingConfigs']);

/*
|--------------------------------------------------------------------------
| ADMIN Routes (Requires JWT Auth + Admin Role) - CORREGIDO
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'admin'])->prefix('admin')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'getStats']);

    /*
    |--------------------------------------------------------------------------
    | Users and Admins Management
    |--------------------------------------------------------------------------
    */
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);
    Route::put('users/{id}/block', [AdminUserController::class, 'block']);
    Route::put('users/{id}/unblock', [AdminUserController::class, 'unblock']);
    Route::post('users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::put('users/{id}/make-admin', [AdminUserController::class, 'makeAdmin']);
    Route::post('users/{id}/make-seller', [AdminUserController::class, 'makeSeller']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Sellers Management
    |--------------------------------------------------------------------------
    */
    Route::get('sellers', [AdminSellerController::class, 'index']);
    Route::get('sellers/{id}', [AdminSellerController::class, 'show']);
    Route::put('sellers/{id}/status', [AdminSellerController::class, 'updateStatus']);

    /*
    Route::get('/sellers', [AdminController::class, 'listSellers']);
    Route::put('/sellers/{id}/status', [AdminController::class, 'updateSellerStatus']);
    Route::post('/sellers', [AdminController::class, 'createSeller']);
    Route::put('/sellers/{id}', [AdminController::class, 'updateSeller']);
    Route::post('/sellers/{id}/feature-products', [AdminController::class, 'featureAllSellerProducts']);

    /*
    |--------------------------------------------------------------------------
    | Products Management - RUTAS CORREGIDAS
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->group(function () {
        // NUEVA RUTA: Listar productos con datos completos para admin
        Route::get('/', [App\Http\Controllers\Admin\AdminProductController::class, 'index']);

        // Crear producto como admin
        Route::post('/', [App\Http\Controllers\Admin\AdminProductController::class, 'store']);

        // Actualizar producto completamente (PUT)
        Route::put('/{id}', [App\Http\Controllers\Admin\AdminProductController::class, 'update']);

        // Actualización parcial (PATCH) - MUY IMPORTANTE PARA TOGGLES
        Route::patch('/{id}', [App\Http\Controllers\Admin\AdminProductController::class, 'partialUpdate']);

        // Eliminar producto
        Route::delete('/{id}', [App\Http\Controllers\Admin\AdminProductController::class, 'destroy']);

        // Estadísticas de productos
        Route::get('/stats', [App\Http\Controllers\Admin\AdminProductController::class, 'getStats']);

        // ✅ NUEVO: Obtener impacto de eliminación antes de confirmar
        Route::get('/{id}/deletion-impact', [App\Http\Controllers\Admin\AdminProductController::class, 'getDeletionImpact']);
    });

    /*
    |--------------------------------------------------------------------------
    | Sellers Information for Admin
    |--------------------------------------------------------------------------
    */
    // Ruta simple para dropdowns en formularios (solo datos básicos)
    Route::get('/sellers-simple', [App\Http\Controllers\Admin\AdminProductController::class, 'getSellersSimple']);

    // Ruta detallada para listados y páginas de gestión (datos completos)
    Route::get('/sellers-details', [App\Http\Controllers\Admin\AdminProductController::class, 'getSellers']);

    /*
    |--------------------------------------------------------------------------
    | Categories Management - ADMIN OPTIMIZADO
    |--------------------------------------------------------------------------
    */
    Route::prefix('categories')->group(function () {
        // ✅ NUEVO: Lista optimizada de categorías con conteos reales
        Route::get('/', [App\Http\Controllers\Admin\AdminCategoryController::class, 'index']);

        // ✅ NUEVO: Estadísticas de categorías
        Route::get('/stats', [App\Http\Controllers\Admin\AdminCategoryController::class, 'getStats']);

        // ✅ NUEVO: Categorías principales para dropdown
        Route::get('/main', [App\Http\Controllers\Admin\AdminCategoryController::class, 'getMainCategories']);

        // ✅ NUEVO: Detalles de categoría específica con productos
        Route::get('/{id}', [App\Http\Controllers\Admin\AdminCategoryController::class, 'show']);

        // Crear categoría como admin
        Route::post('/', [CategoryController::class, 'store']);

        // Actualizar categoría completamente (PUT)
        Route::put('/{id}', [CategoryController::class, 'update']);

        // ✅ MEJORADO: Actualización parcial optimizada (PATCH)
        Route::patch('/{id}', [App\Http\Controllers\Admin\AdminCategoryController::class, 'partialUpdate']);

        // ✅ MEJORADO: Eliminar categoría optimizada
        Route::delete('/{id}', [App\Http\Controllers\Admin\AdminCategoryController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | ⚠️ Financial Configurations - SUPER ADMIN ONLY
    |--------------------------------------------------------------------------
    */
    Route::prefix('configurations/financial')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\FinancialConfigurationController::class, 'index']);
        Route::put('/', [App\Http\Controllers\Admin\FinancialConfigurationController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | Ratings Moderation
    |--------------------------------------------------------------------------
    */
    Route::prefix('ratings')->group(function () {
        Route::get('/', [AdminRatingController::class, 'index']);
        Route::post('/{id}/approve', [AdminRatingController::class, 'approve']);
        Route::post('/{id}/reject', [AdminRatingController::class, 'reject']);
        Route::post('/{id}/flag', [AdminRatingController::class, 'flag']);
        Route::get('/stats', [AdminRatingController::class, 'getStats']);
        Route::get('/pending', [AdminController::class, 'listPendingRatings']);
        Route::put('/{id}/moderate', [AdminController::class, 'moderateRating']);
        Route::post('/approve-all', [AdminController::class, 'approveAllPendingRatings']);
        Route::delete('/{id}', [RatingController::class, 'destroy']);
        // Ver detalles completos de rating (solo admin)
        Route::get('/{id}/details', [AdminRatingController::class, 'show']);
        Route::get('/stats', [AdminRatingController::class, 'getStats']);
        Route::get('/{id}/details', [AdminRatingController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Orders Administration
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/stats', [AdminOrderController::class, 'getOrderStats']);
        Route::get('/{id}', [AdminOrderController::class, 'show']);
        Route::put('/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/{id}/cancel', [AdminOrderController::class, 'cancelOrder']);
        Route::patch('/{id}/shipping', [AdminOrderController::class, 'updateShipping']);
    });

    /*
    |--------------------------------------------------------------------------
    | Shipping Administration
    |--------------------------------------------------------------------------
    */
    Route::get('/shippings', [ShippingController::class, 'getAdminShippingsList']);
    Route::get('/shippings/{id}', [ShippingController::class, 'getAdminShippingDetail']);
    Route::get('/shipping/{trackingNumber}/history', [ShippingController::class, 'getShippingHistory']);
    Route::put('/shipping/{trackingNumber}/status', [ShippingController::class, 'updateShippingStatus']);
    Route::post('/shipping/{trackingNumber}/simulate', [ShippingController::class, 'simulateShippingEvents']);

    /*
    |--------------------------------------------------------------------------
    | Admin Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Feedback Administration
    |--------------------------------------------------------------------------
    */
    Route::get('/feedback/pending', [FeedbackController::class, 'pendingFeedbacks']);
    Route::post('/feedback/{id}/review', [FeedbackController::class, 'review']);

    /*
    |--------------------------------------------------------------------------
    | Accounting Routes (for admins)
    |--------------------------------------------------------------------------
    */
    Route::prefix('accounting')->group(function () {
        Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet']);
        Route::get('/income-statement', [AccountingController::class, 'incomeStatement']);
        Route::get('/accounts', [AccountingController::class, 'accounts']);
        Route::get('/accounts/{id}/ledger', [AccountingController::class, 'accountLedger']);
    });

    /*
    |--------------------------------------------------------------------------
    | Invoice Management Routes (for admins) - SRI System
    |--------------------------------------------------------------------------
    */
    Route::prefix('invoices')->group(function () {
        // Lista paginada de facturas con filtros
        Route::get('/', [AdminInvoiceController::class, 'index']);
        
        // Detalles completos de una factura
        Route::get('/{id}', [AdminInvoiceController::class, 'show']);
        
        // Actualizar datos de una factura
        Route::put('/{id}', [AdminInvoiceController::class, 'update']);
        
        // Reintenta una factura fallida
        Route::post('/{id}/retry', [AdminInvoiceController::class, 'retry']);
        
        // Consulta estado actual en SRI
        Route::get('/{id}/check-status', [AdminInvoiceController::class, 'checkStatus']);
        
        // Estadísticas de facturas para dashboard
        Route::get('/stats/overview', [AdminInvoiceController::class, 'stats']);
    });

    /*
    |--------------------------------------------------------------------------
    | Super Admin Only Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin:super_admin')->group(function () {
        Route::get('/admins', [AdminController::class, 'listAdmins']);
        Route::post('/admins', [AdminController::class, 'manageAdmin']);
        Route::delete('/admins/{userId}', [AdminController::class, 'removeAdmin']);
    });


    /*
    |--------------------------------------------------------------------------
    | Recommendation System Administration
    |--------------------------------------------------------------------------
    */
    Route::prefix('recommendation-system')->group(function () {
        // Dashboard y métricas generales
        Route::get('/dashboard', [RecommendationSystemController::class, 'dashboard']);
        Route::get('/performance', [RecommendationSystemController::class, 'performanceMetrics']);

        // Análisis de usuarios
        Route::get('/users/{userId}/analysis', [RecommendationSystemController::class, 'analyzeUser']);

        // Configuración del sistema
        Route::get('/configuration', [RecommendationSystemController::class, 'getConfiguration']);
        Route::post('/configuration', [RecommendationSystemController::class, 'updateConfiguration']);

        // Gestión de caché
        Route::post('/cache/clear', [RecommendationSystemController::class, 'clearCache']);

        // Exportación de datos
        Route::post('/export', [RecommendationSystemController::class, 'exportData']);
    });

    /*
    |--------------------------------------------------------------------------
    | Volume Discounts Administration
    |--------------------------------------------------------------------------
    */
    Route::prefix('volume-discounts')->group(function () {
        Route::get('/configuration', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'getConfiguration']);
        Route::post('/configuration', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'updateConfiguration']);
        Route::get('/stats', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'getStats']);
        Route::get('/product/{productId}', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'getProductDiscounts']);
        Route::post('/product/{productId}', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'updateProductDiscounts']);
        Route::post('/product/{productId}/apply-defaults', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'applyDefaultDiscounts']);
        Route::delete('/product/{productId}', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'removeProductDiscounts']);
        Route::post('/bulk/apply-defaults', [App\Http\Controllers\Admin\VolumeDiscountController::class, 'bulkApplyDefaults']);
    });

    /*
    |--------------------------------------------------------------------------
    | Platform Commission Routes - Admin Only
    |--------------------------------------------------------------------------
    */
    Route::prefix('platform-commission')->group(function () {
        Route::get('/configuration', [App\Http\Controllers\Admin\PlatformCommissionController::class, 'getConfiguration']);
        Route::post('/configuration', [App\Http\Controllers\Admin\PlatformCommissionController::class, 'updateConfiguration']);
        Route::post('/calculate', [App\Http\Controllers\Admin\PlatformCommissionController::class, 'calculateCommission']);
    });

    /*
    |--------------------------------------------------------------------------
    | Shipping Distribution Routes - Admin Only
    |--------------------------------------------------------------------------
    */
    Route::prefix('shipping-distribution')->group(function () {
        Route::get('/configuration', [App\Http\Controllers\Admin\ShippingDistributionController::class, 'getConfiguration']);
        Route::post('/configuration', [App\Http\Controllers\Admin\ShippingDistributionController::class, 'updateConfiguration']);
        Route::post('/calculate', [App\Http\Controllers\Admin\ShippingDistributionController::class, 'calculateDistribution']);
    });

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration Routes - Admin Only
    |--------------------------------------------------------------------------
    */
    Route::prefix('tax')->group(function () {
        Route::get('/configuration', [App\Http\Controllers\Admin\TaxConfigurationController::class, 'getConfiguration']);
        Route::post('/configuration', [App\Http\Controllers\Admin\TaxConfigurationController::class, 'updateConfiguration']);
        Route::post('/calculate', [App\Http\Controllers\Admin\TaxConfigurationController::class, 'calculateTax']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Discount Codes Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('discount-codes')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'stats']);
        Route::get('/generate-code', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'generateCode']);
        Route::post('/', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'destroy']);
        Route::post('/validate', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'validate']);
        Route::post('/apply', [App\Http\Controllers\Admin\AdminDiscountCodeController::class, 'apply']);
    });

    /*
    |--------------------------------------------------------------------------
    | Seller Applications Administration (for admins)
    |--------------------------------------------------------------------------
    */
    Route::prefix('seller-applications')->group(function () {
        Route::get('/', [App\Http\Controllers\SellerApplicationController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\SellerApplicationController::class, 'getStats']);
        Route::get('/{id}', [App\Http\Controllers\SellerApplicationController::class, 'show']);
        Route::post('/{id}/approve', [App\Http\Controllers\SellerApplicationController::class, 'approve']);
        Route::post('/{id}/reject', [App\Http\Controllers\SellerApplicationController::class, 'reject']);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Logs Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('logs')->group(function () {
        Route::get('/', [AdminLogController::class, 'index']);
        Route::get('/stats', [AdminLogController::class, 'stats']);
        Route::get('/recent', [AdminLogController::class, 'recent']);
        Route::get('/critical', [AdminLogController::class, 'critical']);
        Route::get('/event-types', [AdminLogController::class, 'eventTypes']);
        Route::get('/users', [AdminLogController::class, 'users']);
        Route::get('/by-event-type', [AdminLogController::class, 'byEventType']);
        Route::post('/cleanup', [AdminLogController::class, 'cleanup']);
        Route::get('/{id}', [AdminLogController::class, 'show']);
        Route::delete('/{id}', [AdminLogController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Configuraciones del sistema
    |--------------------------------------------------------------------------
    */
    Route::prefix('configurations')->group(function () {
        // Rutas específicas para configuraciones de ratings
        Route::get('/ratings', [ConfigurationController::class, 'getRatingConfigs']);
        Route::post('/ratings', [ConfigurationController::class, 'updateRatingConfigs']);

        // Rutas específicas para configuraciones de moderación
        Route::get('/moderation', [ConfigurationController::class, 'getModerationConfigs']);
        Route::post('/moderation', [ConfigurationController::class, 'updateModerationConfigs']);

        // Rutas específicas para configuraciones de envío
        Route::get('/shipping', [ConfigurationController::class, 'getShippingConfigs']);
        Route::post('/shipping', [ConfigurationController::class, 'updateShippingConfigs']);

        // Rutas específicas para configuraciones de desarrollo
        Route::get('/development', [ConfigurationController::class, 'getDevelopmentConfigs']);
        Route::post('/development', [ConfigurationController::class, 'updateDevelopmentConfigs']);

        // Rutas específicas para configuraciones de correo
        Route::get('/mail', [App\Http\Controllers\Auth\EmailVerificationController::class, 'getMailConfiguration']);
        Route::post('/mail', [App\Http\Controllers\Auth\EmailVerificationController::class, 'updateMailConfiguration']);
        Route::post('/mail/test', [App\Http\Controllers\Auth\EmailVerificationController::class, 'testMailConfiguration']);
        Route::post('/mail/send-custom', [App\Http\Controllers\Auth\EmailVerificationController::class, 'sendCustomEmail']);
        
        // Mail testing and debugging routes (admin only)
        Route::post('/mail/debug-test', [App\Http\Controllers\Admin\MailTestController::class, 'testMail']);
        Route::get('/mail/status', [App\Http\Controllers\Admin\MailTestController::class, 'getStatus']);

        // Rutas para configuraciones por categoría
        Route::get('/category', [ConfigurationController::class, 'getByCategory']);
        Route::post('/category', [ConfigurationController::class, 'updateByCategory']);

        // Ruta para reglas de validación de contraseñas
        Route::get('/password-validation-rules', [ConfigurationController::class, 'getPasswordValidationRules']);

        // Rutas genericas
        Route::get('/', [ConfigurationController::class, 'index']);
        Route::get('/{key}', [ConfigurationController::class, 'show']);
        Route::post('/update', [ConfigurationController::class, 'update']);
    });
});

/*
|--------------------------------------------------------------------------
| DeUna Payment Gateway Routes
|--------------------------------------------------------------------------
| Routes for DeUna payment integration
*/

// DeUna controllers already imported at the top

// DeUna Payment Routes - Protected with JWT
Route::middleware(['jwt.auth'])->prefix('deuna')->group(function () {
    // Create new payment
    Route::post('/payments', [DeunaPaymentController::class, 'createPayment']);

    // Generate QR code
    Route::post('/payments/qr', [DeunaPaymentController::class, 'generateQR']);

    // Get payment status
    Route::get('/payments/{paymentId}/status', [DeunaPaymentController::class, 'getPaymentStatus']);

    // Get payment by order ID
    Route::get('/orders/{orderId}/payment', [DeunaPaymentController::class, 'getPaymentByOrderId']);

    // List payments with filters
    Route::get('/payments', [DeunaPaymentController::class, 'listPayments']);

    // Cancel payment
    Route::post('/payments/{paymentId}/cancel', [DeunaPaymentController::class, 'cancelPayment']);

    // Void/Refund payment
    Route::post('/payments/{paymentId}/void', [DeunaPaymentController::class, 'voidPayment']);
});

// DeUna Webhook Routes - No authentication required (validated by middleware)
Route::middleware(['deuna.webhook', 'throttle:webhook'])->prefix('webhooks/deuna')->name('deuna.webhook.')->group(function () {
    // Main webhook endpoint for payment status updates
    Route::post('/payment-status', [DeunaWebhookController::class, 'handlePaymentStatus'])->name('payment-status');

    // Test webhook endpoint (local environment only)
    Route::post('/test', [DeunaWebhookController::class, 'testWebhook'])->name('test');

    // Webhook information endpoint
    Route::get('/info', [DeunaWebhookController::class, 'getWebhookInfo'])->name('info');

    // Signature verification utility (local environment only)
    Route::post('/verify-signature', [DeunaWebhookController::class, 'verifySignature'])->name('verify-signature');
});

// DeUna Testing Routes - For development simulation (no middleware validation)
Route::prefix('webhooks/deuna')->name('deuna.webhook.')->group(function () {
    // Simulate payment success - Now also used for processing real QR payments when webhook doesn't trigger
    Route::post('/simulate-payment-success', [DeunaWebhookController::class, 'simulatePaymentSuccess'])->name('simulate-payment-success');
});

// Configuración de descuentos por volumen - Público para que el frontend calcule correctamente
Route::get('/configurations/volume-discounts-public', [App\Http\Controllers\Admin\ConfigurationController::class, 'getVolumeDiscountConfigs']);

// Configuración de comisión de plataforma - Público para sellers
Route::get('/configurations/platform-commission-public', [App\Http\Controllers\Admin\PlatformCommissionController::class, 'getConfiguration']);

// Configuración de distribución de envío - Público para sellers
Route::get('/configurations/shipping-distribution-public', [App\Http\Controllers\Admin\ShippingDistributionController::class, 'getConfiguration']);

// Configuración de impuestos - Público para que sellers y frontend puedan acceder
Route::get('/configurations/tax-public', [App\Http\Controllers\Admin\TaxConfigurationController::class, 'getConfiguration']);

// 🎯 ENDPOINT UNIFICADO: Todas las configuraciones en una sola llamada (Optimización de rendimiento)
Route::get('/configurations/unified', [App\Http\Controllers\Admin\UnifiedConfigurationController::class, 'getUnifiedConfiguration']);
