<?php

namespace App\Http\Middleware;

use App\Support\CosPageTiming;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MeasureCosEdit
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('GET') || ! $request->is('users/*/edit') || ! $request->boolean('cos_timing')) {
            return $next($request);
        }
        $timing = new CosPageTiming();
        $request->attributes->set(CosPageTiming::ATTRIBUTE, $timing);
        try {
            $response = $next($request);
            if (! CosPageTiming::authorized($request)) return $response;
            $report = $timing->finish();
            Log::info('COS carga cronometrada', $report + ['status' => $response->getStatusCode()]);
            $response->headers->set('Server-Timing', $timing->serverTiming());
            $response->headers->set('X-COS-Timing-ID', $timing->id);
            $response->headers->set('Cache-Control', 'private, no-store');
            if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
                $payload = json_encode($report, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
                $response->setContent(str_replace('<!--COS_TIMING_PAYLOAD-->', '<script type="application/json" id="cos-timing-data">'.$payload.'</script>', $response->getContent()));
            }
            return $response;
        } catch (\Throwable $exception) {
            if (CosPageTiming::authorized($request)) {
                Log::warning('COS carga cronometrada fallida', $timing->finish() + ['exception_class' => $exception::class]);
            }
            throw $exception;
        } finally {
            $request->attributes->remove(CosPageTiming::ATTRIBUTE);
        }
    }
}
