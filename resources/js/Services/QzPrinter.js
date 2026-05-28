/**
 * QzPrinter — servicio de impresión directa vía QZ Tray.
 *
 * QZ Tray es una app de escritorio que expone un WebSocket en localhost:8182.
 * Este servicio: conecta, firma requests con el servidor Laravel, y envía
 * comandos ESC/POS a la impresora térmica configurada.
 *
 * Instalación en el cliente: https://qz.io/download/
 */
import qz from 'qz-tray';

// ── Constantes ESC/POS ────────────────────────────────────────────────────
const ESC = '\x1B';
const GS  = '\x1D';

const CMD = {
    INIT:           ESC + '@',          // Reset impresora
    ALIGN_LEFT:     ESC + 'a\x00',
    ALIGN_CENTER:   ESC + 'a\x01',
    ALIGN_RIGHT:    ESC + 'a\x02',
    BOLD_ON:        ESC + 'E\x01',
    BOLD_OFF:       ESC + 'E\x00',
    DOUBLE_HEIGHT:  ESC + '!\x10',
    NORMAL_SIZE:    ESC + '!\x00',
    UNDERLINE_ON:   ESC + '-\x01',
    UNDERLINE_OFF:  ESC + '-\x00',
    FEED:           '\x0A',             // Line feed
    CUT:            GS  + 'V\x42\x00', // Full cut
};

// ── Estado de conexión ────────────────────────────────────────────────────
let _connected  = false;
let _connecting = false;
let _listeners  = [];   // callbacks para cambios de estado

function _emit(state) {
    _listeners.forEach(fn => fn(state));
}

// ── Firmas y certificado ──────────────────────────────────────────────────
function _setupSecurity() {
    qz.security.setCertificatePromise((resolve, reject) => {
        fetch('/print/certificate', { cache: 'no-store' })
            .then(r => r.ok ? r.text() : Promise.reject('No se pudo obtener el certificado QZ'))
            .then(resolve)
            .catch(reject);
    });

    qz.security.setSignatureAlgorithm('SHA512');

    qz.security.setSignaturePromise((toSign) => (resolve, reject) => {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta?.content ?? '';

        fetch('/print/sign', {
            method:  'POST',
            headers: {
                'Content-Type':     'text/plain',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: toSign,
        })
            .then(r => r.ok ? r.text() : Promise.reject('Error al firmar el mensaje QZ'))
            .then(resolve)
            .catch(reject);
    });
}

// ── API pública ───────────────────────────────────────────────────────────
const QzPrinter = {
    /** true si QZ Tray está conectado */
    get connected() { return _connected; },

    /** Suscribirse a cambios de estado: fn({ connected, error? }) */
    onStatus(fn) {
        _listeners.push(fn);
        return () => { _listeners = _listeners.filter(l => l !== fn); };
    },

    /** Conecta al WebSocket de QZ Tray. Idempotente. */
    async connect() {
        if (_connected || _connecting) return;
        _connecting = true;

        _setupSecurity();

        qz.websocket.setClosedCallbacks(() => {
            _connected  = false;
            _connecting = false;
            _emit({ connected: false });
        });

        qz.websocket.setErrorCallbacks((err) => {
            _emit({ connected: false, error: err?.message ?? 'Error QZ' });
        });

        try {
            await qz.websocket.connect({ retries: 2, delay: 1 });
            _connected  = true;
            _connecting = false;
            _emit({ connected: true });
        } catch (err) {
            _connected  = false;
            _connecting = false;
            _emit({ connected: false, error: 'No se pudo conectar a QZ Tray. ¿Está instalado y corriendo?' });
            throw err;
        }
    },

    /** Desconecta limpiamente. */
    async disconnect() {
        if (!_connected) return;
        await qz.websocket.disconnect();
        _connected = false;
        _emit({ connected: false });
    },

    /** Devuelve la lista de impresoras disponibles en el sistema. */
    async getPrinters() {
        if (!_connected) throw new Error('QZ Tray no está conectado.');
        return qz.printers.find();
    },

    /**
     * Imprime datos raw ESC/POS en la impresora indicada.
     * Acepta items mixtos: strings (formato plain) u objetos { type, format, data }.
     * @param {string}           printerName
     * @param {Array<string|object>} data
     * @param {object}           options
     */
    async print(printerName, data, options = {}) {
        if (!_connected) throw new Error('QZ Tray no está conectado.');

        const config = qz.configs.create(printerName, {
            copies:    options.copies ?? 1,
            colorType: 'blackWhite',
            duplex:    false,
        });

        await qz.print(config, data.map(d =>
            typeof d === 'string'
                ? { type: 'raw', format: 'plain', data: d }
                : d
        ));
    },

    /**
     * Imprime el recibo de una venta en formato ESC/POS.
     * @param {string} printerName
     * @param {object} sale  - sale.fe_cufe y sale.fe_qr_url opcionales para QR FE
     * @param {object} opts  - { paperWidth: '80mm'|'58mm', copies: 1 }
     */
    async printReceipt(printerName, sale, opts = {}) {
        const data = buildReceiptEscPos(sale, opts.paperWidth ?? '80mm');
        await this.print(printerName, data, { copies: opts.copies ?? 1 });
    },

    /**
     * Imprime el Z-report (cierre de caja) en formato ESC/POS.
     * @param {string} printerName
     * @param {object} session  - datos de CashSession transformados
     * @param {object} opts     - { paperWidth, copies }
     */
    async printCashSession(printerName, session, opts = {}) {
        const data = buildCashSessionEscPos(session, opts.paperWidth ?? '80mm');
        await this.print(printerName, data, { copies: opts.copies ?? 1 });
    },
};

// ── Helpers de formato ────────────────────────────────────────────────────
function col(paperWidth) {
    return paperWidth === '58mm' ? 32 : 48;
}

function dashedLine(paperWidth) {
    return '-'.repeat(col(paperWidth)) + '\n';
}

function pad(left, right, width) {
    const spaces = width - left.length - right.length;
    return left + ' '.repeat(Math.max(1, spaces)) + right + '\n';
}

function fmt(amount) {
    return '$ ' + Number(amount ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 });
}

