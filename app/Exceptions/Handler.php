<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected function invalidJson($request, \Illuminate\Validation\ValidationException $exception)
    {
        if ($request->routeIs('trips.update', 'trips.dispatch')) {
            $sections = \App\Services\TripFormValidation::sections($exception->errors());

            return response()->json([
                'message' => implode("\n", $sections),
                'sections' => $sections,
                'errors' => $exception->errors(),
            ], $exception->status);
        }

        return parent::invalidJson($request, $exception);
    }
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
        $this->renderable(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if (($e->errorInfo[1] ?? null) === 1451 && str_contains($e->getMessage(), 'trip')) {
                $message = 'This record is used by a trip and cannot be deleted. Deactivate it instead where supported.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 409)
                    : back()->with('error', $message);
            }
        });
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
