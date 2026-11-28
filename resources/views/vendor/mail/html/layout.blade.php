@include('mail.layouts.base', ['slot' => Illuminate\Mail\Markdown::parse($slot), 'subcopy' => $subcopy ?? ''])
