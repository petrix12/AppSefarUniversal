<?php

namespace App\Services;

use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use DOMDocument;
use DOMElement;

class MarketingEmailRenderer
{
    /**
     * Render a draft for the campaign editor without creating tracking links or
     * recording activity for the sample recipient.
     */
    public function preview(string $subject, string $html, ?string $text, MarketingCampaignRecipient $recipient): array
    {
        $sanitizedHtml = $this->sanitize($html);

        return [
            'subject' => $this->personalize($subject, $recipient, false),
            'html' => $this->personalize($sanitizedHtml, $recipient),
            'text' => $this->personalize($text ?: trim(strip_tags($sanitizedHtml)), $recipient, false),
        ];
    }

    public function render(MarketingCampaign $campaign, MarketingCampaignRecipient $recipient): array
    {
        $html = $this->personalize($campaign->body_html, $recipient);
        $html = $this->rewriteLinks($html, $recipient);
        $unsubscribeUrl = route('marketing.unsubscribe.show', ['recipient' => $recipient->tracking_token]);
        $footer = '<p style="font-family:Arial,sans-serif;font-size:12px;color:#6c757d;text-align:center;margin:28px 0 0">'
            .'No deseas recibir estas comunicaciones? <a href="'.e($unsubscribeUrl).'">Cancelar suscripción</a>.</p>';
        $pixel = '<img src="'.e(route('marketing.track.open', ['recipient' => $recipient->tracking_token])).'" width="1" height="1" alt="" style="display:block;border:0" />';

        $trackedHtml = stripos($html, '</body>') !== false
            ? str_ireplace('</body>', $footer.$pixel.'</body>', $html)
            : $html.$footer.$pixel;

        return [
            'subject' => $this->personalize($campaign->subject, $recipient, false),
            'html' => $trackedHtml,
            'text' => $this->personalize($campaign->body_text ?: trim(strip_tags($campaign->body_html)), $recipient, false)
                ."\n\nCancelar suscripción: {$unsubscribeUrl}",
        ];
    }

    public function sanitize(string $html): string
    {
        $document = $this->document($html);
        foreach (['script', 'iframe', 'object', 'embed', 'form', 'base'] as $tag) {
            while (($node = $document->getElementsByTagName($tag)->item(0)) !== null) {
                $node->parentNode?->removeChild($node);
            }
        }
        foreach ($document->getElementsByTagName('*') as $node) {
            $remove = [];
            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction'], true)
                    || (($name === 'href' || $name === 'src') && preg_match('/^\s*(javascript|data:text\/html):/i', $value))) {
                    $remove[] = $attribute->name;
                }
            }
            foreach ($remove as $attribute) {
                $node->removeAttribute($attribute);
            }
        }

        return $this->bodyHtml($document);
    }

    public function validClickSignature(string $token, string $url, string $signature): bool
    {
        return hash_equals($this->signature($token, $url), $signature);
    }

    private function personalize(string $content, MarketingCampaignRecipient $recipient, bool $escape = true): string
    {
        $values = array_change_key_case((array) $recipient->getAttribute('attributes'), CASE_LOWER) + [
            'email' => $recipient->email,
            'first_name' => $recipient->first_name ?: '',
            'last_name' => $recipient->last_name ?: '',
            'full_name' => trim(($recipient->first_name ?: '').' '.($recipient->last_name ?: '')),
        ];

        return preg_replace_callback('/{{\s*([a-zA-Z0-9_.-]+)\s*}}/', function (array $match) use ($values, $escape): string {
            $key = strtolower(str_replace('-', '_', $match[1]));
            $value = (string) ($values[$key] ?? '');
            return $escape ? e($value) : $value;
        }, $content) ?? $content;
    }

    private function rewriteLinks(string $html, MarketingCampaignRecipient $recipient): string
    {
        $document = $this->document($html);
        foreach ($document->getElementsByTagName('a') as $link) {
            if (!$link instanceof DOMElement || !$link->hasAttribute('href')) {
                continue;
            }
            $destination = trim($link->getAttribute('href'));
            if (!preg_match('#^https?://#i', $destination)) {
                continue;
            }
            $encoded = rtrim(strtr(base64_encode($destination), '+/', '-_'), '=');
            $link->setAttribute('href', route('marketing.track.click', [
                'recipient' => $recipient->tracking_token,
                'url' => $encoded,
                'signature' => $this->signature($recipient->tracking_token, $destination),
            ]));
        }

        return $this->bodyHtml($document);
    }

    private function signature(string $token, string $url): string
    {
        return hash_hmac('sha256', $token.'|'.$url, (string) config('app.key'));
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $document;
    }

    private function bodyHtml(DOMDocument $document): string
    {
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $document->saveHTML();
        }
        $html = '';
        foreach ($body->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }
        return $html;
    }
}
