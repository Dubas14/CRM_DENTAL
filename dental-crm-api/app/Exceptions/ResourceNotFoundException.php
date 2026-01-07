<?php

namespace App\Exceptions;

use Exception;

class ResourceNotFoundException extends Exception
{
    public function __construct(string $resource = 'Resource', ?int $id = null)
    {
        $message = $id
            ? "{$resource} з ID {$id} не знайдено"
            : "{$resource} не знайдено";

        parent::__construct($message, 404);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'not_found',
        ], 404);
    }
}
