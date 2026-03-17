<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // ── Helpers privados ──────────────────────────────────────────────

    private function captadores()
    {
        return User::whereHas('roles', function ($q) {
                $q->whereIn('id', [15, 17]); // Ventas y Coordinadores
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
    }

    private function generateNextNumber(): string
    {
        $year    = date('Y');
        $last    = Invoice::whereYear('created_at', $year)
                          ->orderByDesc('id')
                          ->value('invoice_number');

        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $next = intval($m[1] ?? 0) + 1;
        } else {
            $next = 1;
        }

        return 'FAC-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function selectedCustomer(Invoice $invoice): ?array
    {
        if (!$invoice->customer_user_id) return null;

        $user = User::find($invoice->customer_user_id);
        if (!$user) return null;

        return [
            'id'   => $user->id,
            'text' => $user->name . ' — ' . $user->email,
        ];
    }

    private function validationRules(): array
    {
        return [
            // cliente
            'customer_user_id'     => 'nullable|exists:users,id',
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email|max:255',
            'customer_vat'         => 'nullable|string|max:100',
            'customer_address'     => 'nullable|string|max:500',
            'customer_country'     => 'nullable|string|max:100',
            'aa'                   => 'nullable|string|max:255',
            // detalles
            'invoice_date'         => 'required|date',
            'expiry_date'          => 'nullable|date|after_or_equal:invoice_date',
            'currency'             => 'required|in:EUR,USD',
            'status'               => 'required|in:draft,sent,paid',
            'payment_terms'        => 'nullable|string|max:100',
            'payment_method'       => 'nullable|string|max:100',
            'bank_account'         => 'nullable|string|max:100',
            'notes'                => 'nullable|string',
            // gestión interna
            'captador_id'          => 'nullable|exists:users,id',
            'sales_team'           => 'nullable|string|max:255',
            'send_email'           => 'nullable|string|max:255',
            'product_service'      => 'nullable|string|max:255',
            // depósitos
            'deposit_number_client'=> 'nullable|string|max:255',
            'deposit_number_sefar' => 'nullable|string|max:255',
            'paid_by'              => 'nullable|string|max:255',
            // líneas
            'lines'                => 'required|array|min:1',
            'lines.*.description'  => 'required|string|max:500',
            'lines.*.quantity'     => 'required|numeric|min:0',
            'lines.*.unit_price'   => 'required|numeric|min:0',
            'lines.*.tax_rate'     => 'nullable|numeric|min:0|max:100',
        ];
    }

    private function calcTotals(array $lines): array
    {
        $excl = 0;
        $tax  = 0;

        foreach ($lines as $line) {
            $lineTotal  = floatval($line['quantity']) * floatval($line['unit_price']);
            $lineTax    = $lineTotal * (floatval($line['tax_rate'] ?? 0) / 100);
            $excl      += $lineTotal;
            $tax       += $lineTax;
        }

        return [
            'total_excl_tax'  => round($excl, 2),
            'total_tax'       => round($tax, 2),
            'total_incl_tax'  => round($excl + $tax, 2),
        ];
    }

    // ── CRUD ──────────────────────────────────────────────────────────

    public function index()
    {
        $invoices = Invoice::with(['user', 'captador'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('invoices.create', [
            'nextNumber' => $this->generateNextNumber(),
            'captadores' => $this->captadores(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        $totals = $this->calcTotals($data['lines']);

        DB::transaction(function () use ($data, $totals, $request) {

            $invoice = Invoice::create([
                'invoice_number'       => $this->generateNextNumber(),
                'user_id'              => Auth::id(),
                // cliente
                'customer_user_id'     => $data['customer_user_id']  ?? null,
                'customer_name'        => $data['customer_name'],
                'customer_email'       => $data['customer_email']     ?? null,
                'customer_vat'         => $data['customer_vat']       ?? null,
                'customer_address'     => $data['customer_address']   ?? null,
                'customer_country'     => $data['customer_country']   ?? null,
                'aa'                   => $data['aa']                 ?? null,
                // detalles
                'invoice_date'         => $data['invoice_date'],
                'expiry_date'          => $data['expiry_date']        ?? null,
                'currency'             => $data['currency'],
                'status'               => $data['status'],
                'payment_terms'        => $data['payment_terms']      ?? null,
                'payment_method'       => $data['payment_method']     ?? null,
                'bank_account'         => $data['bank_account']       ?? null,
                'notes'                => $data['notes']              ?? null,
                // gestión interna
                'captador_id'          => $data['captador_id']        ?? null,
                'sales_team'           => $data['sales_team']         ?? null,
                'send_email'           => $data['send_email']         ?? null,
                'product_service'      => $data['product_service']    ?? null,
                // depósitos
                'deposit_number_client'=> $data['deposit_number_client'] ?? null,
                'deposit_number_sefar' => $data['deposit_number_sefar']  ?? null,
                'paid_by'              => $data['paid_by']            ?? null,
                // totales
                'total_excl_tax'       => $totals['total_excl_tax'],
                'total_tax'            => $totals['total_tax'],
                'total_incl_tax'       => $totals['total_incl_tax'],
            ]);

            foreach ($data['lines'] as $line) {
                $lineTotal = floatval($line['quantity']) * floatval($line['unit_price']);
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity'    => $line['quantity'],
                    'unit_price'  => $line['unit_price'],
                    'tax_rate'    => $line['tax_rate'] ?? 0,
                    'total'       => round($lineTotal, 2),
                ]);
            }

            // Si había usuario vinculado, actualizar su vat/address/country
            // si estaban vacíos en el perfil
            if (!empty($data['customer_user_id'])) {
                $user = User::find($data['customer_user_id']);
                if ($user) {
                    $updates = [];
                    if (empty($user->passport)           && !empty($data['customer_vat']))     $updates['passport']          = $data['customer_vat'];
                    if (empty($user->address)            && !empty($data['customer_address'])) $updates['address']           = $data['customer_address'];
                    if (empty($user->pais_de_residencia) && !empty($data['customer_country'])) $updates['pais_de_residencia']= $data['customer_country'];
                    if (!empty($updates)) $user->update($updates);
                }
            }
        });

        return redirect()->route('invoices.index')
                         ->with('success', 'Factura creada correctamente.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['user', 'captador', 'lines']);

        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('lines');

        return view('invoices.edit', [
            'invoice'          => $invoice,
            'captadores'       => $this->captadores(),
            'selectedCustomer' => $this->selectedCustomer($invoice),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate($this->validationRules());

        $totals = $this->calcTotals($data['lines']);

        DB::transaction(function () use ($data, $totals, $invoice) {

            $invoice->update([
                // cliente
                'customer_user_id'     => $data['customer_user_id']  ?? null,
                'customer_name'        => $data['customer_name'],
                'customer_email'       => $data['customer_email']     ?? null,
                'customer_vat'         => $data['customer_vat']       ?? null,
                'customer_address'     => $data['customer_address']   ?? null,
                'customer_country'     => $data['customer_country']   ?? null,
                'aa'                   => $data['aa']                 ?? null,
                // detalles
                'invoice_date'         => $data['invoice_date'],
                'expiry_date'          => $data['expiry_date']        ?? null,
                'currency'             => $data['currency'],
                'status'               => $data['status'],
                'payment_terms'        => $data['payment_terms']      ?? null,
                'payment_method'       => $data['payment_method']     ?? null,
                'bank_account'         => $data['bank_account']       ?? null,
                'notes'                => $data['notes']              ?? null,
                // gestión interna
                'captador_id'          => $data['captador_id']        ?? null,
                'sales_team'           => $data['sales_team']         ?? null,
                'send_email'           => $data['send_email']         ?? null,
                'product_service'      => $data['product_service']    ?? null,
                // depósitos
                'deposit_number_client'=> $data['deposit_number_client'] ?? null,
                'deposit_number_sefar' => $data['deposit_number_sefar']  ?? null,
                'paid_by'              => $data['paid_by']            ?? null,
                // totales
                'total_excl_tax'       => $totals['total_excl_tax'],
                'total_tax'            => $totals['total_tax'],
                'total_incl_tax'       => $totals['total_incl_tax'],
            ]);

            // Reemplazar líneas
            $invoice->lines()->delete();
            foreach ($data['lines'] as $line) {
                $lineTotal = floatval($line['quantity']) * floatval($line['unit_price']);
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity'    => $line['quantity'],
                    'unit_price'  => $line['unit_price'],
                    'tax_rate'    => $line['tax_rate'] ?? 0,
                    'total'       => round($lineTotal, 2),
                ]);
            }

            // Actualizar perfil del usuario si había campos vacíos
            if (!empty($data['customer_user_id'])) {
                $user = User::find($data['customer_user_id']);
                if ($user) {
                    $updates = [];
                    if (empty($user->passport)           && !empty($data['customer_vat']))     $updates['passport']           = $data['customer_vat'];
                    if (empty($user->address)            && !empty($data['customer_address'])) $updates['address']            = $data['customer_address'];
                    if (empty($user->pais_de_residencia) && !empty($data['customer_country'])) $updates['pais_de_residencia'] = $data['customer_country'];
                    if (!empty($updates)) $user->update($updates);
                }
            }
        });

        return redirect()->route('invoices.show', $invoice)
                         ->with('success', 'Factura actualizada correctamente.');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->lines()->delete();
        $invoice->delete();

        return redirect()->route('invoices.index')
                         ->with('success', 'Factura eliminada correctamente.');
    }

    // ── AJAX ──────────────────────────────────────────────────────────

    public function searchUsers(Request $request)
    {
        $q = $request->input('q', '');

        $users = User::query()
            ->where(function ($query) use ($q) {
                $query->where('name',     'like', "%{$q}%")
                      ->orWhere('email',  'like', "%{$q}%")
                      ->orWhere('passport','like', "%{$q}%");
            })
            ->select('id', 'name', 'email', 'passport')
            ->limit(20)
            ->get()
            ->map(fn ($u) => [
                'id'   => $u->id,
                'text' => $u->name . ' — ' . $u->email . ($u->passport ? ' (' . $u->passport . ')' : ''),
            ]);

        return response()->json(['results' => $users]);
    }

    public function getUserData(User $user)
    {
        return response()->json([
            'customer_name'    => $user->name,
            'customer_email'   => $user->email,
            'customer_vat'     => $user->passport     ?? '',
            'customer_address' => $user->address      ?? '',
            'customer_country' => $user->pais_de_residencia ?? '',
            'missing' => [
                'passport'          => empty($user->passport),
                'address'           => empty($user->address),
                'pais_de_residencia'=> empty($user->pais_de_residencia),
            ],
        ]);
    }
}
