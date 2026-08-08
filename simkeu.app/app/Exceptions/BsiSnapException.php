<?php

namespace App\Exceptions;

use RuntimeException;

class BsiSnapException extends RuntimeException
{
    public function __construct(
        public readonly string $responseCode,
        public readonly int $httpStatus,
        string $responseMessage
    ) {
        parent::__construct($responseMessage);
    }

    public function responseBody(): array
    {
        return [
            'responseCode' => $this->responseCode,
            'responseMessage' => $this->getMessage(),
        ];
    }
}
