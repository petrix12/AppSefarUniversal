<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\TlCompany;
use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\TlDocument;
use App\Models\TlProject;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TlDocumentController extends Controller
{
    public function table(Request $request)
    {
        $query = TlDocument::query();

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('entity_id', 'like', "%{$search}%")
                    ->orWhere('s3_path', 'like', "%{$search}%");
            });
        }

        if ($entityType = $request->get('entity_type')) {
            if (in_array($entityType, ['contact', 'company', 'deal', 'project'], true)) {
                $query->where('entity_type', $entityType);
            }
        }

        if ($request->filled('downloaded')) {
            $query->where('downloaded', (bool) $request->boolean('downloaded'));
        }

        if ($extension = trim((string) $request->get('extension', ''))) {
            $query->where('extension', $extension);
        }

        $documents = $query
            ->orderByDesc('downloaded_at')
            ->orderByDesc('tl_created_at')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $context = $this->documentContext($documents->getCollection());

        $stats = [
            'total' => TlDocument::count(),
            'downloaded' => TlDocument::where('downloaded', true)->count(),
            'pending' => TlDocument::where('downloaded', false)->count(),
        ];

        $extensions = TlDocument::query()
            ->whereNotNull('extension')
            ->where('extension', '!=', '')
            ->distinct()
            ->orderBy('extension')
            ->pluck('extension');

        return view('teamleader.documents.index', compact('documents', 'context', 'stats', 'extensions'));
    }

    private function documentContext(Collection $documents): array
    {
        $idsByType = $documents
            ->groupBy('entity_type')
            ->map(fn (Collection $items) => $items->pluck('entity_id')->filter()->unique()->values());

        $projectIds = $idsByType->get('project', collect());
        $dealIds = $idsByType->get('deal', collect());

        $projects = $projectIds->isNotEmpty()
            ? TlProject::whereIn('id', $projectIds)->get()->keyBy('id')
            : collect();

        $deals = $dealIds->isNotEmpty()
            ? TlDeal::whereIn('id', $dealIds)->get()->keyBy('id')
            : collect();

        $contactIds = collect($idsByType->get('contact', collect()))
            ->merge($projects->where('customer_type', 'contact')->pluck('customer_id'))
            ->merge($deals->where('customer_type', 'contact')->pluck('customer_id'))
            ->filter()
            ->unique()
            ->values();

        $companyIds = collect($idsByType->get('company', collect()))
            ->merge($projects->where('customer_type', 'company')->pluck('customer_id'))
            ->merge($deals->where('customer_type', 'company')->pluck('customer_id'))
            ->filter()
            ->unique()
            ->values();

        $teamleaderBusinessIds = $projectIds
            ->merge($dealIds)
            ->filter()
            ->unique()
            ->values();

        return [
            'contacts' => $contactIds->isNotEmpty()
                ? TlContact::whereIn('id', $contactIds)->get()->keyBy('id')
                : collect(),
            'companies' => $companyIds->isNotEmpty()
                ? TlCompany::whereIn('id', $companyIds)->get()->keyBy('id')
                : collect(),
            'deals' => $deals,
            'projects' => $projects,
            'localDeals' => $teamleaderBusinessIds->isNotEmpty()
                ? Negocio::whereIn('teamleader_id', $teamleaderBusinessIds)->get()->keyBy('teamleader_id')
                : collect(),
        ];
    }
}