// ── QR nativo ESC/POS (GS ( k) ────────────────────────────────────────────
/**
 * Genera comandos ESC/POS para imprimir un QR usando el comando nativo GS ( k.
 * Devuelve un objeto { type:'raw', format:'base64', data } listo para QZ Tray.
 * @param {string} url  - Texto/URL a codificar en el QR
 * @returns {{ type: string, format: string, data: string }}
 */
function buildQrEscPos(url) {
    const dataBytes = new TextEncoder().encode(url);
    const storeLen  = dataBytes.length + 3; // 3 = cabecera 0x31 0x50 0x30
    const pL = storeLen & 0xFF;
    const pH = (storeLen >> 8) & 0xFF;

    const bytes = [
        // Alinear al centro
        0x1B, 0x61, 0x01,
        // Modelo 2
        0x1D, 0x28, 0x6B, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00,
        // Tamaño de módulo: 4 puntos
        0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x43, 0x04,
        // Corrección de errores: nivel L
        0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x45, 0x30,
        // Almacenar datos
        0x1D, 0x28, 0x6B, pL, pH, 0x31, 0x50, 0x30,
        ...dataBytes,
        // Imprimir QR
        0x1D, 0x28, 0x6B, 0x03, 0x00, 0x31, 0x51, 0x30,
        // Volver a alinear a la izquierda
        0x1B, 0x61, 0x00,
        // Línea extra
        0x0A,
    ];

    let binary = '';
    bytes.forEach(b => { binary += String.fromCharCode(b); });

    return { type: 'raw', format: 'base64', data: btoa(binary) };
}

