<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $sale->invoice_prefix }}{{ $sale->invoice_number ?? $sale->document_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
            width: 210mm;
            padding: 14mm 16mm;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: 10px; color: #555; }
        .muted { color: #555; }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .top .company h1 { font-size: 18px; letter-spacing: .5px; }
        .top .company p { font-size: 11px; color: #444; margin-top: 2px; }
        .top .doc {
            text-align: right;
            border: 1px solid #1a1a1a;
            border-radius: 4px;
            padding: 8px 14px;
            min-width: 220px;
        }
        .top .doc .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #555; }
        .top .doc .number { font-size: 16px; font-weight: bold; margin: 2px 0 6px; }
        .top .doc .row { font-size: 11px; display: flex; justify-content: space-between; gap: 12px; }

        .void-banner {
            border: 2px solid #1a1a1a;
            text-align: center;
            padding: 6px;
            margin: 10px 0;
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 3px;
        }

        .parties {
            display: flex;
            gap: 16px;
            margin: 12px 0;
        }
        .parties .box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 10px;
        }
        .parties .box .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 3px; }
        .parties .box .name { font-weight: bold; font-size: 12px; }
        .parties .box p { font-size: 11px; color: #444; margin-top: 1px; }

        table.items { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.items th {
            font-size: 10px; text-transform: uppercase; letter-spacing: .3px;
            text-align: left; background: #f2f2f2; padding: 6px 6px;
            border-bottom: 1px solid #ccc;
        }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { font-size: 11.5px; padding: 6px; border-bottom: 1px solid #eee; vertical-align: top; }

        .totals-wrap { display: flex; justify-content: flex-end; margin: 6px 0 14px; }
        table.totals { width: 260px; border-collapse: collapse; }
        table.totals td { padding: 3px 4px; font-size: 12px; }
        table.totals td:last-child { text-align: right; }
        table.totals .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #1a1a1a; padding-top: 6px; }

        .payment-line { font-size: 11.5px; margin: 2px 0 12px; color: #333; }

        .fe-block {
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px 14px;
            margin-top: 10px;
        }
        .fe-block .qr svg { width: 30mm; height: 30mm; flex-shrink: 0; }
        .fe-block .info p { font-size: 10.5px; line-height: 1.5; }
        .fe-block .info .cufe { word-break: break-all; font-size: 9.5px; color: #444; }
        .fe-block .title { font-weight: bold; font-size: 11.5px; margin-bottom: 3px; }

        .legal { margin-top: 14px; font-size: 9.5px; color: #666; line-height: 1.5; }

        .footer { margin-top: 18px; font-size: 10px; text-align: center; color: #888; }

        @media print {
            body { width: auto; padding: 12mm 14mm; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

    <div class="top">
        <div class="company">
            <h1>{{ strtoupper($tenant->legal_name ?? $tenant->name) }}</h1>
            <p>NIT: {{ $tenant->nit ?? '—' }}{{ $tenant->verification_digit ? '-'.$tenant->verification_digit : '' }}</p>
            @if($tenant->address)<p>{{ $tenant->address }}{{ $tenant->city ? ', '.$tenant->city : '' }}{{ $tenant->department ? ' ('.$tenant->department.')' : '' }}</p>@endif
            @if($tenant->phone || $tenant->email)
                <p>{{ $tenant->phone }}{{ $tenant->phone && $tenant->email ? ' · ' : '' }}{{ $tenant->email }}</p>
            @endif
        </div>
        <div class="doc">
            <p class="label">{{ $sale->fe_cufe ? 'Factura electrónica de venta' : 'Comprobante de venta' }}</p>
            <p class="number">{{ $sale->invoice_prefix }}{{ $sale->invoice_number ?? $sale->document_number }}</p>
            <div class="row"><span class="muted">Fecha</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
            @if($sale->feResolution)
            <div class="row"><span class="muted">Resolución DIAN</span><span>{{ $sale->feResolution->resolution_number }}</span></div>
            @endif
        </div>
    </div>

    @if($sale->status->value === 'voided')
        <div class="void-banner">*** VENTA ANULADA ***</div>
    @endif

    <div class="parties">
        <div class="box">
            <p class="label">Cliente</p>
            @if($sale->customer)
                <p class="name">{{ $sale->customer->full_name }}</p>
                <p>{{ $sale->customer->identification_type_code }} {{ $sale->customer->identification_number }}{{ $sale->customer->verification_digit ? '-'.$sale->customer->verification_digit : '' }}</p>
                @if($sale->customer->address)<p>{{ $sale->customer->address }}</p>@endif
                @if($sale->customer->phone || $sale->customer->email)<p>{{ $sale->customer->phone }} {{ $sale->customer->email }}</p>@endif
            @else
                <p class="name">Consumidor final</p>
                <p>CC 222222222222</p>
            @endif
        </div>
        <div class="box">
            <p class="label">Venta</p>
            <p>Cajero: {{ $sale->user?->name ?? '—' }}</p>
            <p>Caja: {{ $sale->cashSession?->register?->name ?? '—' }}</p>
            <p>Forma de pago: {{ $sale->payment_form?->value === '2' ? 'Crédito' : $sale->payment_method->label() }}</p>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num">Cant.</th>
                <th class="num">Precio unit.</th>
                <th class="num">Descuento</th>
                <th class="num">IVA</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>
                    {{ $item->description }}
                    @if($item->prescription_number)
                        <br><span class="small">Rx: {{ $item->prescription_number }}</span>
                    @endif
                </td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">$ {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="num">{{ $item->discount_amount > 0 ? '-$ '.number_format($item->discount_amount, 0, ',', '.') : '—' }}</td>
                <td class="num">{{ $item->tax_rate > 0 ? '$ '.number_format($item->line_tax, 0, ',', '.') : '—' }}</td>
                <td class="num bold">$ {{ number_format($item->line_total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals">
            <tr><td>Subtotal</td><td>$ {{ number_format($sale->subtotal, 0, ',', '.') }}</td></tr>
            @if(($sale->discount_total ?? 0) > 0)
            <tr><td>Descuento</td><td>-$ {{ number_format($sale->discount_total, 0, ',', '.') }}</td></tr>
            @endif
            @if($sale->tax_total > 0)
            <tr><td>IVA</td><td>$ {{ number_format($sale->tax_total, 0, ',', '.') }}</td></tr>
            @endif
            <tr class="grand"><td>TOTAL</td><td>$ {{ number_format($sale->total, 0, ',', '.') }}</td></tr>
        </table>
    </div>

    @if($sale->amount_tendered || $sale->change_amount > 0)
    <p class="payment-line">
        @if($sale->amount_tendered)Recibido: $ {{ number_format($sale->amount_tendered, 0, ',', '.') }}@endif
        @if($sale->change_amount > 0) &nbsp;·&nbsp; Cambio: $ {{ number_format($sale->change_amount, 0, ',', '.') }}@endif
    </p>
    @endif

    @if($sale->fe_cufe)
    <div class="fe-block">
        @if($feQrSvg)
        <div class="qr">{!! $feQrSvg !!}</div>
        @endif
        <div class="info">
            <p class="title">Factura electrónica autorizada por la DIAN</p>
            <p class="cufe">CUFE: {{ $sale->fe_cufe }}</p>
            <p>Consulte la validez de este documento en:<br>https://catalogo-vpfe.dian.gov.co</p>
        </div>
    </div>
    @if($sale->feResolution)
    <p class="legal">
        Autorizada mediante Resolución DIAN No. {{ $sale->feResolution->resolution_number }}
        del {{ optional($sale->feResolution->resolution_date)->format('d/m/Y') }},
        rango autorizado {{ $sale->feResolution->prefix }}{{ $sale->feResolution->from_number }}
        al {{ $sale->feResolution->prefix }}{{ $sale->feResolution->to_number }},
        vigente hasta {{ optional($sale->feResolution->valid_until)->format('d/m/Y') }}.
        Esta representación gráfica corresponde a una Factura Electrónica de Venta.
    </p>
    @endif
    @else
    <p class="legal center" style="margin-top:14px">*** Comprobante de venta — no es factura electrónica DIAN ***</p>
    @endif

    <div class="footer">
        <p>Taguara Sync · Sistema de gestión farmacéutica</p>
        <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <script>window.onload = () => window.print();</script>
</body>
</html>
