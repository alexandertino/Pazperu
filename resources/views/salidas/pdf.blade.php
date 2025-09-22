<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Salidas - {{ $productoNombre ?? $productoCodigo }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    .header { text-align: center; margin-bottom: 8px; }
    .meta { margin-bottom: 10px; }
    .meta .row { display:flex; gap:12px; align-items:center; margin-bottom:4px; }
    .meta .label { font-weight:600; min-width:100px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #333; padding: 6px; font-size: 11px; vertical-align: top; }
    th { background: #f0f0f0; }
    .right { text-align: right; }
    .small { font-size: 10px; color: #555; }
  </style>
</head>
<body>
  <div class="header">
    <h3>Salidas del producto</h3>
    <div class="small">Generado: {{ $fecha_generado }}</div>
  </div>

  <div class="meta">
    <div class="row"><div class="label">Producto:</div><div>{{ $productoNombre ?? '—' }}</div></div>
    <div class="row"><div class="label">Código:</div><div>{{ $productoCodigo }}</div></div>
    @if(!empty($productoDescripcion))
      <div class="row"><div class="label">Descripción:</div><div>{{ $productoDescripcion }}</div></div>
    @endif
    @if(!empty($productoStock))
      <div class="row"><div class="label">Stock:</div><div>{{ $productoStock }}</div></div>
    @endif
  </div>

  <table>
    <thead>
      <tr>
        @foreach($mostrar as $col)
          <th>{{ $col['label'] }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($salidas as $s)
        <tr>
          @foreach($mostrar as $col)
            @php $c = $col['col']; @endphp
            <td>
              @if(in_array($c, ['fecha']) && !empty($s->{$c}))
                {{ \Carbon\Carbon::parse($s->{$c})->format('d/m/Y') }}
              @else
                {{ $s->{$c} ?? '' }}
              @endif
            </td>
          @endforeach
        </tr>
      @empty
        <tr>
          <td colspan="{{ count($mostrar) }}" style="text-align:center">No hay salidas para este producto</td>
        </tr>
      @endforelse

      @if($total !== null)
      <tr>
        <td colspan="{{ max(0, count($mostrar) - 1) }}" class="right"><strong>Total</strong></td>
        <td class="right"><strong>{{ $total }}</strong></td>
      </tr>
      @endif
    </tbody>
  </table>
</body>
</html>