// ── Constructor ESC/POS del recibo de venta ───────────────────────────────
/**
 * Construye el array de comandos ESC/POS para el recibo de una venta.
 *
 * Campos esperados en sale:
 *   document_number, total, subtotal, tax_total, discount_total,
 *   payment_form ('1'|'2'), payment_method, payment_due_date, change_amount,
 *   customer_name, cashier_name, created_at, status,
 *   items[]: { description, quantity, unit_price, discount_rate, discount_amount, line_total }
 *   fe_cufe (optional), fe_qr_url (optional - URL a codificar en QR nativo)
 */
function buildReceiptEscPos(sale, paperWidth = '80mm') {
    const W    = col(paperWidth);
    const DASH = dashedLine(paperWidth);
    const cmds = [];

    const p = (text) => cmds.push(text);

    // Inicializar
    p(CMD.INIT);

    // Cabecera
    p(CMD.ALIGN_CENTER);
    p(CMD.BOLD_ON + CMD.DOUBLE_HEIGHT);
    p((sale.tenant_name ?? 'FARMACIA').toUpperCase() + '\n');
    p(CMD.NORMAL_SIZE + CMD.BOLD_OFF);
    p('Farmacia · Drogueria\n');
    if (sale.nit) { p('NIT: ' + sale.nit + '\n'); }
    p('\n');

    // Separador
    p(CMD.ALIGN_LEFT);
    p(DASH);

    // Datos del documento
    p(pad('Comprobante', sale.document_number ?? '', W));
    p(pad('Fecha', sale.created_at ?? '', W));
    if (sale.customer_name) { p(pad('Cliente', sale.customer_name, W)); }
    if (sale.cashier_name)  { p(pad('Cajero',  sale.cashier_name, W));  }

    // Anulada
    if (sale.status === 'voided') {
        p('\n');
        p(CMD.ALIGN_CENTER + CMD.BOLD_ON);
        p('*** ANULADA ***\n');
        p(CMD.BOLD_OFF + CMD.ALIGN_LEFT);
    }

    p(DASH);

    // Encabezado items
    p(CMD.BOLD_ON);
    p(pad('Descripcion', 'Total', W));
    p(CMD.BOLD_OFF);
    p(DASH);

    // Items
    (sale.items ?? []).forEach(item => {
        const desc  = (item.description ?? '').substring(0, W - 12);
        const total = fmt(item.line_total);
        p(pad(desc, total, W));
        const qtyPrice = `  ${item.quantity} x ${fmt(item.unit_price)}`;
        p(qtyPrice + '\n');
        if (item.discount_amount > 0) {
            p(`  Dto ${item.discount_rate}%: -${fmt(item.discount_amount)}\n`);
        }
    });

    p(DASH);

    // Totales
    if (sale.discount_total > 0) { p(pad('Descuento', '-' + fmt(sale.discount_total), W)); }
    if (sale.tax_total > 0)      { p(pad('IVA', fmt(sale.tax_total), W)); }
    p(CMD.BOLD_ON + CMD.DOUBLE_HEIGHT);
    p(CMD.ALIGN_CENTER);
    p('TOTAL  ' + fmt(sale.total) + '\n');
    p(CMD.NORMAL_SIZE + CMD.BOLD_OFF + CMD.ALIGN_LEFT);

    p(DASH);

    // Pago
    const method = sale.payment_form === '2' ? 'Credito' : (sale.payment_method ?? 'Efectivo');
    p(pad('Forma de pago', method, W));
    if (sale.payment_form === '2' && sale.payment_due_date) {
        p(pad('Vence', sale.payment_due_date, W));
    }
    if (sale.change_amount > 0) { p(pad('Cambio', fmt(sale.change_amount), W)); }

    // Facturación electrónica
    if (sale.fe_cufe) {
        p(DASH);
        p(CMD.ALIGN_CENTER + CMD.BOLD_ON);
        p('FACTURA ELECTRONICA\n');
        p(CMD.BOLD_OFF);
        p('CUFE:\n');
        p(CMD.ALIGN_LEFT);
        // CUFE en bloques de W caracteres
        const cufe = sale.fe_cufe;
        for (let i = 0; i < cufe.length; i += W) {
            p(cufe.slice(i, i + W) + '\n');
        }
        // QR nativo si disponemos de una URL
        if (sale.fe_qr_url && sale.fe_qr_url.startsWith('http')) {
            p(CMD.ALIGN_CENTER);
            cmds.push(buildQrEscPos(sale.fe_qr_url));
        }
    }

    // Pie
    p(DASH);
    p(CMD.ALIGN_CENTER);
    p('Gracias por su compra\n');
    p('Taguara Sync\n');
    p('\n\n\n');

    // Corte
    p(CMD.CUT);

    return cmds;
}

