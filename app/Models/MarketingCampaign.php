<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(MarketingList::class, 'marketing_list_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketingTemplate::class, 'marketing_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketingCampaignEvent::class);
    }

    public function analytics(): array
    {
        $recipients = $this->recipients();
        $sent = (clone $recipients)->whereNotNull('sent_at')->count();
        $delivered = (clone $recipients)->whereNotNull('delivered_at')->count();
        $opened = (clone $recipients)->whereNotNull('opened_at')->count();
        $clicked = (clone $recipients)->whereNotNull('clicked_at')->count();
        $bounced = (clone $recipients)->whereNotNull('bounced_at')->count();
        $complained = (clone $recipients)->whereNotNull('complained_at')->count();
        $unsubscribed = (clone $recipients)->whereNotNull('unsubscribed_at')->count();

        return compact('sent', 'delivered', 'opened', 'clicked', 'bounced', 'complained', 'unsubscribed') + [
            'total' => (clone $recipients)->count(),
            'open_events' => $this->events()->where('event_type', 'open')->count(),
            'click_events' => $this->events()->where('event_type', 'click')->count(),
        ];
    }
}
