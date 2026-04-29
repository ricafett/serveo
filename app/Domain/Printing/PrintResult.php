<?php

namespace App\Domain\Printing;

class PrintResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
    ) {}

    public static function ok(?string $message = null): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
