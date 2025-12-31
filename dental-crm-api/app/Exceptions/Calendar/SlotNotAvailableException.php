<?php

namespace App\Exceptions\Calendar;

use Exception;

class SlotNotAvailableException extends Exception
{
    protected $message = 'The requested time slot is not available';
    protected $code = 422;

    public function __construct(string $message = null, int $code = 422)
    {
        parent::__construct($message ?? $this->message, $code);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'slot_not_available',
        ], $this->code);
    }
}

