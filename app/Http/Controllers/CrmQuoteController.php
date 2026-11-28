<?php

namespace App\Http\Controllers;

use App\Models\CrmQuote;
use App\Models\CrmQuoteItem;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmQuoteController extends Controller
{
    public function listing(Request $request)
    {
        $status = (string) $request->query('status', '');
        $query = CrmQuote::query()->with(['lead:id,nombre', 'client:id,name', 'createdBy:id,name'])->withCount('items');
        $query->when(isset(CrmQuote::STATUSES[$status]), fn ($q) => $q->where('status', $status));
        $query->when($request->filled('q'), function ($q) use ($request): void {
            $term = '%' . trim($request->query('q')) . '%';
            $q->where(fn ($matches) => $matches->where('quote_number', 'like', $term)->orWhere('title', 'like', $term));
        });

        return view('crm.quotes.index', [
            'quotes' => $query->latest()->paginate(25)->withQueryString(),
            'status' => $status, 'statuses' => CrmQuote::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        $lead = $request->filled('lead_id') ? Lead::find($request->integer('lead_id')) : null;
        $client = $request->filled('client_id') ? User::clients()->find($request->integer('client_id')) : null;
        abort_if($lead && $client, 422, 'Selecciona un único contacto.');
        return view('crm.quotes.form', [
            'quote' => new CrmQuote(['currency' => 'EUR', 'status' => 'draft', 'lead_id' => $lead?->id, 'client_id' => $client?->id]),
            'items' => [['description' => '', 'quantity' => 1, 'unit_price' => 0]],
            'contactLabel' => $lead ? trim($lead->nombre . ' · ' . ($lead->email ?: '')) . ' (Lead)' : ($client ? trim($client->name . ' · ' . ($client->email ?: '')) . ' (Representado)' : ''),
            'action' => route('crm.quotes.store'), 'method' => 'POST',
            'statuses' => CrmQuote::STATUSES,
        ]);
    }

    public function edit(CrmQuote $quote)
    {
        $quote->load(['lead:id,nombre,email', 'client:id,name,email', 'items']);
        return view('crm.quotes.form', [
            'quote' => $quote,
            'items' => $quote->items->map(fn (CrmQuoteItem $item) => $item->only(['description', 'quantity', 'unit_price']))->all(),
            'contactLabel' => $quote->lead
                ? trim($quote->lead->nombre . ' · ' . ($quote->lead->email ?: '')) . ' (Lead)'
                : ($quote->client ? trim($quote->client->name . ' · ' . ($quote->client->email ?: '')) . ' (Representado)' : ''),
            'action' => route('crm.quotes.update', $quote), 'method' => 'PUT',
            'statuses' => CrmQuote::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        [$data['lead_id'], $data['client_id']] = $this->contactIds($request);
        $this->persist($data, $request->user()->id);

        return redirect()->route('crm.quotes.index')->with('success', 'Presupuesto guardado como registro CRM.');
    }

    public function update(Request $request, CrmQuote $quote)
    {
        $data = $this->validated($request);
        [$data['lead_id'], $data['client_id']] = $this->contactIds($request);
        DB::transaction(function () use ($data, $quote): void {
            $this->updateQuote($quote, $data);
        });

        return redirect()->route('crm.quotes.index')->with('success', 'Presupuesto actualizado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(array_keys(CrmQuote::STATUSES))],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function contactIds(Request $request): array
    {
        $leadId = $request->integer('lead_id') ?: null;
        $clientId = $request->integer('client_id') ?: null;
        if ((bool) $leadId === (bool) $clientId) {
            throw ValidationException::withMessages(['contact_search' => 'Selecciona exactamente un lead o un representado de la lista.']);
        }
        if ($clientId) {
            if (! User::clients()->whereKey($clientId)->exists()) {
                throw ValidationException::withMessages(['contact_search' => 'El contacto seleccionado ya no está disponible.']);
            }
        }
        if ($leadId) {
            if (! Lead::whereKey($leadId)->exists()) {
                throw ValidationException::withMessages(['contact_search' => 'El lead seleccionado ya no existe.']);
            }
        }
        return [$leadId, $clientId];
    }

    private function persist(array $data, int $actorId): CrmQuote
    {
        return DB::transaction(function () use ($data, $actorId): CrmQuote {
            $items = $data['items'];
            $taxPercent = (float) ($data['tax_percent'] ?? 0);
            unset($data['items'], $data['tax_percent']);
            $data['currency'] = strtoupper($data['currency']);
            $data['created_by_user_id'] = $actorId;
            $quote = CrmQuote::create([
                ...$data,
                'quote_number' => 'TMP-' . Str::uuid(),
                'subtotal' => 0, 'tax_amount' => 0, 'total' => 0,
            ]);
            $quote->quote_number = sprintf('COT-%s-%06d', now()->format('Y'), $quote->id);
            $this->replaceItemsAndTotals($quote, $items, $taxPercent);

            return $quote;
        });
    }

    private function updateQuote(CrmQuote $quote, array $data): void
    {
        $items = $data['items'];
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        unset($data['items'], $data['tax_percent']);
        $data['currency'] = strtoupper($data['currency']);
        $quote->update($data);
        $quote->items()->delete();
        $this->replaceItemsAndTotals($quote, $items, $taxPercent);
    }

    private function replaceItemsAndTotals(CrmQuote $quote, array $items, float $taxPercent): void
    {
        $subtotal = 0.0;
        foreach (array_values($items) as $position => $item) {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $amount = round($quantity * $unitPrice, 2);
            $quote->items()->create([
                'description' => $item['description'], 'quantity' => $quantity,
                'unit_price' => $unitPrice, 'amount' => $amount, 'position' => $position,
            ]);
            $subtotal += $amount;
        }
        $subtotal = round($subtotal, 2);
        $taxAmount = round($subtotal * $taxPercent / 100, 2);
        $quote->update(['subtotal' => $subtotal, 'tax_amount' => $taxAmount, 'total' => round($subtotal + $taxAmount, 2)]);
    }
}
