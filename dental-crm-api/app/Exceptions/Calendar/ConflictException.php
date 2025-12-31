<?php

namespace App\Exceptions\Calendar;

use Exception;

class ConflictException extends Exception
{
    protected $message = 'Time conflict detected';
    protected $code = 409;
    protected array $conflicts = [];

    public function __construct(array $conflicts = [], string $message = null, int $code = 409)
    {
        $this->conflicts = $conflicts;
        parent::__construct($message ?? $this->message, $code);
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'calendar_conflict',
            'conflicts' => $this->conflicts,
        ], $this->code);
    }
}

