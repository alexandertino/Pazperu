<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Salidas</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #2c3e50;
            background-color: #ffffff;
            margin: 20px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        thead {
            background-color: #B22222;
            color: black;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px 8px;
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 30px;
            color: #888;
        }
    </style>
</head>
<body>
    <h2>📋 Listado de Salidas de Producto</h2>

    <table>
        <thead>
            <tr>
                <th>N° Acta</th>
                <th>Nombre</th>
                <th>Lugar</th>
                <th>Distrito</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($Salidas as $salida)
                <tr>
                    <td>{{ $salida->numero_acta }}</td>
                    <td>{{ $salida->nombre }}</td>
                    <td>{{ $salida->lugar }}</td>
                    <td>{{ $salida->distrito }}</td>
                    <td>{{ \Carbon\Carbon::parse($salida->fecha)->format('Y-m-d') }}</td>
                    <td>{{ $salida->producto }}</td>
                    <td>{{ $salida->cantidad }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado automáticamente - {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
