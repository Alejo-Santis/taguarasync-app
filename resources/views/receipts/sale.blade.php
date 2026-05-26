<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo {{ $sale->document_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            width: 80mm;
            padding: 4mm 3mm;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: 10px; }
        .large { font-size: 15px; }

        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        .separator-solid { border-top: 1px solid #000; margin: 4px 0; }

        .header { margin-bottom: 6px; }
        .header h1 { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .header p { font-size: 10px; margin-top: 2px; color: #333; }

        .meta { margin: 4px 0; font-size: 10px; }
        .meta td { padding: 1px 0; }
        .meta td:last-child { text-align: right; }
        .meta { width: 100%; border-collapse: collapse; }

        .items { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .items th { font-size: 10px; text-align: left; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .items td { font-size: 11px; padding: 2px 0; vertical-align: top; }
        .items td.qty { width: 22px; text-align: center; }
        .items td.price { width: 34px; text-align: right; }
        .items td.total { width: 38px; text-align: right; font-weight: bold; }

        .totals { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .totals td { padding: 1px 0; font-size: 11px; }
        .totals td:last-child { text-align: right; }
        .totals .grand { font-size: 14px; font-weight: bold; }

        .payment { margin: 4px 0; font-size: 11px; }

        .footer { margin-top: 8px; font-size: 10px; text-align: center; color: #555; }

        .void-banner {
            border: 2px solid #000;
            text-align: center;
            padding: 4px;
            margin: 6px 0;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 2px;
        }

        .fe-section { margin: 6px 0; font-size: 10px; }
        .fe-section .fe-title { font-weight: bold; font-size: 10px; letter-spacing: 1px; margin-bottom: 2px; }
        .fe-cufe { font-size: 8px; word-break: break-all; color: #333; line-height: 1.3; }
        .fe-qr { text-align: center; margin: 4px 0; }
        .fe-qr img { width: 28mm; height: 28mm; }

        @media print {
            body { width: 80mm; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>

    <!-- Cabecera -->
    <div class="header center">
        <h1>{{ strtoupper($tenantName) }}</h1>
        <p>Farmacia · Drogueria</p>
        <p>NIT: {{ $sale->user?->tenant?->nit ?? '---' }}</p>
    </div>

    <div class="separator-solid"></div>

    <!-- Datos del documento -->
    <table class="meta">
        <tr>
            <td>Comprobante</td>
            <td class="bold">
                @if($sale->invoice_prefix)
                    {{ $sale->invoice_prefix }}{{ $sale->document_number }}
                @else
                    {{ $sale->document_number }}
                @endif
            </td>
        </tr>
        <tr>
            <td>Fecha</td>
            <td>{{ $sale->created_at->format('d/m/Y H:i:s') }}</td>
        </tr>
        @if($sale->customer)
        <tr>
            <td>Cliente</td>
            <td>{{ $sale->customer->full_name }}</td>
        </tr>
        <tr>
            <td>Doc.</td>
            <td>{{ $sale->customer->identification_type_code }} {{ $sale->customer->identification_number }}{{ $sale->customer->verification_digit ? '-'.$sale->customer->verification_digit : '' }}</td>
        </tr>
        @endif
        <tr>
            <td>Cajero</td>
            <td>{{ $sale->user?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td>Caja</td>
            <td>{{ $sale->cashSession?->register?->name ?? '—' }}</td>
        </tr>
    </table>

    @if($sale->status->value === 'voided')
        <div class="void-banner">*** ANULADA ***</div>
    @endif

    <div class="separator"></div>

    <!-- Encabezado de items -->
    <table class="items">
        <thead>
            <tr>
                <th>Descripcion</th>
                <th class="qty">Cant</th>
                <th class="price">Precio</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="qty">{{ $item->quantity }}</td>
                <td class="price">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="total">{{ number_format($item->line_total, 0, ',', '.') }}</td>
            </tr>
            @if($item->discount_amount > 0)
            <tr>
                <td class="small" style="padding-left:4px; color:#555">
                    Dto {{ number_format($item->discount_rate, 0) }}%: -{{ number_format($item->discount_amount, 0, ',', '.') }}
                </td>
                <td colspan="3"></td>
            </tr>
            @endif
            @if($item->tax_rate > 0)
            <tr>
                <td class="small" style="padding-left:4px; color:#555">
                    IVA {{ number_format($item->tax_rate, 0) }}%: {{ number_format($item->line_tax, 0, ',', '.') }}
                </td>
                <td colspan="3"></td>
            </tr>
            @endif
            @if($item->prescription_number)
            <tr>
                <td class="small" style="padding-left:4px; color:#555" colspan="4">
                    Rx: {{ $item->prescription_number }}
                    @if($item->patient_id_number) · Doc: {{ $item->patient_id_number }} @endif
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <div class="separator"></div>

    <!-- Totales -->
    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>$ {{ number_format($sale->subtotal, 0, ',', '.') }}</td>
        </tr>
        @if(($sale->discount_total ?? 0) > 0)
        <tr>
            <td>Descuento</td>
            <td>-$ {{ number_format($sale->discount_total, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if($sale->tax_total > 0)
        <tr>
            <td>IVA</td>
            <td>$ {{ number_format($sale->tax_total, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td>TOTAL</td>
            <td>$ {{ number_format($sale->total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="separator"></div>

    <!-- Pago -->
    <div class="payment">
        <table class="meta">
            @if($sale->payments->count() > 1)
                @foreach($sale->payments as $payment)
                <tr>
                    <td>{{ $payment->paymentMethod?->name ?? $sale->payment_method->label() }}</td>
                    <td class="bold">$ {{ number_format($payment->amount, 0, ',', '.') }}</td>
                </tr>
                @if($payment->amount_tendered && $payment->amount_tendered > $payment->amount)
                <tr>
                    <td class="small" style="padding-left:4px">Recibido</td>
                    <td class="small">$ {{ number_format($payment->amount_tendered, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="small" style="padding-left:4px">Cambio</td>
                    <td class="small bold">$ {{ number_format($payment->amount_tendered - $payment->amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($payment->reference)
                <tr>
                    <td class="small" style="padding-left:4px">Ref.</td>
                    <td class="small">{{ $payment->reference }}</td>
                </tr>
                @endif
                @endforeach
            @else
                <tr>
                    <td>Metodo de pago</td>
                    <td class="bold">{{ $sale->payment_method->label() }}</td>
                </tr>
                @if($sale->amount_tendered)
                <tr>
                    <td>Recibido</td>
                    <td>$ {{ number_format($sale->amount_tendered, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($sale->change_amount > 0)
                <tr>
                    <td>Cambio</td>
                    <td class="bold">$ {{ number_format($sale->change_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
            @endif
        </table>
    </div>

    @if($sale->fe_cufe)
    <div class="separator"></div>
    <div class="fe-section">
        <p class="fe-title center">FACTURA ELECTRONICA DIAN</p>
        @if($sale->fe_qr_code)
        <div class="fe-qr">
            <img src="{{ $sale->fe_qr_code }}" alt="QR DIAN" />
        </div>
        @endif
        <p class="fe-cufe">CUFE: {{ $sale->fe_cufe }}</p>
        <p class="small center" style="margin-top:2px">Consulte en: https://catalogo-vpfe.dian.gov.co</p>
    </div>
    @endif

    <div class="separator-solid"></div>

    <!-- Pie de pagina -->
    <div class="footer">
        <p>Gracias por su compra</p>
        <p>Taguara Sync · Sistema de gestion farmaceutica</p>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <script>window.onload = () => window.print();</script>
</body>
</html>
