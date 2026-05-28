<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class QzKeygenCommand extends Command
{
    protected $signature = 'qz:keygen {--force : Sobreescribe las llaves existentes}';

    protected $description = 'Genera el par de llaves RSA para firmar requests de QZ Tray';

    public function handle(): int
    {
        $privatePath = storage_path('app/qz-private.pem');
        $publicPath = storage_path('app/qz-public.pem');

        if (File::exists($privatePath) && ! $this->option('force')) {
            $this->warn('Las llaves ya existen. Usa --force para regenerarlas.');

            return self::SUCCESS;
        }

        $this->info('Generando par de llaves RSA 2048...');

        $resource = openssl_pkey_new([
            'digest_alg' => 'sha512',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (! $resource) {
            $this->error('Error al generar la llave: '.openssl_error_string());

            return self::FAILURE;
        }

        // Exportar llave privada
        openssl_pkey_export($resource, $privateKeyPem);
        File::put($privatePath, $privateKeyPem);

        // Exportar llave pública (formato que QZ Tray espera)
        $details = openssl_pkey_get_details($resource);
        $publicKey = $details['key'];
        File::put($publicPath, $publicKey);

        $this->info('✓ Llave privada: '.$privatePath);
        $this->info('✓ Llave pública: '.$publicPath);
        $this->newLine();
        $this->line('<comment>Próximos pasos:</comment>');
        $this->line('  1. Asegúrate de que storage/app/ NO esté en el repositorio público.');
        $this->line('  2. Ejecuta: php artisan qz:keygen en cada entorno (staging, prod).');
        $this->line('  3. Las llaves se leen automáticamente desde storage/app/.');

        return self::SUCCESS;
    }
}
