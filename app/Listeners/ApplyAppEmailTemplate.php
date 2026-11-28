<?php

namespace App\Listeners;

use App\Services\AppEmailTheme;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\HtmlString;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class ApplyAppEmailTemplate
{
    public function handle(MessageSending $event): void
    {
        $html = $event->message->getHtmlBody();
        if (! $html || ! str_contains($html, 'data-sefar-email-template=')) {
            if (! $html) {
                $html = '<p>'.nl2br(e($event->message->getTextBody() ?? '')).'</p>';
            } elseif (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $body)) {
                $html = $body[1];
            }
            $html = view('mail.layouts.base', ['title' => $event->message->getSubject(), 'slot' => new HtmlString($html)])->render();
        }
        // The inliner recognizes media queries when a space follows @media.
        $html = preg_replace('/@media\s*\(/i', '@media (', $html);
        // Keep the plain-text alternative and all attachments untouched.
        $event->message->html((new CssToInlineStyles)->convert($html, app(AppEmailTheme::class)->css()));
    }
}
