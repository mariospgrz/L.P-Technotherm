<?php
/**
 * Backend/S3Helper.php
 * Shared AWS S3 helpers for private invoice objects + presigned URLs.
 */

require_once __DIR__ . '/bootstrap.php';

use Aws\S3\S3Client;

function s3_client(): S3Client
{
    static $client = null;
    if ($client instanceof S3Client) {
        return $client;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('Composer vendor autoload missing.');
    }
    require_once $autoload;

    $client = new S3Client([
        'region' => app_config('aws_region', 'eu-central-1'),
        'version' => 'latest',
        'credentials' => [
            'key' => app_config('aws_key', ''),
            'secret' => app_config('aws_secret', ''),
        ],
    ]);

    return $client;
}

function s3_bucket(): string
{
    return (string) app_config('aws_bucket', '');
}

/**
 * Normalize stored photo_url to an S3 object key.
 * Supports legacy full ObjectURLs and plain keys like invoices/file.pdf
 */
function s3_key_from_photo_url(?string $photoUrl): ?string
{
    if ($photoUrl === null || $photoUrl === '') {
        return null;
    }

    if (str_starts_with($photoUrl, 'invoices/')) {
        return $photoUrl;
    }

    if (preg_match('#https?://[^/]+/(.+)$#', $photoUrl, $m)) {
        $path = urldecode($m[1]);
        // Strip query string if any
        $path = explode('?', $path, 2)[0];
        return $path !== '' ? $path : null;
    }

    return null;
}

function s3_delete_by_photo_url(?string $photoUrl): void
{
    $key = s3_key_from_photo_url($photoUrl);
    if ($key === null) {
        return;
    }
    $bucket = s3_bucket();
    if ($bucket === '') {
        return;
    }
    try {
        s3_client()->deleteObject([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);
    } catch (Throwable $e) {
        error_log('S3 delete failed for ' . $key . ': ' . $e->getMessage());
    }
}

function s3_presigned_url(string $key, int $expiresSeconds = 300): string
{
    $cmd = s3_client()->getCommand('GetObject', [
        'Bucket' => s3_bucket(),
        'Key' => $key,
    ]);
    $request = s3_client()->createPresignedRequest($cmd, '+' . $expiresSeconds . ' seconds');
    return (string) $request->getUri();
}
