<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the s3 disk is configured with the s3 driver', function () {
    $disk = config('filesystems.disks.s3');

    expect($disk['driver'])->toBe('s3');
    expect($disk)->toHaveKeys(['bucket', 'endpoint', 'use_path_style_endpoint']);
});

test('a put/get round-trip on the faked s3 disk resolves', function () {
    Storage::fake('s3');

    Storage::disk('s3')->put('imports/roundtrip.csv', "name,email\nAda,ada@example.com\n");

    Storage::disk('s3')->assertExists('imports/roundtrip.csv');
    expect(Storage::disk('s3')->get('imports/roundtrip.csv'))
        ->toBe("name,email\nAda,ada@example.com\n");
});

test('an uploaded file stores on the s3 disk and reads back', function () {
    Storage::fake('s3');

    $upload = UploadedFile::fake()->createWithContent(
        'contacts.csv',
        "name,email\nGrace,grace@example.com\n",
    );

    $path = $upload->store('imports', 's3');

    Storage::disk('s3')->assertExists($path);
    expect(Storage::disk('s3')->get($path))
        ->toBe("name,email\nGrace,grace@example.com\n");
});
