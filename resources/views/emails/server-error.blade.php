@component('mail::message')
# Error de servidor

Ocurrió un error no controlado en **{{ config('app.name') }}**.

@component('mail::table')
| Campo | Valor |
| :---- | :---- |
| Excepción | `{{ $exceptionClass }}` |
| Mensaje | {{ $exceptionMessage }} |
| Archivo | `{{ $file }}:{{ $line }}` |
| URL | {{ $url }} |
| Fecha | {{ $occurredAt }} |
@endcomponent

Revisa el detalle completo en `storage/logs/laravel.log`.

Gracias,<br>
{{ config('app.name') }}
@endcomponent
