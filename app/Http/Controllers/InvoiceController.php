<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    // ──────────────────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $invoices = Invoice::with(['user', 'customer', 'captador'])
            ->orderByDesc('invoice_date')
            ->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $nextNumber = Invoice::nextNumber();
        $captadores = $this->getCaptadores();

        return view('invoices.create', compact('nextNumber', 'captadores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // — cliente —
            'customer_user_id'     => 'nullable|exists:users,id',
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email',
            'customer_vat'         => 'nullable|string|max:100',
            'customer_address'     => 'nullable|string|max:500',
            'customer_country'     => 'nullable|string|max:100',
            'aa'                   => 'nullable|string|max:255',
            // — detalles —
            'invoice_date'         => 'required|date',
            'expiry_date'          => 'nullable|date',
            'currency'             => 'required|in:EUR,USD',
            'status'               => 'required|in:draft,sent,paid',
            'payment_terms'        => 'nullable|string|max:100',
            'payment_method'       => 'nullable|string|max:100',
            'bank_account'         => 'nullable|string|max:100',
            'notes'                => 'nullable|string',
            // — gestión interna —
            'captador_id'          => 'nullable|exists:users,id',
            'sales_team'           => 'nullable|string|max:255',
            'send_email'           => 'nullable|string|max:255',
            'product_service'      => 'nullable|string|max:255',
            // — depósitos —
            'deposit_number_client'=> 'nullable|string|max:255',
            'deposit_number_sefar' => 'nullable|string|max:255',
            'paid_by'              => 'nullable|string|max:255',
            // — líneas —
            'lines'                   => 'required|array|min:1',
            'lines.*.description'     => 'required|string',
            'lines.*.quantity'        => 'required|numeric|min:0',
            'lines.*.unit_price'      => 'required|numeric|min:0',
            'lines.*.tax_rate'        => 'required|numeric|min:0|max:100',
        ]);

        $invoice = Invoice::create([
            'invoice_number'       => Invoice::nextNumber(),
            'user_id'              => auth()->id(),
            // cliente
            'customer_user_id'     => $validated['customer_user_id']     ?? null,
            'customer_name'        => $validated['customer_name'],
            'customer_email'       => $validated['customer_email']        ?? null,
            'customer_vat'         => $validated['customer_vat']          ?? null,
            'customer_address'     => $validated['customer_address']      ?? null,
            'customer_country'     => $validated['customer_country']      ?? null,
            'aa'                   => $validated['aa']                    ?? null,
            // detalles
            'invoice_date'         => $validated['invoice_date'],
            'expiry_date'          => $validated['expiry_date']           ?? null,
            'currency'             => $validated['currency'],
            'status'               => $validated['status'],
            'payment_terms'        => $validated['payment_terms']         ?? null,
            'payment_method'       => $validated['payment_method']        ?? null,
            'bank_account'         => $validated['bank_account']          ?? null,
            'notes'                => $validated['notes']                 ?? null,
            // gestión interna
            'captador_id'          => $validated['captador_id']           ?? null,
            'sales_team'           => $validated['sales_team']            ?? null,
            'send_email'           => $validated['send_email']            ?? null,
            'product_service'      => $validated['product_service']       ?? null,
            // depósitos
            'deposit_number_client'=> $validated['deposit_number_client'] ?? null,
            'deposit_number_sefar' => $validated['deposit_number_sefar']  ?? null,
            'paid_by'              => $validated['paid_by']               ?? null,
        ]);

        foreach ($validated['lines'] as $i => $line) {
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity'    => $line['quantity'],
                'unit_price'  => $line['unit_price'],
                'tax_rate'    => $line['tax_rate'],
                'total'       => round($line['quantity'] * $line['unit_price'], 2),
                'sort_order'  => $i,
            ]);
        }

        $invoice->recalculate();

        if (!empty($validated['customer_user_id'])) {
            $this->syncUserFromInvoice((int) $validated['customer_user_id'], $validated);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura {$invoice->invoice_number} creada.");
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('lines', 'user', 'customer', 'captador');

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('lines', 'customer');

        $captadores = $this->getCaptadores();

        $selectedCustomer = null;
        if ($invoice->customer) {
            $u           = $invoice->customer;
            $displayName = trim(($u->nombres ?? '') . ' ' . ($u->apellidos ?? '')) ?: $u->name;
            $selectedCustomer = [
                'id'   => $u->id,
                'text' => $displayName . ' — ' . $u->email,
            ];
        }

        return view('invoices.edit', compact('invoice', 'selectedCustomer', 'captadores'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            // — cliente —
            'customer_user_id'     => 'nullable|exists:users,id',
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email',
            'customer_vat'         => 'nullable|string|max:100',
            'customer_address'     => 'nullable|string|max:500',
            'customer_country'     => 'nullable|string|max:100',
            'aa'                   => 'nullable|string|max:255',
            // — detalles —
            'invoice_date'         => 'required|date',
            'expiry_date'          => 'nullable|date',
            'currency'             => 'required|in:EUR,USD',
            'status'               => 'required|in:draft,sent,paid',
            'payment_terms'        => 'nullable|string|max:100',
            'payment_method'       => 'nullable|string|max:100',
            'bank_account'         => 'nullable|string|max:100',
            'notes'                => 'nullable|string',
            // — gestión interna —
            'captador_id'          => 'nullable|exists:users,id',
            'sales_team'           => 'nullable|string|max:255',
            'send_email'           => 'nullable|string|max:255',
            'product_service'      => 'nullable|string|max:255',
            // — depósitos —
            'deposit_number_client'=> 'nullable|string|max:255',
            'deposit_number_sefar' => 'nullable|string|max:255',
            'paid_by'              => 'nullable|string|max:255',
            // — líneas —
            'lines'                   => 'required|array|min:1',
            'lines.*.description'     => 'required|string',
            'lines.*.quantity'        => 'required|numeric|min:0',
            'lines.*.unit_price'      => 'required|numeric|min:0',
            'lines.*.tax_rate'        => 'required|numeric|min:0|max:100',
        ]);

        $invoice->update([
            // user_id NO cambia (quien la creó sigue siendo el mismo)
            'customer_user_id'     => $validated['customer_user_id']     ?? $invoice->customer_user_id,
            'customer_name'        => $validated['customer_name'],
            'customer_email'       => $validated['customer_email']        ?? null,
            'customer_vat'         => $validated['customer_vat']          ?? null,
            'customer_address'     => $validated['customer_address']      ?? null,
            'customer_country'     => $validated['customer_country']      ?? null,
            'aa'                   => $validated['aa']                    ?? null,
            // detalles
            'invoice_date'         => $validated['invoice_date'],
            'expiry_date'          => $validated['expiry_date']           ?? null,
            'currency'             => $validated['currency'],
            'status'               => $validated['status'],
            'payment_terms'        => $validated['payment_terms']         ?? null,
            'payment_method'       => $validated['payment_method']        ?? null,
            'bank_account'         => $validated['bank_account']          ?? null,
            'notes'                => $validated['notes']                 ?? null,
            // gestión interna
            'captador_id'          => $validated['captador_id']           ?? null,
            'sales_team'           => $validated['sales_team']            ?? null,
            'send_email'           => $validated['send_email']            ?? null,
            'product_service'      => $validated['product_service']       ?? null,
            // depósitos
            'deposit_number_client'=> $validated['deposit_number_client'] ?? null,
            'deposit_number_sefar' => $validated['deposit_number_sefar']  ?? null,
            'paid_by'              => $validated['paid_by']               ?? null,
        ]);

        $invoice->lines()->delete();

        foreach ($validated['lines'] as $i => $line) {
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity'    => $line['quantity'],
                'unit_price'  => $line['unit_price'],
                'tax_rate'    => $line['tax_rate'],
                'total'       => round($line['quantity'] * $line['unit_price'], 2),
                'sort_order'  => $i,
            ]);
        }

        $invoice->recalculate();

        $customerUserId = $validated['customer_user_id'] ?? $invoice->customer_user_id;
        if ($customerUserId) {
            $this->syncUserFromInvoice((int) $customerUserId, $validated);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura {$invoice->invoice_number} actualizada.");
    }

    public function destroy(Invoice $invoice)
    {
        $number = $invoice->invoice_number;
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', "Factura $number eliminada.");
    }

    // ──────────────────────────────────────────────────────────────────
    // AJAX
    // ──────────────────────────────────────────────────────────────────

    public function searchUsers(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = $request->get('q', '');

        $users = User::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name',       'like', "%{$q}%")
                        ->orWhere('nombres',   'like', "%{$q}%")
                        ->orWhere('apellidos', 'like', "%{$q}%")
                        ->orWhere('email',     'like', "%{$q}%")
                        ->orWhere('passport',  'like', "%{$q}%");
                });
            })
            ->select('id', 'name', 'nombres', 'apellidos', 'email', 'passport')
            ->limit(20)
            ->get()
            ->map(function ($u) {
                $displayName = trim(($u->nombres ?? '') . ' ' . ($u->apellidos ?? '')) ?: $u->name;
                return [
                    'id'   => $u->id,
                    'text' => $displayName . ' — ' . $u->email,
                ];
            });

        return response()->json(['results' => $users]);
    }

    public function getUserData(User $user): \Illuminate\Http\JsonResponse
    {
        $fullName = trim(($user->nombres ?? '') . ' ' . ($user->apellidos ?? '')) ?: $user->name;

        return response()->json([
            'id'               => $user->id,
            'customer_name'    => $fullName,
            'customer_email'   => $user->email,
            'customer_vat'     => $user->passport,
            'customer_address' => $user->address,
            'customer_country' => $user->pais_de_residencia,
            'missing'          => [
                'address'            => empty($user->address),
                'pais_de_residencia' => empty($user->pais_de_residencia),
                'passport'           => empty($user->passport),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // PRIVADOS
    // ──────────────────────────────────────────────────────────────────

    /**
     * Usuarios con rol Ventas (15) o Coordinador (17).
     */
    private function getCaptadores()
    {
        return User::whereHas('roles', function ($q) {
                $q->whereIn('id', [15, 17]);
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
    }

    /**
     * Rellena SOLO los campos vacíos del User.
     * Nunca sobreescribe datos que ya existen.
     */
    private function syncUserFromInvoice(int $userId, array $data): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $updates = [];

        if (empty($user->address) && !empty($data['customer_address'])) {
            $updates['address'] = $data['customer_address'];
        }

        if (empty($user->pais_de_residencia) && !empty($data['customer_country'])) {
            $updates['pais_de_residencia'] = $data['customer_country'];
        }

        if (empty($user->passport) && !empty($data['customer_vat'])) {
            $updates['passport'] = $data['customer_vat'];
        }

        if (!empty($updates)) {
            $user->update($updates);
        }
    }
}
