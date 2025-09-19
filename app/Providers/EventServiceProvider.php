<?php

namespace App\Providers;

use App\Events\CreditNoteApproved;
use App\Events\FeedbackCreated;
use App\Events\FeedbackReviewed;
use App\Events\InvoiceApproved;
use App\Events\InvoiceGenerated;
// Nuevos eventos para notificaciones
use App\Events\MessageSent;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\ProductStockUpdated;
use App\Events\ProductUpdated;
use App\Events\RatingCreated;
// Listeners para notificaciones
use App\Events\SellerAccountBlocked;
use App\Events\SellerRankChanged;
use App\Events\SellerStrikeAdded;
use App\Events\ShippingDelayed;
use App\Events\ShippingStatusUpdated;
use App\Listeners\GenerateInvoiceFromOrderListener;
use App\Listeners\GeneratePdfFromCreditNoteListener;
use App\Listeners\GeneratePdfFromInvoiceListener;
use App\Listeners\NotifyAdminOfFeedback;
use App\Listeners\NotifyAdminSellerRankUp;
use App\Listeners\NotifySellerOfAccountBlock;
use App\Listeners\NotifySellerOfFeedbackResponse;
// Seller notification events
use App\Listeners\NotifySellerOfLowStock;
use App\Listeners\NotifySellerOfNewOrder;
use App\Listeners\NotifySellerOfShippingDelay;
use App\Listeners\NotifySellerOfStrike;
use App\Listeners\NotifySellerRankChanged;
use App\Listeners\SendCreditNoteEmailListener;
use App\Listeners\SendFeedbackResponseNotification;
// Seller notification listeners
use App\Listeners\SendInvoiceEmailListener;
use App\Listeners\SendInvoiceToSriListener;
use App\Listeners\SendNewMessageNotification;
use App\Listeners\SendOrderStatusNotification;
use App\Listeners\SendProductUpdateNotifications;
use App\Listeners\SendRatingReceivedNotification;
use App\Listeners\SendRatingRequestNotification;
use App\Listeners\SendShippingStatusNotification;
use App\Listeners\RecordSaleTransactionListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 🔧 CORREGIDO: Event to listener mappings sin duplicaciones
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // 🎆 NUEVO: Evento para nuevas órdenes
        OrderCreated::class => [
            NotifySellerOfNewOrder::class,
            \App\Listeners\InvalidateCartCacheListener::class, // 🛒 Invalidar cache del carrito en header
            GenerateInvoiceFromOrderListener::class, // ✅ Generar factura automáticamente
            RecordSaleTransactionListener::class, // 🧮 Registrar transacción contable automáticamente
        ],

        // ✅ NUEVO: Evento para facturas generadas
        InvoiceGenerated::class => [
            SendInvoiceToSriListener::class, // ✅ Enviar automáticamente al SRI
        ],

        // ✅ NUEVO: Evento para facturas aprobadas por SRI
        InvoiceApproved::class => [
            GeneratePdfFromInvoiceListener::class, // ✅ Generar PDF automáticamente
            SendInvoiceEmailListener::class, // ✅ Enviar email con PDF adjunto
        ],

        // ✅ NUEVO: Evento para notas de crédito aprobadas por SRI
        CreditNoteApproved::class => [
            GeneratePdfFromCreditNoteListener::class, // ✅ Generar PDF automáticamente
            SendCreditNoteEmailListener::class, // ✅ Enviar email con PDF adjunto
        ],

        OrderCompleted::class => [
            SendRatingRequestNotification::class,
        ],

        // Eventos de notificaciones para usuarios
        OrderStatusChanged::class => [
            SendOrderStatusNotification::class,
        ],

        FeedbackCreated::class => [
            NotifyAdminOfFeedback::class,
        ],

        FeedbackReviewed::class => [
            SendFeedbackResponseNotification::class,
            NotifySellerOfFeedbackResponse::class,
        ],

        // 🔧 CORREGIDO: Solo un listener para mensajes
        MessageSent::class => [
            SendNewMessageNotification::class, // Este maneja tanto usuarios como vendedores
        ],

        ProductUpdated::class => [
            SendProductUpdateNotifications::class,
        ],

        ShippingStatusUpdated::class => [
            SendShippingStatusNotification::class,
            SendRatingRequestNotification::class,
        ],

        // 🔧 CORREGIDO: Solo un listener para ratings
        RatingCreated::class => [
            SendRatingReceivedNotification::class, // Este maneja la notificación al vendedor y admin
        ],

        // Notificaciones Seller
        ProductStockUpdated::class => [
            NotifySellerOfLowStock::class,
        ],

        SellerStrikeAdded::class => [
            NotifySellerOfStrike::class,
        ],

        SellerAccountBlocked::class => [
            NotifySellerOfAccountBlock::class,
        ],

        ShippingDelayed::class => [
            NotifySellerOfShippingDelay::class,
        ],

        SellerRankChanged::class => [
            NotifySellerRankChanged::class,
            NotifyAdminSellerRankUp::class,
        ],

        // Add these new events for favorites
        \App\Events\ProductPriceChanged::class => [
            \App\Listeners\NotifyFavoritePriceChange::class,
        ],

        \App\Events\ProductPromotionAdded::class => [
            \App\Listeners\NotifyFavoritePromotion::class,
        ],

        \App\Events\ProductLowStock::class => [
            \App\Listeners\NotifyFavoriteLowStock::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
