<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {--tenant-name= : Nombre de la farmacia}
        {--name=        : Nombre completo del propietario}
        {--email=       : Email del propietario}
        {--password=    : Contraseña (mínimo 8 caracteres)}';

    protected $description = 'Crea un nuevo tenant (farmacia) con su cuenta owner';

    public function handle(CreateNewUser $createNewUser): int
    {
        $tenantName = $this->option('tenant-name') ?? $this->ask('Nombre de la farmacia');
        $name = $this->option('name') ?? $this->ask('Nombre del propietario');
        $email = $this->option('email') ?? $this->ask('Email del propietario');
        $password = $this->option('password') ?? $this->secret('Contraseña (mínimo 8 caracteres)');

        try {
            $user = $createNewUser->create([
                'tenant_name' => $tenantName,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $this->info("✓ Farmacia \"{$tenantName}\" creada correctamente.");
            $this->line("  Tenant ID : {$user->tenant->uuid}");
            $this->line("  Owner     : {$user->name} <{$user->email}>");

        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->error("{$field}: ".implode(', ', $messages));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
