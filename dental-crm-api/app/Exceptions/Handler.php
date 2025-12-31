<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ValidationException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Handle API requests differently
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions.
     */
    protected function handleApiException($request, Throwable $e): JsonResponse
    {
        // Custom exceptions
        if ($e instanceof AppointmentConflictException) {
            return $e->render($request);
        }

        if ($e instanceof UnauthorizedException) {
            return $e->render($request);
        }

        if ($e instanceof ResourceNotFoundException) {
            return $e->render($request);
        }

        if ($e instanceof BusinessLogicException) {
            return $e->render($request);
        }

        if ($e instanceof InvalidStateException) {
            return $e->render($request);
        }

        // Authentication exception
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Необхідна автентифікація',
                'error' => 'unauthenticated',
            ], 401);
        }

        // Validation exception
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        // Model not found exception
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Ресурс не знайдено',
                'error' => 'not_found',
            ], 404);
        }

        // Not found HTTP exception
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'Маршрут не знайдено',
                'error' => 'not_found',
            ], 404);
        }

        // HTTP exceptions
        if ($e instanceof HttpException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Помилка сервера',
                'error' => 'http_error',
            ], $e->getStatusCode());
        }

        // Log unexpected errors
        if (config('app.debug')) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'internal_server_error',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ], 500);
        }

        // Production error response
        return response()->json([
            'message' => 'Внутрішня помилка сервера',
            'error' => 'internal_server_error',
        ], 500);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Необхідна автентифікація',
                'error' => 'unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }
}

