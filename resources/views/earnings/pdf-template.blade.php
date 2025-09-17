<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ganancias - {{ $seller->store_name ?? $seller->user->name }}</title>
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

        .report-container {
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

        .company-details {
            font-size: 10px;
            color: #555555;
            line-height: 1.4;
        }

        .company-details strong {
            color: #0f497d;
        }

        .report-title {
            display: table-cell;
            width: 35%;
            vertical-align: top;
            text-align: right;
        }

        .report-title h1 {
            font-size: 20px;
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .report-meta {
            font-size: 10px;
            color: #555555;
            line-height: 1.4;
        }

        .report-meta strong {
            color: #0f497d;
        }

        /* Seller Info */
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .seller-info, .period-info {
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

        /* Metrics Cards */
        .metrics-section {
            margin-bottom: 20px;
        }

        .metrics-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .metric-card {
            display: table-cell;
            width: 25%;
            padding: 8px;
        }

        .metric-card-content {
            border: 1px solid #0f497d;
            padding: 10px;
            text-align: center;
            background: #f8f9fa;
        }

        .metric-value {
            font-size: 14px;
            font-weight: bold;
            color: #0f497d;
            margin-bottom: 4px;
        }

        .metric-label {
            font-size: 9px;
            color: #555555;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .metric-growth {
            font-size: 8px;
            margin-top: 2px;
        }

        .growth-positive {
            color: #27ae60;
        }

        .growth-negative {
            color: #e74c3c;
        }

        /* Monthly Table */
        .monthly-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
            border: 1px solid #0f497d;
        }

        .monthly-table th {
            background: #0f497d;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            border-right: 1px solid #34495e;
        }

        .monthly-table th:last-child {
            border-right: none;
        }

        .monthly-table th:last-child,
        .monthly-table td:last-child {
            text-align: right;
        }

        .monthly-table td {
            padding: 6px 6px;
            border-bottom: 1px solid #d5d5d5;
            border-right: 1px solid #d5d5d5;
            vertical-align: top;
        }

        .monthly-table td:last-child {
            border-right: none;
        }

        .monthly-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .month-name {
            font-weight: bold;
            color: #0f497d;
        }

        .amount {
            text-align: right;
            font-weight: bold;
        }

        .amount.positive {
            color: #27ae60;
        }

        .amount.commission {
            color: #e74c3c;
        }

        /* Summary Section */
        .summary-section {
            margin-bottom: 20px;
        }

        .summary-wrapper {
            float: right;
            width: 350px;
        }

        .summary {
            width: 100%;
            font-size: 10px;
            border: 1px solid #0f497d;
        }

        .summary td {
            padding: 4px 8px;
            border-bottom: 1px solid #d5d5d5;
        }

        .summary td:first-child {
            background: #ecf0f1;
            font-weight: bold;
            color: #0f497d;
            width: 60%;
        }

        .summary td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .summary .total-row td {
            background: #0f497d;
            color: white;
            font-weight: bold;
            font-size: 11px;
            border-bottom: none;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
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

        .footer-logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .footer-logo img {
            width: 150px;
            height: auto;
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
            .report-container {
                padding: 0;
                margin: 0;
                max-width: none;
            }

            body {
                font-size: 10px;
            }

            .monthly-table tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
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

                <div class="report-title">
                    <h1>REPORTE DE GANANCIAS</h1>
                    <div class="report-meta">
                        <p><strong>Vendedor:</strong> {{ $seller->store_name ?? $seller->user->name }}</p>
                        <p><strong>Período:</strong> {{ $period['formatted_start'] }} - {{ $period['formatted_end'] }}</p>
                        <p><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</p>
                        <p><strong>Comisión:</strong> {{ number_format($commissionRate, 1) }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seller and Period Information -->
        <div class="info-section">
            <div class="seller-info">
                <div class="info-block">
                    <h3>Datos del Vendedor</h3>
                    <p><strong>Nombre de la Tienda:</strong> {{ $seller->store_name ?? 'N/A' }}</p>
                    <p><strong>Propietario:</strong> {{ $seller->user->name }}</p>
                    <p><strong>Email:</strong> {{ $seller->user->email }}</p>
                    <p><strong>Teléfono:</strong> {{ $seller->phone ?? 'N/A' }}</p>
                    <p><strong>Dirección:</strong> {{ $seller->address ?? 'N/A' }}</p>
                    <p><strong>Estado:</strong> {{ $seller->status === 'active' ? 'Activo' : 'Inactivo' }}</p>
                </div>
            </div>

            <div class="period-info">
                <div class="info-block">
                    <h3>Resumen del Período</h3>
                    <p><strong>Fecha Inicio:</strong> {{ $period['formatted_start'] }}</p>
                    <p><strong>Fecha Fin:</strong> {{ $period['formatted_end'] }}</p>
                    <p><strong>Total Días:</strong> {{ \Carbon\Carbon::parse($period['start_date'])->diffInDays(\Carbon\Carbon::parse($period['end_date'])) + 1 }}</p>
                    <p><strong>Comisión Plataforma:</strong> {{ number_format($commissionRate, 1) }}%</p>
                    <p><strong>Moneda:</strong> USD</p>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-section">
            <h3 style="color: #0f497d; margin-bottom: 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Métricas Principales</h3>
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-card-content">
                        <div class="metric-value">${{ number_format($earningsData['total_earnings'], 2) }}</div>
                        <div class="metric-label">Total Ganancias</div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-card-content">
                        <div class="metric-value">${{ number_format($earningsData['sales_this_month'], 2) }}</div>
                        <div class="metric-label">Ventas Este Mes</div>
                        @if($earningsData['sales_growth'] != 0)
                            <div class="metric-growth {{ $earningsData['sales_growth'] > 0 ? 'growth-positive' : 'growth-negative' }}">
                                {{ $earningsData['sales_growth'] > 0 ? '+' : '' }}{{ number_format($earningsData['sales_growth'], 1) }}%
                            </div>
                        @endif
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-card-content">
                        <div class="metric-value">${{ number_format($earningsData['commissions_this_month'], 2) }}</div>
                        <div class="metric-label">Comisiones Este Mes</div>
                        <div class="metric-growth">{{ number_format($commissionRate, 1) }}% comisión</div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-card-content">
                        <div class="metric-value">${{ number_format($earningsData['net_earnings_this_month'], 2) }}</div>
                        <div class="metric-label">Neto Este Mes</div>
                        @if($earningsData['earnings_growth'] != 0)
                            <div class="metric-growth {{ $earningsData['earnings_growth'] > 0 ? 'growth-positive' : 'growth-negative' }}">
                                {{ $earningsData['earnings_growth'] > 0 ? '+' : '' }}{{ number_format($earningsData['earnings_growth'], 1) }}%
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Breakdown Table -->
        <h3 style="color: #0f497d; margin-bottom: 10px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Desglose Mensual (Últimos 12 Meses)</h3>
        <table class="monthly-table">
            <thead>
                <tr>
                    <th style="width: 15%">Mes</th>
                    <th style="width: 20%">Ventas</th>
                    <th style="width: 20%">Comisiones</th>
                    <th style="width: 20%">Ganancias Netas</th>
                    <th style="width: 15%">Órdenes</th>
                    <th style="width: 10%">Promedio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyData as $month)
                <tr>
                    <td class="month-name">{{ $month['month'] }}</td>
                    <td class="amount">${{ number_format($month['sales'], 2) }}</td>
                    <td class="amount commission">${{ number_format($month['commissions'], 2) }}</td>
                    <td class="amount positive">${{ number_format($month['net'], 2) }}</td>
                    <td class="text-center">{{ $month['orders_count'] }}</td>
                    <td class="amount">
                        @if($month['orders_count'] > 0)
                            ${{ number_format($month['sales'] / $month['orders_count'], 2) }}
                        @else
                            $0.00
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background: #f8f9fa;">
                <tr>
                    <td class="font-weight-bold">TOTAL</td>
                    <td class="amount font-weight-bold">${{ number_format(collect($monthlyData)->sum('sales'), 2) }}</td>
                    <td class="amount font-weight-bold commission">${{ number_format(collect($monthlyData)->sum('commissions'), 2) }}</td>
                    <td class="amount font-weight-bold positive">${{ number_format(collect($monthlyData)->sum('net'), 2) }}</td>
                    <td class="text-center font-weight-bold">{{ collect($monthlyData)->sum('orders_count') }}</td>
                    <td class="amount font-weight-bold">
                        @php
                            $totalOrders = collect($monthlyData)->sum('orders_count');
                            $totalSales = collect($monthlyData)->sum('sales');
                        @endphp
                        @if($totalOrders > 0)
                            ${{ number_format($totalSales / $totalOrders, 2) }}
                        @else
                            $0.00
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Summary Totals -->
        <div class="summary-section clearfix">
            <div class="summary-wrapper">
                <table class="summary">
                    <tr>
                        <td>Total Ventas Históricas</td>
                        <td>${{ number_format($earningsData['total_sales_all_time'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Comisiones Pagadas</td>
                        <td>${{ number_format($earningsData['total_commissions_all_time'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Pagos Pendientes</td>
                        <td>${{ number_format($earningsData['pending_payments'], 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL GANANCIAS NETAS</td>
                        <td>${{ number_format($earningsData['total_earnings'], 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Additional Information -->
        <div style="margin-bottom: 15px; padding: 8px; background: #f8f9fa; border-left: 3px solid #0f497d; font-size: 9px;">
            <p><strong>Información Importante:</strong></p>
            <p>• Las comisiones se aplican sobre el total de ventas completadas</p>
            <p>• Los pagos pendientes incluyen órdenes completadas pero no liquidadas</p>
            <p>• Las ganancias incluyen distribución de costos de envío según política de la plataforma</p>
            <p>• Todos los montos están expresados en dólares estadounidenses (USD)</p>
            <p>• El crecimiento se calcula comparando el mes actual con el mes anterior</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-logo">
                <img src="{{ storage_path('app/public/invoices/logobig.png') }}" alt="Powered by Comersia">
            </div>
            <p class="footer-title">Comersia App - BUSINESSCONNECT S.A.S. - Plataforma de Comercio Electrónico</p>
            <p>Reporte de ganancias generado automáticamente por el sistema de gestión de sellers</p>
            <p>Documento generado el {{ $generatedAt->format('d/m/Y H:i:s') }}</p>
            <p>RUC: 1793204144001 | Dirección: RAMIREZ DAVALOS Y AV. AMAZONAS EDIFICIO CENTRO AMAZONAS OF. 402 | Tel: 0962966301</p>
            <p style="margin-top: 5px; font-size: 8px; color: #999;">Powered by Comersia - https://www.comersia.app/</p>
        </div>
    </div>
</body>
</html>