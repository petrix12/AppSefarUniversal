<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductionErrorReport extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly array $report)
    {
    }

    public function build(): self
    {
        return $this
            ->subject(sprintf(
                '[%s] Error 500 %s — %s',
                config('app.name'),
                $this->report['reference'],
                $this->report['request']['method'].' '.$this->report['request']['url']
            ))
            ->view('mail.production-error-report');
    }
}
