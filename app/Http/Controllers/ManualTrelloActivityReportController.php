<?php

namespace App\Http\Controllers;

use App\Services\ManualSystemsActivityReportService;
use App\Services\SystemsActivitySources\TrelloActivitySource;
use App\Services\TrelloOAuthService;
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

    public function generate(Request $request, ManualSystemsActivityReportService $reportService): RedirectResponse
    {
        $validated = $request->validate(['list_id' => ['required', 'string', 'max:100']]);

        try {
            $report = $reportService->generate($validated['list_id']);

            return redirect()->route('systems-activity.manual-trello.index')
                ->with('manual_trello_report', $report)
                ->with('selected_list_id', $validated['list_id']);
        } catch (RuntimeException $exception) {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['report' => $exception->getMessage()])
                ->withInput();
        }
    }
}
