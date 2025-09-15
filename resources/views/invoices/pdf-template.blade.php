<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11px;
            line-height: 1.3;
            color: #1a1a1a;
            background-color: #ffffff;
        }

        .invoice-container {
            max-width: 190mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }

        /* Header */
        .header {
            margin-bottom: 25px;
            border-bottom: 3px solid #0f497d;
            padding-bottom: 15px;
        }

        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .company-info {
            display: table-cell;
            width: 65%;
            vertical-align: top;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .footer-logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .footer-logo img {
            width: 150px;
            height: auto;
        }

        .company-details {
            font-size: 10px;
            color: #555555;
            line-height: 1.4;
        }

        .company-details strong {
            color: #0f497d;
        }

        .invoice-title {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            text-align: right;
        }

        .invoice-title h1 {
            font-size: 24px;
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .invoice-meta {
            font-size: 10px;
            color: #555555;
            line-height: 1.4;
        }

        .invoice-meta strong {
            color: #0f497d;
        }

        /* Customer and Invoice Info */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .customer-info, .invoice-details {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }

        .info-block {
            border: 1px solid #d5d5d5;
            padding: 12px;
            margin-bottom: 8px;
        }

        .info-block h3 {
            font-size: 11px;
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 3px;
        }

        .info-block p {
            margin-bottom: 2px;
            font-size: 10px;
            color: #1a1a1a;
        }

        .info-block strong {
            color: #0f497d;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
            border: 1px solid #0f497d;
        }

        .items-table th {
            background: #0f497d;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            border-right: 1px solid #34495e;
        }

        .items-table th:last-child {
            border-right: none;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .items-table td {
            padding: 6px 6px;
            border-bottom: 1px solid #d5d5d5;
            border-right: 1px solid #d5d5d5;
            vertical-align: top;
        }

        .items-table td:last-child {
            border-right: none;
        }

        .items-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .item-name {
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 2px;
        }

        .item-sku {
            font-size: 9px;
            color: #7f8c8d;
            font-style: italic;
        }

        .discount-note {
            font-size: 9px;
            color: #27ae60;
            font-style: italic;
            margin-top: 2px;
        }

        /* Totals Section */
        .totals-section {
            margin-bottom: 20px;
        }

        .totals-wrapper {
            float: right;
            width: 300px;
        }

        .totals {
            width: 100%;
            font-size: 10px;
            border: 1px solid #0f497d;
        }

        .totals td {
            padding: 4px 8px;
            border-bottom: 1px solid #d5d5d5;
        }

        .totals td:first-child {
            background: #ecf0f1;
            font-weight: bold;
            color: #0f497d;
            width: 60%;
        }

        .totals td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .totals .total-row td {
            background: #0f497d;
            color: white;
            font-weight: bold;
            font-size: 11px;
            border-bottom: none;
        }

        .totals .discount-row td:first-child {
            background: #d5f4e6;
            color: #27ae60;
        }

        .totals .discount-row td:last-child {
            color: #27ae60;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* SRI Section */
        .sri-section {
            background: #f8fffe;
            border: 2px solid #0f497d;
            padding: 12px;
            margin-bottom: 15px;
            font-size: 10px;
        }

        .sri-section h4 {
            color: #0f497d;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 11px;
            border-bottom: 1px solid #0f497d;
            padding-bottom: 3px;
        }

        .sri-info {
            display: table;
            width: 100%;
        }

        .sri-info div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .sri-info p {
            margin-bottom: 2px;
            color: #1a1a1a;
        }

        .sri-info strong {
            color: #0f497d;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #0f497d;
            text-align: center;
            font-size: 9px;
            color: #7f8c8d;
        }

        .footer p {
            margin-bottom: 3px;
        }

        .footer .footer-title {
            font-weight: bold;
            color: #0f497d;
            font-size: 10px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-authorized {
            background: #d5f4e6;
            color: #27ae60;
            border-color: #27ae60;
        }

        .status-pending {
            background: #ffeaa7;
            color: #d63031;
            border-color: #d63031;
        }

        /* Utility Classes */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-weight-bold { font-weight: bold; }
        .text-muted { color: #7f8c8d; }
        .text-success { color: #27ae60; }

        /* Print Optimization */
        @page {
            margin: 10mm;
            size: A4 portrait;
        }

        @media print {
            .invoice-container {
                padding: 0;
                margin: 0;
                max-width: none;
            }

            body {
                font-size: 10px;
            }

            .items-table tbody tr:hover {
                background-color: transparent;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="company-info">
                    <div class="company-name">BUSINESSCONNECT S.A.S.</div>
                    <div class="company-details">
                        <p><strong>Nombre Comercial:</strong> BUSINESSCONNECT</p>
                        <p><strong>RUC:</strong> 1793204144001</p>
                        <p><strong>Dirección:</strong> RAMIREZ DAVALOS Y AV. AMAZONAS EDIFICIO CENTRO AMAZONAS OF. 402</p>
                        <p><strong>Teléfono:</strong> 0962966301</p>
                        <p><strong>Email:</strong> contacto@comersia.app</p>
                        <p><strong>Web:</strong> https://www.comersia.app/</p>
                    </div>
                </div>

                <div class="invoice-title">
                    <h1>FACTURA</h1>
                    <div class="invoice-meta">
                        <p><strong>No. {{ $invoice->invoice_number }}</strong></p>
                        <p><strong>Fecha:</strong> {{ $invoice->issue_date->format('d/m/Y') }}</p>
                        <p><strong>Estado:</strong> {{ $invoice->status === 'AUTHORIZED' ? 'Autorizada' : 'Pendiente' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer and Invoice Information -->
        <div class="info-section">
            <div class="customer-info">
                <div class="info-block">
                    <h3>Datos del Cliente</h3>
                    <p><strong>Nombre:</strong> {{ $invoice->customer_name }}</p>
                    <p><strong>{{ $invoice->getCustomerIdentificationType() === '05' ? 'Cédula' : 'RUC' }}:</strong> {{ $invoice->customer_identification }}</p>
                    <p><strong>Email:</strong> {{ $invoice->customer_email }}</p>
                    <p><strong>Dirección:</strong> {{ $invoice->customer_address }}</p>
                    @if($invoice->customer_phone)
                        <p><strong>Teléfono:</strong> {{ $invoice->customer_phone }}</p>
                    @endif
                </div>
            </div>

            <div class="invoice-details">
                <div class="info-block">
                    <h3>Detalles de la Transacción</h3>
                    <p><strong>No. Orden:</strong> {{ $order->order_number }}</p>
                    <p><strong>Fecha Orden:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
                    <p><strong>Método de Pago:</strong> {{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}</p>
                    <p><strong>Estado Pago:</strong> {{ $order->payment_status === 'completed' ? 'Completado' : 'Pendiente' }}</p>
                    <p><strong>Vendedor:</strong> {{ $order->seller_name ?? 'BUSINESSCONNECT' }}</p>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%">N°</th>
                    <th style="width: 47%">Descripción</th>
                    <th style="width: 10%">Cant.</th>
                    <th style="width: 15%">Precio Unit.</th>
                    <th style="width: 20%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if($item->product_sku)
                            <div class="item-sku">Código: {{ $item->product_sku }}</div>
                        @endif
                        @if($item->volume_discount_percentage > 0)
                            <div class="discount-note">
                                ✓ Descuento por volumen aplicado: {{ number_format($item->volume_discount_percentage, 1) }}%
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">${{ number_format($item->price, 2) }}</td>
                    <td class="text-right">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section clearfix">
            <div class="totals-wrapper">
                <table class="totals">
                    @if($order->total_discounts > 0)
                    <tr class="discount-row">
                        <td>Descuentos Aplicados</td>
                        <td>-${{ number_format($order->total_discounts, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>Subtotal (sin IVA)</td>
                        <td>${{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                    @if($order->shipping_cost > 0)
                    <tr>
                        <td>Costo de Envío</td>
                        <td>${{ number_format($order->shipping_cost, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>IVA (15%)</td>
                        <td>${{ number_format($invoice->tax_amount, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL A PAGAR</td>
                        <td>${{ number_format($invoice->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- SRI Authorization Status (if authorized) -->
        @if($invoice->status === 'AUTHORIZED')
        <div style="text-align: center; margin-bottom: 15px; font-size: 11px;">
            <p style="color: #27ae60; font-weight: bold;">
                FACTURA AUTORIZADA POR EL SRI
            </p>
            <p style="color: #555555; font-size: 9px;">
                Fecha de autorización: {{ $invoice->sri_authorized_at ? $invoice->sri_authorized_at->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s') }}
            </p>
        </div>
        @endif

        <!-- Additional Notes -->
        @if($order->total_discounts > 0 || collect($items)->sum('volume_discount_percentage') > 0)
        <div style="margin-bottom: 15px; padding: 8px; background: #f8f9fa; border-left: 3px solid #27ae60; font-size: 9px;">
            <p><strong>Información sobre Descuentos:</strong></p>
            @if($order->total_discounts > 0)
                <p>• Se aplicaron descuentos por un total de ${{ number_format($order->total_discounts, 2) }}</p>
            @endif
            @if(collect($items)->sum('volume_discount_percentage') > 0)
                <p>• Descuentos por volumen aplicados según la cantidad de productos adquiridos</p>
            @endif
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">
                <img src="{{ storage_path('app/public/invoices/logobig.png') }}" alt="Powered by Comersia">
            </div>
            <p class="footer-title">Comersia App - BUSINESSCONNECT S.A.S. - Plataforma de Comercio Electrónico</p>
            <p>Este documento constituye una factura electrónica válida conforme a la normativa ecuatoriana vigente</p>
            <p>Documento generado automáticamente el {{ $generatedAt->format('d/m/Y H:i:s') }}</p>
            <p>RUC: 1793204144001 | Dirección: RAMIREZ DAVALOS Y AV. AMAZONAS EDIFICIO CENTRO AMAZONAS OF. 402 | Tel: 0962966301</p>
            <p style="margin-top: 5px; font-size: 8px; color: #999;">Powered by Comersia - https://www.comersia.app/</p>
            @if($invoice->status !== 'AUTHORIZED')
            <p style="margin-top: 8px; color: #d63031; font-weight: bold;">
                IMPORTANTE: Esta factura está pendiente de autorización por parte del SRI
            </p>
            @endif
        </div>
    </div>
</body>
</html>