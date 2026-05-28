<?php

namespace App\Http\Controllers\Print;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class PrintController extends Controller
{
    /**
     * Sirve la llave pública RSA para que QZ Tray valide la identidad del servidor.
     * GET /print/certificate
     */
    public function certificate(): Response
    {
        $path = storage_path('app/qz-public.pem');

        if (! File::exists($path)) {
            abort(503, 'Certificado QZ no generado. Ejecuta: php artisan qz:keygen');
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/x-pem-file',
        ]);
    }

    /**
     * Firma el mensaje de QZ Tray con la llave privada RSA (SHA-512).
     * QZ Tray envía el mensaje como body raw (text/plain).
     * POST /print/sign
     */
    public function sign(Request $request): Response
    {
        $message = $request->getContent();

        $path = storage_path('app/qz-private.pem');

        if (! File::exists($path)) {
            abort(503, 'Llave privada QZ no generada. Ejecuta: php artisan qz:keygen');
        }

        $privateKey = openssl_pkey_get_private(File::get($path));

        if (! $privateKey) {
            abort(500, 'Error al leer la llave privada QZ.');
        }

        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        return response(base64_encode($signature), 200, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Renderiza la página de configuración de impresora.
     * GET /settings/printer
     */
    public function index(Request $request): \Inertia\Response
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $user->tenant?->printer_settings ?? [
            'printer_name' => null,
            'paper_width' => '80mm',
            'copies' => 1,
            'auto_print' => false,
        ];

        return Inertia::render('Settings/Printer/Index', [
            'printerSettings' => $settings,
        ]);
    }

    /**
     * Guarda la configuración de impresora del tenant.
     * PUT /settings/printer
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'printer_name' => ['nullable', 'string', 'max:255'],
            'paper_width' => ['required', 'in:58mm,80mm'],
            'copies' => ['required', 'integer', 'min:1', 'max:5'],
            'auto_print' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        Tenant::where('id', $user->tenant_id)->update([
            'printer_settings' => $validated,
        ]);

        return back()->with('success', 'Configuración de impresora guardada.');
    }
}
