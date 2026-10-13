<?php

namespace App\Http\Controllers;

use App\Services\TrelloOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TrelloOAuthController extends Controller
{
    public function start(TrelloOAuthService $oauth): RedirectResponse
    {
        try {
            $state = bin2hex(random_bytes(32));
            $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            session([
                'trello_oauth_state' => $state,
                'trello_oauth_verifier' => $verifier,
            ]);

            return redirect()->away($oauth->authorizationUrl($state, $challenge));
        } catch (RuntimeException $exception) {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, TrelloOAuthService $oauth): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('trello_oauth_state', '');
        $verifier = (string) $request->session()->pull('trello_oauth_verifier', '');
        $receivedState = (string) $request->query('state', '');

        if ($request->filled('error')) {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => 'Trello canceló la autorización: '.$request->query('error_description', $request->query('error'))]);
        }

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState) || $verifier === '') {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => 'No se pudo validar el retorno de Trello. Inicia la conexión nuevamente.']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => 'Trello no devolvió el código de autorización.']);
        }

        try {
            $oauth->exchangeAuthorizationCode($code, $verifier);

            return redirect()->route('systems-activity.manual-trello.index')
                ->with('status', 'Trello quedó conectado. Ya puedes generar el informe seleccionando una columna.');
        } catch (RuntimeException $exception) {
            return redirect()->route('systems-activity.manual-trello.index')
                ->withErrors(['trello' => $exception->getMessage()]);
        }
    }
}
