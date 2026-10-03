<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Productos</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #222; }
        h1 { font-size: 16pt; margin: 0 0 2pt; }
        .sub { font-size: 9pt; color: #555; margin-bottom: 10pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9pt; }
        th { background: #f1f1f1; text-align: left; }
        .num { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #999; }
        .note { font-size: 8pt; color: #555; margin-top: 8pt; }
    </style>
</head>
<body>
    @php
        $typeLabels = ['inventariable' => 'Inventariable', 'produccion' => 'Produccion', 'demanda' => 'Demanda', 'combo' => 'Combo'];
        $typeLabel = $type ? ($typeLabels[$type] ?? ucfirst($type)) : 'Todos';
    @endphp

    <h1>Reporte de Productos</h1>
    <div class="sub">
        Periodo: {{ date('d/m/Y', strtotime($from)) }} al {{ date('d/m/Y', strtotime($to)) }}
        · Tipo: {{ $typeLabel }}
        · Generado: {{ date('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Categoria</th>
                <th>Tipo</th>
                <th class="num">Vendidos</th>
                <th class="num">Ingresos (Bs)</th>
                <th class="num">Ganancia (Bs)</th>
                <th class="num">Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td>{{ $p->category_name }}</td>
                <td>{{ ucfirst($p->control_type) }}</td>
                <td class="num">{{ $p->total_sold }}</td>
                <td class="num">{{ number_format($p->revenue, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($p->profit, 2, ',', '.') }}</td>
                <td class="num">{{ $p->stock_current ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7">Sin datos para este periodo</td>
            </tr>
            @endforelse
        </tbody>
        @if(! $products->isEmpty())
        <tfoot>
            <tr>
                <td colspan="4">Totales</td>
                <td class="num">{{ number_format($totals['revenue'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totals['profit'], 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="note">Ingresos = total vendido (Bs) en el periodo, sin descontar costo. Ganancia = Ingresos − Costo.</div>
</body>
</html>