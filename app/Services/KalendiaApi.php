<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KalendiaApi
{
    public function configured(): bool
    {
        return filled(config('kalendia.token'));
    }

    public function request(string $method, string $path, array $data = [], ?string $key = null): array
    {
        if (! $this->configured()) throw new KalendiaApiException();
        if (! in_array($method, ['GET', 'POST', 'PATCH'], true)) throw new KalendiaApiException();
        $base = rtrim((string) config('kalendia.base_url'), '/');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https'
            || parse_url($base, PHP_URL_HOST) !== 'sefar-universal-inc.app.kalendia.co') throw new KalendiaApiException();
        try {
            $request = Http::withToken(config('kalendia.token'))->acceptJson()->asJson()
                ->connectTimeout(4)->timeout(20)->withoutRedirecting();
            if ($key) $request = $request->withHeaders(['Idempotency-Key' => $key]);
            $response = match ($method) { 'GET' => $request->get($base.'/'.$path, $data), 'PATCH' => $request->patch($base.'/'.$path, $data), default => $request->post($base.'/'.$path, $data) };
        } catch (\Throwable) {
            throw new KalendiaApiException();
        }
        if (! $response->successful() || ! is_array($response->json('data'))) {
            $code = $response->json('error.code') === 'slot_unavailable' ? 'slot_unavailable' : null;
            throw new KalendiaApiException($response->status(), $code);
        }
        return array_replace($response->json('data'), ['_http_status' => $response->status()]);
    }

    public function services(bool $refresh = false): array
    {
        $cacheKey = 'kalendia.services.'.hash('sha256', (string) config('kalendia.token'));
        if ($refresh) Cache::forget($cacheKey);
        return Cache::remember($cacheKey, 60, function () {
            $items = [];
            $seen = [];
            $expectedPages = null;
            for ($page = 1; $page <= 100; $page++) {
                $result = $this->request('GET', 'services', ['page' => $page, 'per_page' => 100]);
                $pages = filter_var(data_get($result, 'pagination.pages', 1), FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 100]]);
                if (! is_array($result['items'] ?? null) || ! array_is_list($result['items']) || $pages === false
                    || ($expectedPages !== null && $expectedPages !== $pages)) throw new KalendiaApiException();
                $expectedPages = $pages;
                foreach ($result['items'] as $service) {
                    if (! is_array($service) || ! is_string($service['id'] ?? null)
                        || ! preg_match('/^svc_[A-Za-z0-9_-]+$/D', $service['id'])
                        || ! is_string($service['name'] ?? null) || trim($service['name']) === ''
                        || isset($seen[$service['id']])) throw new KalendiaApiException();
                    $seen[$service['id']] = true;
                    $items[] = ['id' => $service['id'], 'name' => $service['name'], 'duration_minutes' => $service['duration_minutes'] ?? null,
                        'price' => $service['price'] ?? null,
                        'currency' => $service['currency'] ?? $result['currency'] ?? config('kalendia.currency'),
                        'description' => is_string($service['description'] ?? null) ? $service['description'] : null,
                        'active' => filter_var($service['active'] ?? $service['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)];
                }
                if ($page >= $pages) return $items;
            }
            throw new KalendiaApiException();
        });
    }

    public function serviceFor(string $name): ?array
    {
        $matches = collect($this->services())->filter(fn ($service) =>
            RegistrationMeetingService::normalize($service['name']) === RegistrationMeetingService::normalize($name));
        // An ambiguous name must not silently select a different service.
        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function serviceById(string $id): ?array
    {
        return collect($this->services())->firstWhere('id', $id);
    }

    public function isFreeService(?array $service): bool
    {
        // Missing or malformed prices are never treated as a free booking.
        $price = $service['price'] ?? null;
        return is_numeric($price) && is_finite((float) $price) && (float) $price === 0.0;
    }

    public function canReservePaidService(?array $service): bool
    {
        $price = $service['price'] ?? null;
        return $service !== null && ($service['active'] ?? true)
            && is_numeric($price) && is_finite((float) $price) && (float) $price >= 0;
    }

    public function staffForName(string $name): ?string
    {
        if (RegistrationMeetingService::normalize($name) === '') return null;
        $staff = Cache::remember('kalendia.staff.'.hash('sha256', (string) config('kalendia.token')), 60, function () {
            $items = [];
            for ($page = 1; $page <= 10; $page++) {
                $result = $this->request('GET', 'staff', ['page' => $page, 'per_page' => 100]);
                foreach ($result['items'] ?? [] as $person) {
                    if (is_array($person) && is_string($person['name'] ?? null)
                        && is_string($person['id'] ?? null) && preg_match('/^stf_[A-Za-z0-9_-]+$/D', $person['id'])) {
                        $items[] = ['id' => $person['id'], 'name' => $person['name']];
                    }
                }
                if ($page >= (int) data_get($result, 'pagination.pages', 1)) break;
            }
            return $items;
        });
        $matches = collect($staff)->filter(fn ($person) => RegistrationMeetingService::normalize($person['name']) === RegistrationMeetingService::normalize($name));
        return $matches->count() === 1 ? $matches->first()['id'] : null;
    }
}
