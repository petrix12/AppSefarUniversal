<?php

namespace Tests\Feature;

use App\Listeners\ApplyAppEmailTemplate;
use App\Models\User;
use App\Services\MarketingEmailRenderer;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SharedEmailTemplateTest extends TestCase
{
    public function test_wrapping_a_fragment_keeps_plain_text_attachments_links_and_one_brand_card(): void
    {
        $email = (new Email)->from('app@example.test')->to('person@example.test')->subject('Comprobante')
            ->text('Texto y token privado')->html('<p>Pago recibido.</p><a href="https://app.sefaruniversal.com/comprobante">Ver comprobante</a>')
            ->attach('PDF content', 'comprobante.pdf', 'application/pdf');
        $listener = new ApplyAppEmailTemplate;
        $listener->handle(new MessageSending($email));
        $listener->handle(new MessageSending($email));
        $html = $email->getHtmlBody();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//table[contains(@class,"sefar-email-card")]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-sefar-email-slot]')->length);
        $this->assertMatchesRegularExpression('/max-width:\s*640px/', $html);
        $link = $xpath->query('//a[@href="https://app.sefaruniversal.com/comprobante"]')->item(0);
        $this->assertStringContainsString('#093143', $link->getAttribute('style'));
        $this->assertStringNotContainsString('#dbba72', $link->getAttribute('style'));
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString('Atención al Representado', $html);
        $this->assertStringContainsString('https://app.sefaruniversal.com/comprobante', $html);
        $this->assertSame('Texto y token privado', $email->getTextBody());
        $this->assertSame('comprobante.pdf', $email->getAttachments()[0]->getFilename());
    }

    public function test_registration_receipts_and_deploy_updates_share_the_layout_and_keep_business_content(): void
    {
        $user = (new User)->forceFill(['name' => 'María Prueba', 'email' => 'maria@example.test', 'passport' => 'PASAPORTE-123', 'servicio' => 'Portuguesa Sefardí']);
        $user->created_at = now();
        $cases = [
            ['mail.registro-cliente', ['user' => $user], '¡Bienvenido a Sefar Universal!'],
            ['mail.comprobante-mail', ['user' => $user], 'María Prueba'],
            ['mail.carga-sefar', ['user' => $user], 'PASAPORTE-123'],
            ['mail.reporte-diario', ['peticion' => ['dia' => 28, 'mes' => 11, 'año' => 2026]], 'Reporte del día'],
            ['mail.deploy-summary', ['version' => '2.0.0', 'appName' => 'App Sefar', 'generatedAt' => now(),
                'notes' => ['intro' => ['Cambios disponibles'], 'sections' => [['title' => 'Correos', 'items' => [['title' => 'Diseño renovado', 'body' => 'Editable en la app']]]]]], 'Diseño renovado'],
        ];
        foreach ($cases as [$view, $data, $content]) {
            $html = view($view, $data)->render();
            $this->assertStringContainsString('data-sefar-email-template="base-v1"', $html, $view);
            $this->assertStringContainsString($content, $html, $view);
            $this->assertStringContainsString('Atención al Representado', $html, $view);
        }
    }

    public function test_saved_responsive_templates_keep_their_layout_after_personalization_and_tracking(): void
    {
        $html = view('mail.layouts.base', ['preheader' => 'Tu siguiente paso',
            'slot' => new \Illuminate\Support\HtmlString('<h1>Hola {{first_name}}</h1><a class="sefar-email-action" href="https://app.sefaruniversal.com">Entrar</a>')])->render();
        $renderer = app(MarketingEmailRenderer::class);
        $sanitized = $renderer->sanitize($html);
        $campaign = new \App\Models\MarketingCampaign(['subject' => 'Hola {{first_name}}', 'body_html' => $sanitized]);
        $recipient = new \App\Models\MarketingCampaignRecipient(['first_name' => 'María', 'email' => 'maria@example.test', 'tracking_token' => 'preview-token']);
        $recipient->setAttribute('attributes', []);
        $rendered = $renderer->render($campaign, $recipient);
        $this->assertStringContainsString('max-width:600px', $rendered['html']);
        $this->assertStringContainsString('data-sefar-email-slot', $rendered['html']);
        $this->assertStringContainsString('Hola María', $rendered['html']);
        $this->assertStringContainsString('/email/t/preview-token/click/', $rendered['html']);
        $this->assertStringNotContainsString('max-width:600px', $rendered['text']);
    }

    public function test_legacy_role_and_genealogy_storage_names_remain_usable_with_represented_labels(): void
    {
        $user = new User;
        $user->setRelation('roles', collect([new \Spatie\Permission\Models\Role(['name' => 'Cliente'])]));
        $this->assertSame('Representado', $user->adminlte_desc());
        $this->assertSame('Cliente', $user->getRoleNames()->first());
        $this->assertSame('Cliente', GetPersona(1)); // Also used for existing document directory names.
        $this->assertSame('Representado', \App\Support\RepresentedLanguage::label(GetPersona(1)));
    }
}
