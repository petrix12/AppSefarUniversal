<?php

namespace App\Exceptions;

use App\Services\ProductionErrorReporter;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render production server errors without exposing technical details.
     */
    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);

        if (! $request instanceof Request
            || ! app()->environment('production')
            || $response->getStatusCode() < 500) {
            return $response;
        }

        $reference = (string) Str::uuid();

        try {
            app(ProductionErrorReporter::class)->capture($request, $e, $response, $reference);
        } catch (Throwable $reportingException) {
            // The reporting path must never replace the original response.
            error_log(sprintf(
                'Could not capture production error %s: %s',
                $reference,
                $reportingException->getMessage()
            ));
        }

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Error-Reference' => $reference,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Error interno del servidor.',
                'reference' => $reference,
            ], 500, $headers);
        }

        return response()->view('errors.500', compact('reference'), 500, $headers);
    }
}
