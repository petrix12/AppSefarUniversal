<?php

namespace App\Console\Commands;

use App\Models\TlCustomFieldDefinition;
use App\Services\TeamleaderService;
use Illuminate\Console\Command;

class SyncTlCustomFieldDefinitions extends Command
{
    protected $signature   = 'tl:sync-custom-field-definitions
                                {--context=all : Contexto a sincronizar: contact, company, deal, project, o all}';

    protected $description = 'Sincroniza las definiciones de custom fields desde Teamleader';

    // Todos los contextos que maneja TL
    private const CONTEXTS = [
        'contact',
        'company',
        'deal',
        'project',
    ];

    public function handle(TeamleaderService $tl): int
    {
        $contextOption = $this->option('context');

        $contexts = $contextOption === 'all'
            ? self::CONTEXTS
            : [$contextOption];

        $totalSynced = 0;

        foreach ($contexts as $context) {
            $this->info("── Contexto: {$context}");

            try {
                $definitions = $tl->getCustomFieldDefinitions($context);

                if (empty($definitions)) {
                    $this->warn("   Sin campos para contexto '{$context}'.");
                    continue;
                }

                foreach ($definitions as $def) {
                    TlCustomFieldDefinition::updateOrCreate(
                        ['id' => $def['id']],
                        [
                            'label'         => $def['label']                  ?? 'Sin nombre',
                            'type'          => $def['type']                   ?? 'unknown',
                            'context'       => $def['context']                ?? $context,
                            'required'      => $def['required']               ?? false,
                            'configuration' => $def['configuration']          ?? null,
                            'raw_data'      => $def,
                        ]
                    );

                    $totalSynced++;
                }

                $this->line("   ✓ " . count($definitions) . " definiciones sincronizadas.");

            } catch (\Exception $e) {
                $this->error("   Error en contexto '{$context}': " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Total sincronizado: {$totalSynced} definiciones.");

        return self::SUCCESS;
    }
}
