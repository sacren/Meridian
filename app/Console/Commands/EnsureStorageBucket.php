<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

/**
 * Creates the object-store bucket backing an S3 disk if it does not already
 * exist. Sail's MinIO (and a fresh production S3 account) ships without the
 * application's bucket, so this makes a `composer setup` / fresh checkout
 * self-provisioning. It is idempotent — a present bucket is left untouched — so
 * it is safe to re-run from any setup script. It talks to the store through the
 * disk's own S3 client, so it works against MinIO in dev and AWS in production
 * as a config-only swap, exactly like the storage seam it supports.
 */
#[Signature('storage:ensure-bucket {--disk=s3 : The S3 filesystem disk whose bucket should exist}')]
#[Description('Create the configured S3 disk\'s bucket if it does not already exist (idempotent).')]
class EnsureStorageBucket extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = (string) $this->option('disk');

        $filesystem = Storage::disk($disk);

        if (! $filesystem instanceof AwsS3V3Adapter) {
            $this->error("The [{$disk}] disk is not an S3 disk; there is no bucket to ensure.");

            return self::FAILURE;
        }

        $bucket = (string) config("filesystems.disks.{$disk}.bucket");

        if ($bucket === '') {
            $this->error("No bucket is configured for the [{$disk}] disk; set AWS_BUCKET first.");

            return self::FAILURE;
        }

        $client = $filesystem->getClient();

        if ($this->bucketExists($client, $bucket)) {
            $this->info("Bucket [{$bucket}] already exists on the [{$disk}] disk.");

            return self::SUCCESS;
        }

        $this->createBucket($client, $bucket);
        $this->info("Created bucket [{$bucket}] on the [{$disk}] disk.");

        return self::SUCCESS;
    }

    /**
     * Whether the bucket is already present. A 404 means "create it"; any other
     * S3 error (a 403, a bad endpoint, a connection failure) is a real problem
     * and is re-thrown rather than mistaken for a missing bucket.
     */
    protected function bucketExists(S3ClientInterface $client, string $bucket): bool
    {
        try {
            $client->headBucket(['Bucket' => $bucket]);

            return true;
        } catch (S3Exception $exception) {
            if ($exception->getStatusCode() === 404) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Create the bucket, tolerating a concurrent creator so the command stays
     * idempotent even under a race.
     */
    protected function createBucket(S3ClientInterface $client, string $bucket): void
    {
        try {
            $client->createBucket(['Bucket' => $bucket]);
        } catch (S3Exception $exception) {
            if (! in_array($exception->getAwsErrorCode(), ['BucketAlreadyOwnedByYou', 'BucketAlreadyExists'], true)) {
                throw $exception;
            }
        }
    }
}
