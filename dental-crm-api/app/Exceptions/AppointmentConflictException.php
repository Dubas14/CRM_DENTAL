<?php

namespace App\Exceptions;

use Exception;

class AppointmentConflictException extends Exception
{
    protected $conflicts;
    protected $severity;

    public function __construct(array $conflicts, string $severity = 'hard')
    {
        $this->conflicts = $conflicts;
        $this->severity = $severity;

        $message = $severity === 'hard' 
            ? 'Неможливо створити запис через конфлікти'
            : 'Запис створено, але є попередження';

        parent::__construct($message, $severity === 'hard' ? 422 : 200);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflicts' => $this->conflicts,
            'severity' => $this->severity,
        ], $this->getCode());
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }
}

