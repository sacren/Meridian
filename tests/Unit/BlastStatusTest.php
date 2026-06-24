<?php

use App\Enums\BlastStatus;

test('blast status carries the full send lifecycle', function () {
    expect(BlastStatus::cases())->toBe([
        BlastStatus::Draft,
        BlastStatus::Sending,
        BlastStatus::Sent,
        BlastStatus::Failed,
    ]);
});

test('blast status is string-backed for the database column', function () {
    expect(BlastStatus::Draft->value)->toBe('draft');
    expect(BlastStatus::Sending->value)->toBe('sending');
    expect(BlastStatus::Sent->value)->toBe('sent');
    expect(BlastStatus::Failed->value)->toBe('failed');
});
