<?php

namespace App\Exceptions;

use Exception;

class BusinessLogicException extends Exception
{
    protected $errorCode;
    protected $errorData;

    public function __construct(
        string $message,
        string $errorCode = 'business_logic_error',
        array $errorData = [],
        int $httpStatusCode = 422
    ) {
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;

        parent::__construct($message, $httpStatusCode);
    }

    public function render($request)
    {
        $response = [
            'message' => $this->getMessage(),
            'error' => $this->errorCode,
        ];

        if (!empty($this->errorData)) {
            $response['data'] = $this->errorData;
        }

        return response()->json($response, $this->getCode());
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}

