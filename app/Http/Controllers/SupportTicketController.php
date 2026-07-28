<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ClientSupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportTicketController extends Controller
{
    public function __construct(private ClientSupportTicketService $supportTickets)
    {
    }

    public function clientCreate()
    {
        return view('clientes.support-ticket', [
            'user' => Auth::user(),
        ]);
    }

    public function clientStore(Request $request)
    {
        $data = $request->validate([
            'request_description' => ['required', 'string', 'min:15', 'max:3000'],
        ]);

        try {
            $result = $this->supportTickets->create(
                Auth::user(),
                Auth::user(),
                $data['request_description'],
                $request->input('source', 'Menu AdminLTE - Solicitud de soporte')
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->supportTicketError($request, $e);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Solicitud enviada. Creamos un ticket de soporte para revisar tu caso.',
                'ticket_id' => $result['ticket_id'],
            ], 201);
        }

        return back()->with('support_success', 'Solicitud enviada. Creamos un ticket de soporte para revisar tu caso.');
    }

    public function storeForUser(Request $request, User $user)
    {
        $data = $request->validate([
            'request_description' => ['required', 'string', 'min:15', 'max:3000'],
        ]);

        try {
            $result = $this->supportTickets->create(
                $user,
                Auth::user(),
                $data['request_description'],
                $request->input('source', 'Perfil de cliente - Solicitud de soporte')
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $this->safeErrorMessage($e),
            ], 422);
        }

        return response()->json([
            'message' => 'Solicitud enviada. Se creo un ticket en HubSpot y se notifico por correo.',
            'ticket_id' => $result['ticket_id'],
            'owner_email' => $result['owner_email'],
        ], 201);
    }

    public function adminTestCreate()
    {
        return view('pruebas.support-ticket');
    }

    public function adminTestStore(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'request_description' => ['required', 'string', 'min:15', 'max:3000'],
        ]);

        $client = User::role('Cliente')->find($data['client_id']);

        if (! $client) {
            return back()
                ->withInput()
                ->withErrors(['client_id' => 'Selecciona un cliente valido para la prueba.']);
        }

        try {
            $result = $this->supportTickets->create(
                $client,
                Auth::user(),
                $data['request_description'],
                'Prueba admin - Solicitud de soporte',
                true
            );
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors(['support_ticket' => $this->safeErrorMessage($e)]);
        }

        return back()
            ->with('support_success', 'Ticket de prueba creado y correos enviados.')
            ->with('support_ticket_id', $result['ticket_id'])
            ->with('support_owner_email', $result['owner_email']);
    }

    private function supportTicketError(Request $request, \Throwable $e)
    {
        $message = $this->safeErrorMessage($e);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()
            ->withInput()
            ->withErrors(['support_ticket' => $message]);
    }

    private function safeErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        $safeStarts = [
            'La solicitud',
            'No se pudo ubicar',
            'No se encontro',
            'El contacto no tiene',
            'El propietario del contacto',
            'No se pudo obtener el propietario',
        ];

        foreach ($safeStarts as $safeStart) {
            if (str_starts_with($message, $safeStart)) {
                return $message;
            }
        }

        return 'No se pudo enviar la solicitud de soporte en este momento.';
    }
}
