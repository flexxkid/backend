<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentStorageService
{
    public function disk(): string
    {
        $configuredDisk = config('filesystems.default', 'local');

        if ($configuredDisk === 'b2' && ! $this->isB2Configured()) {
            return 'local';
        }

        return $configuredDisk;
    }

    public function store(UploadedFile $file, string $directory, ?string $filename = null): string
    {
        $disk = $this->disk();

        $path = $filename === null
            ? Storage::disk($disk)->putFile($directory, $file)
            : Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $path;
    }

    public function url(string $path): string
    {
        $disk = $this->disk();

        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(5));
        } catch (Throwable) {
            return Storage::disk($disk)->url($path);
        }
    }

    public function isB2Configured(): bool
    {
        return filled(config('filesystems.disks.b2.key'))
            && filled(config('filesystems.disks.b2.secret'))
            && filled(config('filesystems.disks.b2.bucket'))
            && filled(config('filesystems.disks.b2.endpoint'));
    }
}
