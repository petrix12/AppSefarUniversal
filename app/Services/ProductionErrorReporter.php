<?php

namespace App\Services;

use App\Mail\ProductionErrorReport;
use App\Models\ApplicationErrorAudit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProductionErrorReporter
{
    private const REDACTED = '[REDACTADO]';

    private const SAFE_HEADERS = [
        'accept',
        'content-type',
        'host',
        'origin',
        'referer',
        'x-forwarded-for',
        'x-requested-with',
    ];

    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'current_password',
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'client_secret',
        'stripe_token',
        'card_number',
        'credit_card',
        'cvv',
        'cvc',
    ];

    public function capture(
        Request $request,
        Throwable $exception,
        Response $response,
        string $reference
    ): void {
        if (! config('error_reporting.enabled', true)) {
            return;
        }

        $report = $this->buildReport($request, $exception, $response, $reference);
        $recipient = trim((string) config('error_reporting.email'));
        $audit = $this->persist($report, $recipient);

        if ($recipient === '') {
            return;
        }

        try {
            Mail::to($recipient)->send(new ProductionErrorReport($report));
            $audit?->forceFill(['email_sent_at' => now(), 'email_error' => null])->saveQuietly();
        } catch (Throwable $mailException) {
            $message = $this->limit($mailException->getMessage(), 4000);

            try {
                $audit?->forceFill(['email_error' => $message])->saveQuietly();
            } catch (Throwable) {
                // The original error is already protected by the generic response.
            }

            $this->safeLog('No se pudo enviar el reporte privado de error de producción.', [
                'reference' => $reference,
                'recipient' => $recipient,
                'exception' => get_class($mailException),
                'message' => $message,
            ]);
        }
    }

    private function buildReport(
        Request $request,
        Throwable $exception,
        Response $response,
        string $reference
    ): array {
        $user = null;
        $routeName = null;
        $routeParameters = [];

        try {
            $user = $request->user();
        } catch (Throwable) {
            // Authentication may depend on the same unavailable service that failed.
        }

        try {
            $route = $request->route();
            $routeName = is_object($route) ? $route->getName() : null;
            $routeParameters = is_object($route) ? $route->parameters() : [];
        } catch (Throwable) {
            // A failure can happen before route binding is complete.
        }

        $contentType = $response->headers->get('Content-Type');

        return [
            'reference' => $reference,
            'environment' => app()->environment(),
            'occurred_at' => now(),
            'user' => [
                'id' => $user?->getAuthIdentifier(),
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'request' => [
                'method' => $request->method(),
                'url' => $request->url(),
                'route_name' => $routeName,
                'ip_address' => $request->ip(),
                'user_agent' => $this->limit((string) $request->userAgent(), 2000),
                'headers' => $this->safeHeaders($request),
                'data' => [
                    'parameters' => $this->sanitize($routeParameters),
                    'input' => $this->sanitize($request->input()),
                ],
                'files' => $this->fileMetadata($request->allFiles()),
            ],
            'response' => [
                'status_code' => $response->getStatusCode(),
                'content_type' => $contentType,
                'body' => $this->responseBody($response, $contentType),
            ],
            'exception' => [
                'class' => get_class($exception),
                'message' => $this->limit($exception->getMessage(), 20000),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->limit($exception->getTraceAsString(), 60000),
            ],
        ];
    }

    private function persist(array $report, string $recipient): ?ApplicationErrorAudit
    {
        try {
            return ApplicationErrorAudit::create([
                'reference' => $report['reference'],
                'environment' => $report['environment'],
                'user_id' => $report['user']['id'],
                'user_name' => $report['user']['name'],
                'user_email' => $report['user']['email'],
                'method' => $report['request']['method'],
                'url' => $report['request']['url'],
                'route_name' => $report['request']['route_name'],
                'ip_address' => $report['request']['ip_address'],
                'user_agent' => $report['request']['user_agent'],
                'request_headers' => $report['request']['headers'],
                'request_data' => $report['request']['data'],
                'request_files' => $report['request']['files'],
                'status_code' => $report['response']['status_code'],
                'response_content_type' => $report['response']['content_type'],
                'response_body' => $report['response']['body'],
                'exception_class' => $report['exception']['class'],
                'exception_message' => $report['exception']['message'],
                'exception_file' => $report['exception']['file'],
                'exception_line' => $report['exception']['line'],
                'exception_trace' => $report['exception']['trace'],
                'email_recipient' => $recipient ?: null,
                'occurred_at' => $report['occurred_at'],
            ]);
        } catch (Throwable $databaseException) {
            $this->safeLog('No se pudo guardar la auditoría del error de producción.', [
                'reference' => $report['reference'],
                'exception' => get_class($databaseException),
                'message' => $this->limit($databaseException->getMessage(), 4000),
            ]);

            return null;
        }
    }

    private function safeHeaders(Request $request): array
    {
        $headers = [];

        foreach (self::SAFE_HEADERS as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = $this->limit((string) $request->headers->get($name), 2000);
            }
        }

        return $headers;
    }

    private function sanitize(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($depth >= 6) {
            return '[DATOS ANIDADOS OMITIDOS]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach (array_slice($value, 0, 100, true) as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitize($childValue, (string) $childKey, $depth + 1);
            }

            if (count($value) > 100) {
                $sanitized['_omitted_items'] = count($value) - 100;
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->limit($value, 4000);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return sprintf('[%s]', get_debug_type($value));
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower(str_replace(['-', '.', ' '], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function fileMetadata(array $files): array
    {
        $metadata = [];

        foreach (array_slice($files, 0, 50, true) as $key => $file) {
            if (is_array($file)) {
                $metadata[$key] = $this->fileMetadata($file);
                continue;
            }

            if ($file instanceof UploadedFile) {
                $metadata[$key] = [
                    'original_name' => $this->limit($file->getClientOriginalName(), 255),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'upload_error' => $file->getError(),
                ];
            }
        }

        return $metadata;
    }

    private function responseBody(Response $response, ?string $contentType): ?string
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        if (str_contains(Str::lower((string) $contentType), 'html')) {
            $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = preg_replace('/\s+/u', ' ', $content) ?? $content;
        }

        return $this->limit(trim($content), 10000);
    }

    private function limit(string $value, int $limit): string
    {
        return Str::limit($value, $limit, '…');
    }

    private function safeLog(string $message, array $context): void
    {
        try {
            Log::error($message, $context);
        } catch (Throwable $loggingException) {
            error_log($message.' '.$loggingException->getMessage());
        }
    }
}
