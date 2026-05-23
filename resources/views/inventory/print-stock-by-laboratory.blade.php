<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Existencias por laboratorio</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12mm;
            color: #111;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .title { font-size: 18px; font-weight: 700; margin: 0 0 4px; }
        .muted { color: #555; }
        .small { font-size: 10px; }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin: 8px 0 10px;
        }
        .summary div {
            border: 1px solid #bbb;
            padding: 5px 6px;
        }
        .summary strong { display: block; font-size: 13px; }
        .lab {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-top: 12px;
        }
        .lab + .lab {
            break-before: page;
            page-break-before: always;
        }
        .lab-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 12px;
            margin-bottom: 6px;
        }
        .lab-title { font-size: 14px; font-weight: 700; margin: 0; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #999;
            padding: 4px;
            vertical-align: top;
        }
        th {
            background: #f1f1f1;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .product { font-weight: 700; }
        .count-cell { height: 20px; min-width: 56px; }
        .signature {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 16px;
        }
        .signature div {
            border-top: 1px solid #111;
            padding-top: 4px;
            min-height: 24px;
        }
        @media print {
            body { padding: 8mm; }
            @page { size: A4 portrait; margin: 8mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()" style="position:fixed;right:12px;top:12px;padding:8px 12px">
        Imprimir
    </button>

    <header class="header">
        <div>
            <h1 class="title">Planilla de existencias por laboratorio</h1>
            <div class="muted">
                {{ $tenant?->name ?? 'Farmacia' }}
                @if($tenant?->nit)
                    · NIT {{ $tenant->nit }}
                @endif
            </div>
            <div class="small muted">
                {{ $laboratory ? 'Laboratorio: '.$laboratory->name : 'Todos los laboratorios' }}
                · {{ $includeZero ? 'Incluye lotes en cero' : 'Solo lotes con existencia' }}
            </div>
        </div>
        <div class="right small">
            <strong>Fecha de impresión</strong><br>
            {{ $printedAt->format('d/m/Y H:i') }}<br>
            <span class="muted">Conteo físico semanal / periódico</span>
        </div>
    </header>

    @forelse($groups as $group)
        <section class="lab">
            <div class="lab-head">
                <div>
                    <h2 class="lab-title">{{ $group['laboratory']['name'] }}</h2>
                    <div class="small muted">NIT: {{ $group['laboratory']['nit'] ?? '—' }}</div>
                </div>
                <div class="right small">
                    <strong>{{ number_format($group['total_units'], 0, ',', '.') }}</strong> unidades sistema<br>
                    <span class="muted">$ {{ number_format($group['total_value'], 0, ',', '.') }} costo estimado</span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 10%">Código</th>
                        <th>Producto</th>
                        <th style="width: 10%">Lote</th>
                        <th style="width: 9%">Vence</th>
                        <th style="width: 9%">Present.</th>
                        <th style="width: 8%" class="right">Sistema</th>
                        <th style="width: 8%" class="right">Mín.</th>
                        <th style="width: 11%" class="center">Conteo físico</th>
                        <th style="width: 10%" class="center">Diferencia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['lots'] as $lot)
                        <tr>
                            <td>
                                {{ $lot['internal_code'] ?? '—' }}
                                @if($lot['barcode'])
                                    <div class="small muted">{{ $lot['barcode'] }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="product">{{ $lot['product'] }}</div>
                                <div class="small muted">
                                    {{ collect([$lot['generic_name'], $lot['concentration'], $lot['form']])->filter()->join(' · ') ?: '—' }}
                                </div>
                            </td>
                            <td>{{ $lot['lot_number'] }}</td>
                            <td>{{ $lot['expires_on'] }}</td>
                            <td>{{ $lot['presentation'] ?? '—' }}</td>
                            <td class="right">{{ number_format($lot['current_quantity'], 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($lot['minimum_stock'] ?? 0, 0, ',', '.') }}</td>
                            <td class="count-cell"></td>
                            <td class="count-cell"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="signature">
                <div>Contado por</div>
                <div>Revisado por</div>
                <div>Fecha / hora cierre conteo</div>
            </div>
        </section>
    @empty
        <p>No hay existencias para los filtros seleccionados.</p>
    @endforelse

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
