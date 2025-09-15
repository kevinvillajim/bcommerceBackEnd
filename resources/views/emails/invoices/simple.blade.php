<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $invoiceNumber }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #0f497d;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            color: #0f497d;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .content {
            text-align: center;
            margin-bottom: 30px;
        }
        .invoice-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .highlight {
            color: #0f497d;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Comersia</div>
            <p>BUSINESSCONNECT S.A.S.</p>
        </div>

        <div class="content">
            <h2>¡Gracias por preferirnos!</h2>
            <p>Estimado/a <span class="highlight">{{ $customerName }}</span>,</p>
            <p>Adjuntamos su factura electrónica correspondiente a su compra.</p>

            <div class="invoice-info">
                <p><strong>Factura N°:</strong> <span class="highlight">{{ $invoiceNumber }}</span></p>
                <p><strong>Total:</strong> <span class="highlight">${{ number_format($totalAmount, 2) }}</span></p>
            </div>

            <p>Su factura ha sido autorizada por el SRI y tiene validez legal completa.</p>
        </div>

        <div class="footer">
            <p><strong>Comersia Team</strong></p>
            <p>BUSINESSCONNECT S.A.S.</p>
            <p>Email: contacto@comersia.app | Web: https://www.comersia.app/</p>
            <p>RUC: 1793204144001</p>
        </div>
    </div>
</body>
</html>