<?php

namespace App\Exceptions\Booking;

use Exception;

class InvalidAppointmentStatusException extends Exception
{
    protected $message = 'Invalid appointment status transition';
    protected $code = 422;

    public function __construct(string $currentStatus, string $newStatus, string $message = null)
    {
        $finalMessage = $message ?? "Cannot change appointment status from '{$currentStatus}' to '{$newStatus}'";
        parent::__construct($finalMessage, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_status_transition',
        ], $this->code);
    }
}

