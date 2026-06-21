<?php

namespace App\Services;

readonly class GitResult
{
    public function __construct(
        public bool $success,
        public string $output,
        public string $error,
        public int $exitCode,
    ) {}
}
