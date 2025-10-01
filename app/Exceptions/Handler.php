<?php

namespace App\Exceptions;

use Throwable;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register()
    {
        //
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return ApiResponse::error('Unauthenticated', null, 401);
    }

    public function render($request, Throwable $e)
    {

        if ($request->is('api/*')) {
            // Validation errors
            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    'Validation failed',
                    $e->errors(),
                    422
                );
            }

            // Unauthenticated
            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(
                    'Unauthenticated',
                    null,
                    401
                );
            }

            // Route or resource not found
            if ($e instanceof NotFoundHttpException) {
                if (str_contains($e->getMessage(), 'No query results for model')) {
                    return ApiResponse::error('Resource not found', null, 404);
                }
                return ApiResponse::error('Endpoint not found', null, 404);
            }

            // Default error
            return ApiResponse::error(
                'Server error',
                $e->getMessage(),
                500
            );
        }

        return parent::render($request, $e);
    }
}