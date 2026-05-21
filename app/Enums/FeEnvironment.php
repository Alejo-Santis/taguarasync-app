<?php

namespace App\Enums;

enum FeEnvironment: string
{
    case Test = 'test';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Pruebas',
            self::Production => 'Producción',
        };
    }
}
