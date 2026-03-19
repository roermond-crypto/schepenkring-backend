<?php

namespace App\Exceptions;

class RetryableOpenAiVideoException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }
}
