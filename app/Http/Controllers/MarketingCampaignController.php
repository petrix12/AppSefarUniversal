<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingList;
use App\Models\MarketingTemplate;
use App\Models\User;
use App\Services\MarketingAudienceImporter;
use App\Services\MarketingCampaignDispatcher;
use App\Services\MarketingEmailRenderer;
use App\Services\MarketingSesSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketingCampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:administrador']);
    }

    public function dashboard()
    {
        $recipients = MarketingCampaignRecipient::query();
        $metrics = [
            'sent' => (clone $recipients)->whereNotNull('sent_at')->count(),
            'delivered' => (clone $recipients)->whereNotNull('delivered_at')->count(),
            'opened' => (clone $recipients)->whereNotNull('opened_at')->count(),
            'clicked' => (clone $recipients)->whereNotNull('clicked_at')->count(),
            'bounced' => (clone $recipients)->whereNotNull('bounced_at')->count(),
            'unsubscribed' => (clone $recipients)->whereNotNull('unsubscribed_at')->count(),
        ];
        $campaigns = MarketingCampaign::query()->with('list')->latest()->paginate(15);

        return view('marketing.dashboard', compact('metrics', 'campaigns'));
    }

    public function create()
    {
        return view('marketing.campaign-form', [
            'campaign' => new MarketingCampaign([
                'from_email' => config('marketing.ses.from_email'),
                'from_name' => config('marketing.ses.from_name'),
                'reply_to' => config('marketing.ses.reply_to'),
                'status' => MarketingCampaign::STATUS_DRAFT,
            ]),
            'lists' => MarketingList::withCount('members')->orderBy('name')->get(),
            'templates' => MarketingTemplate::latest()->get(),
        ]);
    }

    public function store(Request $request, MarketingEmailRenderer $renderer)
    {
        $campaign = MarketingCampaign::create($this->payload($request, $renderer) + ['created_by_user_id' => $request->user()->id]);

        return redirect()->route('marketing.campaigns.show', $campaign)->with('success', 'Campaña guardada. Revísala y envíala cuando estés listo.');
    }

    public function edit(MarketingCampaign $campaign)
    {
        abort_unless(in_array($campaign->status, [MarketingCampaign::STATUS_DRAFT, MarketingCampaign::STATUS_SCHEDULED, MarketingCampaign::STATUS_PAUSED], true), 422, 'La campaña ya comenzó a enviarse y no puede editarse.');

        return view('marketing.campaign-form', [
            'campaign' => $campaign,
            'lists' => MarketingList::withCount('members')->orderBy('name')->get(),
            'templates' => MarketingTemplate::latest()->get(),
        ]);
    }

    public function update(Request $request, MarketingCampaign $campaign, MarketingEmailRenderer $renderer)
    {
        abort_unless(in_array($campaign->status, [MarketingCampaign::STATUS_DRAFT, MarketingCampaign::STATUS_SCHEDULED, MarketingCampaign::STATUS_PAUSED], true), 422);
        $campaign->update($this->payload($request, $renderer));

        return redirect()->route('marketing.campaigns.show', $campaign)->with('success', 'Campaña actualizada.');
    }

    public function show(MarketingCampaign $campaign)
    {
        $analytics = $campaign->analytics();
        $events = $campaign->events()->with('recipient:id,email')->latest('occurred_at')->paginate(25);

        return view('marketing.campaign-show', compact('campaign', 'analytics', 'events'));
    }

    public function send(MarketingCampaign $campaign, MarketingCampaignDispatcher $dispatcher)
    {
        try {
            $queued = $dispatcher->queue($campaign);
            return back()->with('success', "Se prepararon {$queued} destinatarios para enviar por Amazon SES.");
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function pause(MarketingCampaign $campaign)
    {
        if ($campaign->status !== MarketingCampaign::STATUS_SENDING) {
            return back()->with('error', 'Solo se puede pausar una campaña que se esté enviando.');
        }
        $campaign->update(['status' => MarketingCampaign::STATUS_PAUSED]);
        return back()->with('success', 'Campaña pausada. Los destinatarios pendientes no serán enviados; puedes reanudarla cuando quieras.');
    }

    public function sendTest(Request $request, MarketingCampaign $campaign, MarketingEmailRenderer $renderer, MarketingSesSender $ses)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc,dns', 'max:191']]);
        $recipient = new MarketingCampaignRecipient([
            'email' => $data['email'],
            'first_name' => 'Contacto',
            'last_name' => 'de prueba',
            'tracking_token' => (string) Str::uuid(),
        ]);
        $recipient->setAttribute('attributes', []);

        try {
            $ses->sendPreview($campaign, $data['email'], $renderer->render($campaign, $recipient));
            return back()->with('success', 'Correo de prueba enviado mediante Amazon SES.');
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function previewWithRandomUser(Request $request, MarketingEmailRenderer $renderer)
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:1000000'],
            'body_text' => ['nullable', 'string', 'max:1000000'],
        ]);

        $user = User::query()
            ->select(['id', 'nombres', 'apellidos', 'email'])
            ->whereNotNull('nombres')->where('nombres', '!=', '')
            ->whereNotNull('apellidos')->where('apellidos', '!=', '')
            ->whereNotNull('email')->where('email', '!=', '')
            ->inRandomOrder()
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No hay usuarios con nombre, apellido y correo para generar la vista previa.',
            ], 422);
        }

        $recipient = new MarketingCampaignRecipient([
            'email' => $user->email,
            'first_name' => trim($user->nombres),
            'last_name' => trim($user->apellidos),
        ]);
        $recipient->setAttribute('attributes', []);

        $preview = $renderer->preview(
            $data['subject'] ?? '',
            $data['body_html'],
            $data['body_text'] ?? null,
            $recipient,
        );

        return response()->json($preview + [
            'recipient' => [
                'first_name' => $recipient->first_name,
                'last_name' => $recipient->last_name,
                'full_name' => trim($recipient->first_name.' '.$recipient->last_name),
                'email' => $recipient->email,
            ],
        ]);
    }

    public function lists()
    {
        $lists = MarketingList::withCount('members')->latest()->paginate(20);
        return view('marketing.lists', compact('lists'));
    }

    public function importList(Request $request, MarketingAudienceImporter $importer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source' => ['required', 'in:app,teamleader,hubspot,csv'],
            'app_segment' => ['nullable', 'in:all,clients,verified'],
            'teamleader_tags' => ['nullable', 'string', 'max:500'],
            'hubspot_list_id' => ['nullable', 'string', 'max:50'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);
        if ($data['source'] === 'csv' && !$request->hasFile('csv_file')) {
            return back()->withInput()->with('error', 'Selecciona un CSV para importar.');
        }
        $list = MarketingList::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'source_type' => $data['source'],
            'source_config' => [],
            'created_by_user_id' => $request->user()->id,
        ]);

        try {
            $imported = $importer->import($list, $data['source'], [
                'app_segment' => $data['app_segment'] ?? 'all',
                'teamleader_tags' => $data['teamleader_tags'] ?? '',
                'hubspot_list_id' => $data['hubspot_list_id'] ?? '',
            ], $request->file('csv_file'));
            return redirect()->route('marketing.lists.index')->with('success', "Lista creada con {$imported} contactos válidos.");
        } catch (\Throwable $exception) {
            $list->delete();
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function templates()
    {
        $templates = MarketingTemplate::latest()->paginate(20);
        return view('marketing.templates', compact('templates'));
    }

    public function storeTemplate(Request $request, MarketingEmailRenderer $renderer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:1000000'],
        ]);
        MarketingTemplate::create([
            'name' => $data['name'],
            'subject' => $data['subject'] ?? null,
            'body_html' => $renderer->sanitize($data['body_html']),
            'body_text' => trim(strip_tags($data['body_html'])),
            'created_by_user_id' => $request->user()->id,
        ]);
        return back()->with('success', 'Plantilla guardada.');
    }

    public function uploadTemplateImage(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],
        ]);

        $image = $data['image'];
        $path = 'marketing/templates/'.now()->format('Y/m');
        $filename = (string) Str::uuid().'.'.$image->extension();

        try {
            $storedPath = Storage::disk('s3')->putFileAs($path, $image, $filename, [
                'visibility' => 'public',
                'ContentType' => $image->getMimeType(),
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No se pudo cargar la imagen en S3.'], 422);
        }

        if ($storedPath === false) {
            return response()->json(['message' => 'No se pudo cargar la imagen en S3.'], 422);
        }

        return response()->json([
            'path' => $storedPath,
            'url' => Storage::disk('s3')->url($storedPath),
        ]);
    }

    public function setup()
    {
        return view('marketing.setup', [
            'configured' => filled(config('marketing.ses.key')) && filled(config('marketing.ses.secret')) && filled(config('marketing.ses.from_email')),
            'webhookUrl' => route('marketing.ses.webhook'),
            'topicsConfigured' => count(config('marketing.ses_sns_topic_arns', [])),
        ]);
    }

    private function payload(Request $request, MarketingEmailRenderer $renderer): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:255'],
            'from_email' => ['required', 'email:rfc,dns', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'reply_to' => ['nullable', 'email:rfc,dns', 'max:191'],
            'marketing_list_id' => ['required', 'exists:marketing_lists,id'],
            'marketing_template_id' => ['nullable', 'exists:marketing_templates,id'],
            'body_html' => ['required', 'string', 'max:1000000'],
            'body_text' => ['nullable', 'string', 'max:1000000'],
            'delivery_mode' => ['required', 'in:draft,scheduled'],
            'scheduled_at' => ['nullable', 'date'],
        ]);
        if ($data['delivery_mode'] === 'scheduled' && empty($data['scheduled_at'])) {
            abort(422, 'Indica cuándo debe enviarse la campaña programada.');
        }

        return [
            'name' => $data['name'], 'subject' => $data['subject'], 'from_email' => $data['from_email'],
            'from_name' => $data['from_name'] ?? null, 'reply_to' => $data['reply_to'] ?? null,
            'marketing_list_id' => $data['marketing_list_id'], 'marketing_template_id' => $data['marketing_template_id'] ?? null,
            'body_html' => $renderer->sanitize($data['body_html']), 'body_text' => $data['body_text'] ?? trim(strip_tags($data['body_html'])),
            'status' => $data['delivery_mode'] === 'scheduled' ? MarketingCampaign::STATUS_SCHEDULED : MarketingCampaign::STATUS_DRAFT,
            'scheduled_at' => $data['delivery_mode'] === 'scheduled' ? $data['scheduled_at'] : null,
        ];
    }
}
