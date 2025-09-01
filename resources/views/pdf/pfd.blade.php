<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        .watermark {
            position: fixed;
            top: 35%;
            left: 20%;
            opacity: 0.1;
            font-size: 100px;
            transform: rotate(-30deg);
            color: red;
            z-index: -1000;
        }
    </style>
</head>
<body>
    <div class="watermark">
        {{ now()->format('d/m/Y H:i') }} <!-- Marca de agua con fecha y hora -->
    </div>

    <h2 style="text-align:center;">Reporte de Inventarios</h2>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>U.M.</th>
                <th>Entradas</th>
                <th>Salidas</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inventarios as $item)
                <tr>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->fecha }}</td>
                    <td>{{ $item->descripcion }}</td>
                    <td>{{ $item->categoria }}</td>
                    <td>{{ $item->um }}</td>
                    <td>{{ $item->entradas }}</td>
                    <td>{{ $item->salidas }}</td>
                    <td>{{ $item->stock }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
