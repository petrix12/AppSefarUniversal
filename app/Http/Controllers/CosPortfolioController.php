<?php

namespace App\Http\Controllers;

use App\Services\LocalCosPortfolioService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CosPortfolioController extends Controller
{
    public function listing(Request $request, LocalCosPortfolioService $portfolio)
    {
        $ownerId = $this->ownerScope($request);
        $filters = $request->validate([
            'certificate' => ['nullable', Rule::in(['all', 'yes', 'no'])],
            'balance' => ['nullable', Rule::in(['all', 'debt', 'review', 'no_recorded_balance', 'no_data'])],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $filters += ['certificate' => 'all', 'balance' => 'debt', 'q' => ''];
        $report = Cache::remember($this->cacheKey($ownerId), 300, fn () => $portfolio->generate($ownerId));
        $rows = collect($report['rows'])->filter(function ($row) use ($filters) {
            if ($filters['certificate'] !== 'all' && $row['has_certificate'] !== ($filters['certificate'] === 'yes')) {
                return false;
            }
            if ($filters['balance'] === 'debt' && $row['known_balance_eur'] <= 0.01) {
                return false;
            }
            if ($filters['balance'] === 'review' && $row['review_reasons'] === []) {
                return false;
            }
            if (in_array($filters['balance'], ['no_recorded_balance', 'no_data'], true) && $row['status'] !== $filters['balance']) {
                return false;
            }
            return $filters['q'] === '' || str_contains(
                mb_strtolower($row['name'].' '.$row['email'].' '.$row['passport']), mb_strtolower($filters['q'])
            );
        })->values();
        if ($request->get('export') === 'csv') {
            return response()->streamDownload(function () use ($rows, $portfolio) {
                $stream = fopen('php://output', 'w');
                $portfolio->writeCsv($stream, $rows);
                fclose($stream);
            }, 'cartera-cos-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $page = (int) $request->input('page', 1);
        $clients = new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return response()->view('cos.portfolio', compact('report', 'filters', 'clients'))->header('Cache-Control', 'private, no-store');
    }

    public function associate(Request $request, LocalCosPortfolioService $portfolio)
    {
        $ownerId = $this->ownerScope($request);
        // Recompute on the server; no browser-supplied IDs or stale cached decisions are trusted.
        $report = $portfolio->generate($ownerId);
        $count = $portfolio->applyMatches($report['associations']);
        Cache::forget($this->cacheKey($ownerId));
        Cache::forget($this->cacheKey(null));

        return redirect()->route('crud.cos.portfolio')->with('success', $count.' asociaciones guardadas por reglas sin IA.');
    }

    private function ownerScope(Request $request): ?int
    {
        $actor = $request->user();
        abort_if($actor->hasRole('Cliente'), 403);
        return ! $actor->hasRole('Administrador') && $actor->hasAnyRole(['Ventas', 'Coord. de Nacionalidad y Genealogía'])
            ? (int) $actor->id : null;
    }

    private function cacheKey(?int $ownerId): string
    {
        return 'cos.portfolio.v1.'.($ownerId ?? 'all');
    }
}
