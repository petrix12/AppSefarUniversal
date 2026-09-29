<?php

namespace Tests\Unit;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Services\MarketingEmailRenderer;
use Tests\TestCase;

class MarketingEmailRendererTest extends TestCase
{
    public function test_it_personalizes_and_tracks_a_campaign_email(): void
    {
        config()->set('app.key', 'marketing-renderer-test-key');
        config()->set('app.url', 'https://sefar.test');
        $campaign = new MarketingCampaign([
            'subject' => 'Hola {{first_name}}',
            'body_html' => '<h1>Hola {{first_name}}</h1><a href="https://example.org/oferta">Ver oferta</a>',
            'body_text' => 'Hola {{first_name}}',
        ]);
        $recipient = new MarketingCampaignRecipient([
            'email' => 'ana@example.org',
            'first_name' => 'Ana',
            'tracking_token' => 'b5fb2a6d-4b2b-4c0e-8dbb-d0d06650b4c0',
        ]);
        $recipient->setAttribute('attributes', []);

        $rendered = app(MarketingEmailRenderer::class)->render($campaign, $recipient);

        $this->assertSame('Hola Ana', $rendered['subject']);
        $this->assertStringContainsString('Hola Ana', $rendered['html']);
        $this->assertStringContainsString('/email/t/b5fb2a6d-4b2b-4c0e-8dbb-d0d06650b4c0/click/', $rendered['html']);
        $this->assertStringContainsString('/email/t/b5fb2a6d-4b2b-4c0e-8dbb-d0d06650b4c0/open', $rendered['html']);
        $this->assertStringContainsString('Cancelar suscripci', $rendered['html']);
        $this->assertSame('Hola Ana'."\n\nCancelar suscripción: ".route('marketing.unsubscribe.show', $recipient->tracking_token), $rendered['text']);
    }

    public function test_it_removes_active_html_from_the_editor_content(): void
    {
        $html = app(MarketingEmailRenderer::class)->sanitize('<p onclick="alert(1)">Hola</p><script>alert(1)</script><a href="javascript:alert(1)">Link</a>');

        $this->assertStringContainsString('Hola', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
