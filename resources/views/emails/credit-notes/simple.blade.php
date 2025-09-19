<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota de Crédito {{ $creditNoteNumber }}</title>
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
            border-bottom: 3px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            color: #dc3545;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .content {
            text-align: center;
            margin-bottom: 30px;
        }
        .credit-note-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .highlight {
            color: #dc3545;
            font-weight: bold;
        }
        .modified-document {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
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
            <h2>Nota de Crédito Autorizada</h2>
            <p>Estimado/a <span class="highlight">{{ $customerName }}</span>,</p>
            <p>Adjuntamos su nota de crédito electrónica autorizada por el SRI.</p>

            <div class="credit-note-info">
                <p><strong>Nota de Crédito N°:</strong> <span class="highlight">{{ $creditNoteNumber }}</span></p>
                <p><strong>Total:</strong> <span class="highlight">${{ number_format($totalAmount, 2) }}</span></p>
                <p><strong>Motivo:</strong> {{ $motivo }}</p>
            </div>

            <div class="modified-document">
                <p><strong>Documento Modificado:</strong></p>
                <p>Factura N°: <span class="highlight">{{ $documentoModificado }}</span></p>
            </div>

            <p>Esta nota de crédito ha sido procesada y autorizada por el SRI, y tiene validez legal completa.</p>
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