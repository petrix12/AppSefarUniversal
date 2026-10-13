<?php

namespace App\Http\Controllers;

use App\Services\SystemsActivitySources\TrelloActivitySource;
use App\Services\TrelloOAuthService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ManualTrelloActivityReportController extends Controller
{
    public function create(TrelloActivitySource $trello, TrelloOAuthService $oauth): View
    {
        $lists = [];
        $connectionError = null;

        if (! $oauth->isConfigured()) {
            $connectionError = 'Configura TRELLO_OAUTH_CLIENT_ID en el .env y ejecuta php artisan config:clear.';
        } elseif ($oauth->isConnected()) {
            try {
                $lists = $trello->availableLists();
            } catch (RuntimeException $exception) {
                $connectionError = $exception->getMessage();
            }
        }

        return view('systems-activity.manual-trello-report', [
            'lists' => $lists,
            'connectionError' => $connectionError,
            'oauthConfigured' => $oauth->isConfigured(),
            'trelloConnected' => $oauth->isConnected(),
            'report' => session('manual_trello_report'),
        ]);
    }

    public function generate(Request $request, TrelloActivitySource $trello): RedirectResponse
    {
        $validated = $request->validate(['list_id' => ['required', 'string', 'max:100']]);

        try {
            $result = $trello->cardsInList($validated['list_id']);
            $report = $this->formatReport($result['name'], $result['cards']);

            return redirect()->route('systems-activity.manual-trello.index')
                ->with('manual_trello_report', $report)
                ->with('selected_list_id', $validated['list_id']);
        } catch (RuntimeException $exception) {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => $exception->getMessage()])
                ->withInput();
        }
    }

    private function formatReport(string $listName, array $cards): string
    {
        $period = $this->periodFromListName($listName);
        $lines = [
            'Informe de actividad de Trello',
            'Columna: '.$listName,
            'Periodo: '.($period ?? 'según el nombre de la columna'),
            'Tarjetas encontradas: '.count($cards),
            '',
            'Las tarjetas se presentan tal como aparecen en la columna. La columna no se interpreta como un estado de finalización.',
        ];

        foreach ($cards as $index => $card) {
            $lines[] = '';
            $lines[] = ($index + 1).'. '.$card['name'];
            if ($card['description'] !== '') {
                $lines[] = '   '.$card['description'];
            }
        }

        return implode("\n", $lines);
    }

    private function periodFromListName(string $name): ?string
    {
        if (! preg_match('/(\d{2}\/\d{2}\/\d{4})\s*(?:->|→|↔|[-–—])\s*(\d{2}\/\d{2}\/\d{4})/u', $name, $matches)) {
            return null;
        }

        try {
            $from = CarbonImmutable::createFromFormat('!d/m/Y', $matches[1]);
            $to = CarbonImmutable::createFromFormat('!d/m/Y', $matches[2]);
        } catch (\Throwable) {
            return null;
        }

        return $from && $to ? $from->format('d/m/Y').' → '.$to->format('d/m/Y') : null;
    }
}
