<?php

namespace App\Services\Dms\Sanitizer;

class ClamAvResult
{
    public function __construct(
        public readonly bool $isInfected,
        public readonly ?string $virusName = null,
        public readonly ?string $error = null
    ) {}

    public function isInfected(): bool
    {
        return $this->isInfected;
    }

    public function isClean(): bool
    {
        return ! $this->isInfected && $this->error === null;
    }

    public function getVirusName(): ?string
    {
        return $this->virusName;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public static function clean(): self
    {
        return new self(isInfected: false);
    }

    public static function infected(string $virusName): self
    {
        return new self(isInfected: true, virusName: $virusName);
    }

    public static function error(string $error): self
    {
        return new self(isInfected: false, error: $error);
    }
}
