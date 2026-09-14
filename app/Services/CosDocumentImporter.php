<?php

namespace App\Services;

use App\Models\File;
use App\Models\Agcliente;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Storage;

class CosDocumentImporter
{
    /**
     * Import HubSpot files while preserving the property that supplied them.
     * Imported documents are deliberately private: an internal user must
     * associate and explicitly publish a document before a client can see it.
     *
     * @param array<int, string|array{url:string,hubspot_property?:string,property_label?:string,document_kind?:?string}> $entries
     */
    public function import(array $entries, $user, HubspotService $hubspotService): array
    {
        $client = new Client(['timeout' => 15]);
        $processedFiles = [];
        $location = "public/doc/{$user->passport}/";

        $existing = File::query()
            ->where('IDCliente', $user->passport)
            ->where('location', $location)
            ->get()
            ->keyBy('file');
        $existingByReference = $existing->filter(fn (File $file) => filled($file->source_reference))
            ->keyBy('source_reference');

        $promises = [];
        $downloads = [];
        $reservedNames = [];

        foreach ($entries as $entry) {
            try {
                $rawUrl = is_array($entry) ? ($entry['url'] ?? null) : $entry;
                if (! filled($rawUrl)) {
                    continue;
                }

                $fileUrl = $hubspotService->getFileUrlFromFormIntegrations($rawUrl);
                if (! $fileUrl) {
                    continue;
                }

                $property = is_array($entry) ? ($entry['hubspot_property'] ?? 'engagement') : 'engagement';
                $documentKind = is_array($entry) ? ($entry['document_kind'] ?? null) : null;
                $sourceReference = 'hubspot:' . $property . ':' . sha1($fileUrl);
                $legacyFilename = urldecode((string) basename((string) parse_url($fileUrl, PHP_URL_PATH)));

                // Older imports stored the direct URL as their reference. Upgrade
                // those records in place instead of importing a second copy.
                $matchingRecord = $existingByReference->get($sourceReference)
                    ?: $existing->first(fn (File $file) => (string) $file->source_reference === (string) $fileUrl);
                if (! $matchingRecord && $documentKind && $legacyFilename !== '') {
                    $candidate = $existing->get($legacyFilename);
                    $matchingRecord = $candidate?->source === 'hubspot' ? $candidate : null;
                }
                if ($matchingRecord) {
                    $this->upgradeImportedRecord($matchingRecord, $sourceReference, $documentKind);
                    $this->associateRecognizedClientDocument($matchingRecord->fresh(), $user->passport, $documentKind);
                    continue;
                }

                $filename = $this->filenameFor($fileUrl, $sourceReference, $reservedNames, $existing);

                $s3Path = $location . $filename;
                if (Storage::disk('s3')->exists($s3Path)) {
                    continue;
                }

                $key = sha1($fileUrl . '|' . $property);
                $downloads[$key] = [
                    'filename' => $filename,
                    's3_path' => $s3Path,
                    'document_kind' => $documentKind,
                    'source_reference' => $sourceReference,
                ];
                $promises[$key] = $client->getAsync($fileUrl);
            } catch (\Throwable $exception) {
                \Log::error('Error preparando descarga de HubSpot: ' . $exception->getMessage());
            }
        }

        foreach (Promise\Utils::settle($promises)->wait() as $key => $response) {
            if ($response['state'] !== 'fulfilled') {
                \Log::warning("Fallo descarga de HubSpot: {$key}");
                continue;
            }

            try {
                $metadata = $downloads[$key];
                $httpResponse = $response['value'];
                $contents = (string) $httpResponse->getBody();

                Storage::disk('s3')->put($metadata['s3_path'], $contents);

                $file = File::create([
                    'file' => $metadata['filename'],
                    'location' => $location,
                    'IDCliente' => $user->passport,
                    'user_id' => $user->id,
                    'source' => 'hubspot',
                    'client_visible' => false,
                    'document_kind' => $metadata['document_kind'],
                    'tipo' => $metadata['document_kind'] ? GenealogyDocumentService::label($metadata['document_kind']) : null,
                    'mime_type' => strtok($httpResponse->getHeaderLine('Content-Type'), ';') ?: null,
                    'size_bytes' => strlen($contents),
                    'source_reference' => $metadata['source_reference'],
                ]);
                $this->associateRecognizedClientDocument($file, $user->passport, $metadata['document_kind']);

                $processedFiles[] = $metadata['filename'];
            } catch (\Throwable $exception) {
                \Log::error("Error procesando importación de HubSpot {$key}: {$exception->getMessage()}");
            }
        }

        return $processedFiles;
    }

    private function filenameFor(string $fileUrl, string $sourceReference, array &$reservedNames, $existing): string
    {
        $filename = urldecode((string) basename((string) parse_url($fileUrl, PHP_URL_PATH)));
        $filename = trim($filename) ?: 'documento-hubspot';

        if (! isset($reservedNames[$filename]) && ! $existing->has($filename)) {
            $reservedNames[$filename] = true;

            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME) ?: 'documento-hubspot';
        $unique = $base . '-' . substr(sha1($sourceReference), 0, 10) . ($extension ? '.' . $extension : '');
        $reservedNames[$unique] = true;

        return $unique;
    }

    private function upgradeImportedRecord(File $file, string $sourceReference, ?string $documentKind): void
    {
        $changes = [
            'source' => 'hubspot',
            'source_reference' => $sourceReference,
        ];

        // Existing HubSpot documents may already have been explicitly shared by
        // a curator. A recurring import must never revoke that decision.
        if ($file->source !== 'hubspot') {
            $changes['client_visible'] = false;
        }

        if (! $file->document_kind && $documentKind) {
            $changes['document_kind'] = $documentKind;
            $changes['tipo'] = $file->tipo ?: GenealogyDocumentService::label($documentKind);
        }

        $file->forceFill($changes)->save();
    }

    /**
     * These two HubSpot properties belong to the contact itself, so their
     * genealogy target is unambiguous. This association is internal only;
     * `client_visible` remains false until a curator explicitly shares it.
     */
    private function associateRecognizedClientDocument(File $file, string $passport, ?string $documentKind): void
    {
        if (! in_array($documentKind, [GenealogyDocumentService::KIND_PASSPORT, GenealogyDocumentService::KIND_BIRTH], true)) {
            return;
        }

        // Do not overwrite an explicit association made later by the team.
        if ($file->people()->exists() || ! in_array((string) $file->IDPersonaNew, ['', '0'], true)) {
            return;
        }

        $clientPerson = Agcliente::query()
            ->where('IDCliente', $passport)
            ->where('IDPersona', 1)
            ->first();

        if ($clientPerson) {
            app(GenealogyDocumentService::class)->associatePerson($file, $clientPerson);
        }
    }
}
