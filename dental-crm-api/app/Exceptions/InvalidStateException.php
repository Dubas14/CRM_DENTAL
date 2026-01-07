<?php

namespace App\Exceptions;

use Exception;

class InvalidStateException extends Exception
{
    public function __construct(string $message = 'Недопустимий стан об\'єкта для виконання операції')
    {
        parent::__construct($message, 422);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_state',
        ], 422);
    }
}
