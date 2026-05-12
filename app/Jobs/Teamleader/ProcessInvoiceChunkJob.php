<?php
// app/Jobs/Teamleader/ProcessInvoiceChunkJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlInvoice;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessInvoiceChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $invoiceIds,
        public readonly int   $chunkNumber,
        public readonly int   $totalChunks,
        public readonly ?int  $syncLogId = null,
        public readonly bool  $downloadPdfs = true,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] Facturas — Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->invoiceIds) . " facturas");

        foreach ($this->invoiceIds as $id) {
            try {
                $existing = TlInvoice::find($id);
                $detail = $service->getInvoiceById($id);
                $invoice = TlInvoice::fromTeamleader($detail);

                if ($this->downloadPdfs && $this->invoiceNeedsPdfDownload($existing, $detail)) {
                    try {
                        $this->downloadPdf($invoice, $service);
                    } catch (\Throwable $pdfError) {
                        Log::error("[TL] Error PDF factura {$id}: " . $pdfError->getMessage());
                    }
                }

                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');
            } catch (\Throwable $e) {
                Log::error("[TL] Error factura {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000);
        }

        Log::info("[TL] Facturas — Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");
        $this->checkIfCompleted();
    }

    private function invoiceNeedsPdfDownload(?TlInvoice $existing, array $detail): bool
    {
        if (!$existing || !$existing->pdf_downloaded || blank($existing->pdf_s3_path)) {
            return true;
        }

        $teamleaderUpdatedAt = $detail['updated_at'] ?? null;

        if (!$teamleaderUpdatedAt || !$existing->tl_updated_at) {
            return false;
        }

        try {
            return Carbon::parse($teamleaderUpdatedAt)->gt($existing->tl_updated_at);
        } catch (\Throwable) {
            return false;
        }
    }

    private function downloadPdf(TlInvoice $invoice, TeamleaderService $service): void
    {
        $pdf = $service->downloadInvoicePdf($invoice->id);
        $name = $invoice->invoice_number ?: $invoice->id;
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: $invoice->id;
        $path = "teamleader/invoices/{$invoice->id}/{$safeName}.pdf";

        Storage::disk('s3')->put($path, $pdf, 'private');

        $invoice->update([
            'pdf_s3_path' => $path,
            'pdf_s3_disk' => 's3',
            'pdf_downloaded' => true,
            'pdf_downloaded_at' => now(),
        ]);
    }

    private function checkIfCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);
        if (!$log) return;

        if (($log->processed + $log->failed) >= $log->total) {
            Log::info("[TL] Facturas — SYNC COMPLETADO. Total: {$log->total}");
            $log->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] ProcessInvoiceChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', count($this->invoiceIds));
        $this->checkIfCompleted();
    }
}
