<?php

namespace App\Exceptions\Booking;

use Exception;

class AppointmentNotFoundException extends Exception
{
    protected $message = 'Appointment not found';
    protected $code = 404;

    public function __construct(int $appointmentId = null, string $message = null)
    {
        $finalMessage = $message ?? ($appointmentId 
            ? "Appointment with ID {$appointmentId} not found"
            : $this->message);
        
        parent::__construct($finalMessage, $this->code);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'appointment_not_found',
        ], $this->code);
    }
}

