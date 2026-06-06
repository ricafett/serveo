<?php

namespace App\Domain\Printing;

class PrintResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly bool $contended = false,
    ) {}

    public static function ok(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }

    /**
     * A contended result means a per-printer lock was held by another
     * process. The caller should retry, not treat this as a permanent
     * transport failure.
     */
    public static function contended(string $message = 'Printer busy'): self
    {
        return new self(false, $message, true);
    }
}
