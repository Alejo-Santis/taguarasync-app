<?php

use App\Support\Sanitize;

test('identification strips spaces, dots, and dashes', function () {
    expect(Sanitize::identification('900.123.456-7'))->toBe('9001234567')
        ->and(Sanitize::identification('79 123 456'))->toBe('79123456')
        ->and(Sanitize::identification(' 900123456 '))->toBe('900123456');
});

test('identification preserves letters for alphanumeric ids', function () {
    expect(Sanitize::identification('AB-123.456'))->toBe('AB123456');
});

test('identification returns null for null or blank input', function () {
    expect(Sanitize::identification(null))->toBeNull()
        ->and(Sanitize::identification(' . - '))->toBeNull();
});
