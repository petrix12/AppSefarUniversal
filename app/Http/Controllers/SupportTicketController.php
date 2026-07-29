<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ClientSupportTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function __construct(private ClientSupportTicketService $supportTickets)
    {
    }

    public function clientCreate()
    {
        return view('clientes.support-ticket', [
            'user' => Auth::user(),
            'supportTopics' => ClientSupportTicketService::topics(),
        ]);
    }

    public function clientStore(Request $request)
    {
        $data = $request->validate([
            'support_topic' => ['required', 'string', Rule::in(array_keys(ClientSupportTicketService::topics()))],
            'request_description' => ['required', 'string', 'min:15', 'max:3000'],
        ]);

        try {
            $result = $this->supportTickets->create(
                Auth::user(),
                Auth::user(),
                $data['request_description'],
                $data['support_topic'],
                $request->input('source', 'Menu AdminLTE - Solicitud de soporte')
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->supportTicketError($request, $e);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => $this->supportSuccessMessage($result),
                'ticket_id' => $result['ticket_id'],
                'used_hubspot_inbox_fallback' => $result['used_hubspot_inbox_fallback'],
            ], 201);
        }

        return back()->with(
            'support_success',
            $this->supportSuccessMessage($result)
        );
    }

    public function storeForUser(Request $request, User $user)
    {
        $data = $request->validate([
            'support_topic' => ['required', 'string', Rule::in(array_keys(ClientSupportTicketService::topics()))],
            'request_description' => ['required', 'string', 'min:15', 'max:3000'],
        ]);

        try {
            $result = $this->supportTickets->create(
                $user,
                Auth::user(),
                $data['request_description'],
                $data['support_topic'],
                $request->input('source', 'Perfil de cliente - Solicitud de soporte')
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $this->safeErrorMessage($e),
            ], 422);
        }

        return response()->json([
            'message' => $this->supportSuccessMessage($result),
            'ticket_id' => $result['ticket_id'],
            'owner_email' => $result['owner_email'],
            'used_hubspot_inbox_fallback' => $result['used_hubspot_inbox_fallback'],
            'ticket_error' => $result['ticket_error'],
        ], 201);
    }

    public function adminTestCreate()
    {
        return view('pruebas.support-ticket', [
            'supportTopics' => ClientSupportTicketService::topics(),
        ]);
    }

    public function adminTestStore(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'support_topic' => ['required', 'string', Rule::in(array_keys(ClientSupportTicketService::topics()))],
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
                $data['support_topic'],
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
            ->with(
                'support_success',
                $this->supportSuccessMessage($result, true)
            )
            ->with('support_ticket_id', $result['ticket_id'])
            ->with('support_owner_email', $result['owner_email'])
            ->with('support_ticket_error', $result['ticket_error'])
            ->with('support_inbox_fallback', $result['used_hubspot_inbox_fallback']);
    }

    private function supportSuccessMessage(array $result, bool $isTest = false): string
    {
        $prefix = $isTest ? 'Prueba enviada.' : 'Solicitud enviada.';

        if (! empty($result['ticket_id'])) {
            return "{$prefix} Ticket HubSpot creado: {$result['ticket_id']}.";
        }

        return "{$prefix} No se pudo crear el ticket por API, asi que se envio a info@sefarvzla.com para que ATC lo procese.";
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
        if ($e instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface) {
            return 'No se pudo enviar el correo de soporte en este momento.';
        }

        $message = $e->getMessage();
        $safeStarts = [
            'La solicitud',
            'No se pudo ubicar',
            'No se encontro',
            'El contacto no tiene',
            'El cliente',
            'El propietario del contacto',
            'No se pudo obtener el propietario',
            'Selecciona',
        ];

        foreach ($safeStarts as $safeStart) {
            if (str_starts_with($message, $safeStart)) {
                return $message;
            }
        }

        return 'No se pudo enviar la solicitud de soporte en este momento.';
    }
}
