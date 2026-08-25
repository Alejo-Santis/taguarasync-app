<?php

use App\Mail\ServerErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

test('an unhandled server error queues an alert email', function () {
    Mail::fake();
    Cache::flush();

    Route::get('/__test/throws', function () {
        throw new RuntimeException('Boom for alert test');
    });

    $this->get('/__test/throws');

    Mail::assertQueued(ServerErrorOccurred::class, fn (ServerErrorOccurred $mail) => $mail->exceptionMessage === 'Boom for alert test'
        && $mail->exceptionClass === RuntimeException::class);
});

test('a routine 404 does not queue an alert email', function () {
    Mail::fake();
    Cache::flush();

    Route::get('/__test/missing', function () {
        abort(404);
    });

    $this->get('/__test/missing');

    Mail::assertNothingQueued();
});

test('the same recurring error only queues one alert within the throttle window', function () {
    Mail::fake();
    Cache::flush();

    Route::get('/__test/throws-repeatedly', function () {
        throw new RuntimeException('Repeating boom');
    });

    $this->get('/__test/throws-repeatedly');
    $this->get('/__test/throws-repeatedly');

    Mail::assertQueuedCount(1);
});
