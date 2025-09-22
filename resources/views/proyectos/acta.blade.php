<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta {{ $acta['n_acta'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .info {
            margin-bottom: 20px;
        }
        .info table {
            width: 100%;
            border-collapse: collapse;
        }
        .info td {
            padding: 5px;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items th, .items td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        .items th {
            background: #f0f0f0;
        }
        .footer {
            margin-top: 40px;
            text-align: right;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Acta de Entrega</h1>
        <p><strong>N° Acta:</strong> {{ $acta['n_acta'] }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td><strong>Nombre:</strong> {{ $acta['nombre'] }}</td>
                <td><strong>Fecha:</strong> {{ $acta['fecha'] }}</td>
            </tr>
            <tr>
                <td><strong>Lugar:</strong> {{ $acta['lugar'] }}</td>
                <td><strong>Distrito:</strong> {{ $acta['distrito'] }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($acta['items'] as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item['producto_label'] ?? $item['producto'] }}</td>
                    <td>{{ $item['um'] }}</td>
                    <td>{{ $item['cantidad'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generado automáticamente el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
