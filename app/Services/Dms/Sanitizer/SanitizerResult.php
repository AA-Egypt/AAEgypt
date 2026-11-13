<?php

namespace App\Services\Dms\Sanitizer;

class SanitizerResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $status,
        public readonly string $filePath,
        public readonly ?string $mimeType = null,
        public readonly ?string $error = null,
        public readonly ?string $quarantinedPath = null,
        public readonly array $details = []
    ) {}

    public function isClean(): bool
    {
        return $this->status === 'clean';
    }

    public function isInfected(): bool
    {
        return $this->status === 'infected';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function clean(string $filePath, ?string $mimeType = null, array $details = []): self
    {
        return new self(
            status: 'clean',
            filePath: $filePath,
            mimeType: $mimeType,
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function infected(string $filePath, string $error, ?string $quarantinedPath = null, array $details = []): self
    {
        return new self(
            status: 'infected',
            filePath: $filePath,
            error: $error,
            quarantinedPath: $quarantinedPath,
            details: $details
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function rejected(string $filePath, string $error, ?string $quarantinedPath = null, array $details = []): self
    {
        return new self(
            status: 'rejected',
            filePath: $filePath,
            error: $error,
            quarantinedPath: $quarantinedPath,
            details: $details
        );
    }
}
