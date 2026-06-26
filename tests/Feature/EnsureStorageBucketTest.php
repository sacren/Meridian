<?php

test('the ensure-bucket command fails when the target disk is not an s3 disk', function () {
    $this->artisan('storage:ensure-bucket', ['--disk' => 'local'])
        ->expectsOutputToContain('is not an S3 disk')
        ->assertFailed();
});

test('the ensure-bucket command fails when no bucket is configured', function () {
    config()->set('filesystems.disks.s3.bucket', '');

    $this->artisan('storage:ensure-bucket')
        ->expectsOutputToContain('No bucket is configured')
        ->assertFailed();
});
