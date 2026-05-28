<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:alerts')
    ->dailyAt('07:00')
    ->withoutOverlapping();

Schedule::command('fe:retry-contingency')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('fe:alerts')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('operations:alerts')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('billing:check')
    ->dailyAt('08:00')
    ->withoutOverlapping();
