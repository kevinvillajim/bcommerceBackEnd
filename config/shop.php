<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de la Tienda
    |--------------------------------------------------------------------------
    |
    | Configuraciones centralizadas para el funcionamiento de la tienda
    | incluyendo impuestos, envío, descuentos, etc.
    |
    */

    'currency' => [
        'code' => env('SHOP_CURRENCY_CODE', 'USD'),
        'symbol' => env('SHOP_CURRENCY_SYMBOL', '$'),
        'decimals' => env('SHOP_CURRENCY_DECIMALS', 2),
    ],

    'tax' => [
        // IVA (Impuesto al Valor Agregado)
        'iva_enabled' => env('SHOP_IVA_ENABLED', true),
        'iva_rate' => env('SHOP_IVA_RATE', 0.15), // 15%
        'iva_name' => env('SHOP_IVA_NAME', 'IVA'),
    ],

    'shipping' => [
        // Configuración de envío
        'enabled' => env('SHOP_SHIPPING_ENABLED', true),
        'default_cost' => env('SHOP_SHIPPING_COST', 5.00), // $5 por vendedor
        'free_threshold' => env('SHOP_FREE_SHIPPING_THRESHOLD', 50.00), // Envío gratis a partir de $50
        'calculate_per_seller' => env('SHOP_SHIPPING_PER_SELLER', true), // $5 por cada vendedor

        // Mensajes de envío
        'free_shipping_message' => 'Envío gratis por compra mayor a ${threshold}',
        'paid_shipping_message' => '${cost} por vendedor',
    ],

    'discounts' => [
        // Descuentos por volumen
        'volume_discounts' => [
            'enabled' => env('SHOP_VOLUME_DISCOUNTS_ENABLED', true),
            'apply_after_seller_discount' => true, // Aplicar después del descuento de seller
        ],

        // Descuentos de seller
        'seller_discounts' => [
            'enabled' => env('SHOP_SELLER_DISCOUNTS_ENABLED', true),
            'max_percentage' => env('SHOP_MAX_SELLER_DISCOUNT', 90), // Máximo 90% de descuento
        ],
    ],

    'cart' => [
        // Configuración del carrito
        'session_lifetime' => env('SHOP_CART_SESSION_LIFETIME', 7 * 24 * 60), // 7 días en minutos
        'max_items' => env('SHOP_CART_MAX_ITEMS', 100),
        'max_quantity_per_item' => env('SHOP_CART_MAX_QUANTITY_PER_ITEM', 99),
    ],

    'orders' => [
        // Configuración de órdenes
        'number_prefix' => env('SHOP_ORDER_PREFIX', 'ORD'),
        'number_length' => env('SHOP_ORDER_NUMBER_LENGTH', 8),
        'auto_confirm_payment' => env('SHOP_AUTO_CONFIRM_PAYMENT', true),

        // Estados de órdenes
        'statuses' => [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
        ],
    ],

    'payment' => [
        // Configuración de pagos
        'methods' => [
            'credit_card' => 'Tarjeta de Crédito',
            'debit_card' => 'Tarjeta de Débito',
            'datafast' => 'Datafast',
            'de_una' => 'De Una',
        ],

        'timeout' => env('SHOP_PAYMENT_TIMEOUT', 15), // minutos
    ],

    'pricing' => [
        // Configuración de precios
        'round_to' => env('SHOP_PRICE_ROUND_TO', 2), // Decimales para redondeo
        'calculation_precision' => env('SHOP_CALCULATION_PRECISION', 4), // Precisión en cálculos

        // Orden de aplicación de descuentos
        'discount_order' => [
            'seller_discount',
            'volume_discount',
        ],

        // Orden de aplicación de impuestos
        'tax_order' => [
            'iva', // IVA se aplica después de descuentos
        ],
    ],
];
