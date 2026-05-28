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
    DASHED_LINE:    '--------------------------------\n', // 32 chars = 58mm; ajusta abajo
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
     * @param {string}   printerName  - Nombre exacto de la impresora
     * @param {string[]} data         - Array de comandos/texto ESC/POS
     * @param {object}   options      - Opciones adicionales de QZ
     */
    async print(printerName, data, options = {}) {
        if (!_connected) throw new Error('QZ Tray no está conectado.');

        const config = qz.configs.create(printerName, {
            copies:       options.copies       ?? 1,
            colorType:    'blackWhite',
            duplex:       false,
            ...options,
        });

        await qz.print(config, data.map(d => ({ type: 'raw', format: 'plain', data: d })));
    },

    /**
     * Imprime el recibo de una venta en formato ESC/POS.
     * @param {string} printerName
     * @param {object} sale         - Datos de la venta (viene de `completedSale` del POS)
     * @param {object} opts         - { paperWidth: '80mm'|'58mm', copies: 1 }
     */
    async printReceipt(printerName, sale, opts = {}) {
        const data = buildReceiptEscPos(sale, opts.paperWidth ?? '80mm');
        await this.print(printerName, data, { copies: opts.copies ?? 1 });
    },
};

// ── Constructor ESC/POS del recibo ────────────────────────────────────────
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
    return '$ ' + Number(amount ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 });
}

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
    if (sale.nit) p('NIT: ' + sale.nit + '\n');
    p('\n');

    // Separador
    p(CMD.ALIGN_LEFT);
    p(DASH);

    // Datos del documento
    p(pad('Comprobante', sale.document_number ?? '', W));
    p(pad('Fecha', sale.created_at ?? '', W));
    if (sale.customer_name) {
        p(pad('Cliente', sale.customer_name, W));
    }
    if (sale.cashier_name) {
        p(pad('Cajero', sale.cashier_name, W));
    }

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
    if (sale.discount_total > 0) {
        p(pad('Descuento', '-' + fmt(sale.discount_total), W));
    }
    if (sale.tax_total > 0) {
        p(pad('IVA', fmt(sale.tax_total), W));
    }
    p(CMD.BOLD_ON + CMD.DOUBLE_HEIGHT);
    p(CMD.ALIGN_CENTER);
    p('TOTAL  ' + fmt(sale.total) + '\n');
    p(CMD.NORMAL_SIZE + CMD.BOLD_OFF + CMD.ALIGN_LEFT);

    p(DASH);

    // Pago
    const method = sale.payment_form === '2' ? 'Crédito' : (sale.payment_method ?? 'Efectivo');
    p(pad('Forma de pago', method, W));
    if (sale.payment_form === '2' && sale.payment_due_date) {
        p(pad('Vence', sale.payment_due_date, W));
    }
    if (sale.change_amount > 0) {
        p(pad('Cambio', fmt(sale.change_amount), W));
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

export default QzPrinter;
export { buildReceiptEscPos };