// ── Constructor ESC/POS del Z-report (cierre de caja) ────────────────────
/**
 * Construye el array de comandos ESC/POS para el cierre de caja.
 *
 * Campos esperados en session (transformados por CashSessionReportController):
 *   register.name, cashier, opened_at, closed_at,
 *   sales_count, sales_total, cash_sales_total, card_sales_total,
 *   transfer_sales_total, opening_amount, expected_closing,
 *   actual_closing_amount, difference, notes
 */
function buildCashSessionEscPos(session, paperWidth = '80mm') {
    const W    = col(paperWidth);
    const DASH = dashedLine(paperWidth);
    const cmds = [];

    const p = (text) => cmds.push(text);

    p(CMD.INIT);
    p(CMD.ALIGN_CENTER);
    p(CMD.BOLD_ON + CMD.DOUBLE_HEIGHT);
    p('CIERRE DE CAJA\n');
    p(CMD.NORMAL_SIZE + CMD.BOLD_OFF);
    p((session.register?.name ?? 'CAJA').toUpperCase() + '\n');
    if (session.cashier) { p(session.cashier + '\n'); }
    p('\n');

    p(CMD.ALIGN_LEFT);
    p(DASH);

    p(pad('Apertura', session.opened_at ?? '', W));
    if (session.closed_at) { p(pad('Cierre', session.closed_at, W)); }

    p(DASH);
    p(CMD.BOLD_ON);
    p('RESUMEN DE VENTAS\n');
    p(CMD.BOLD_OFF);
    p(DASH);

    p(pad('Documentos', String(session.sales_count ?? 0), W));
    p(CMD.BOLD_ON);
    p(pad('Total vendido', fmt(session.sales_total), W));
    p(CMD.BOLD_OFF);
    p(DASH);
    p(pad('Efectivo', fmt(session.cash_sales_total), W));
    p(pad('Tarjeta', fmt(session.card_sales_total), W));
    p(pad('Transferencia', fmt(session.transfer_sales_total), W));

    p(DASH);
    p(CMD.BOLD_ON);
    p('ARQUEO DE CAJA\n');
    p(CMD.BOLD_OFF);
    p(DASH);

    p(pad('Saldo apertura', fmt(session.opening_amount), W));
    p(pad('Ventas efectivo', fmt(session.cash_sales_total), W));
    p(CMD.BOLD_ON);
    p(pad('Total esperado', fmt(session.expected_closing), W));
    p(CMD.BOLD_OFF);

    if (session.actual_closing_amount !== null && session.actual_closing_amount !== undefined) {
        p(pad('Total contado', fmt(session.actual_closing_amount), W));
        const diff = session.difference ?? 0;
        const diffStr = (diff >= 0 ? '+' : '') + fmt(diff);
        p(CMD.BOLD_ON);
        p(pad('Diferencia', diffStr, W));
        p(CMD.BOLD_OFF);
    }

    if (session.notes) {
        p(DASH);
        p('Notas: ' + session.notes + '\n');
    }

    p(DASH);
    p(CMD.ALIGN_CENTER);
    p('Taguara Sync\n');
    p('\n\n\n');
    p(CMD.CUT);

    return cmds;
}

export default QzPrinter;
export { buildReceiptEscPos, buildCashSessionEscPos };
