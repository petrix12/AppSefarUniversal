<?php

namespace Tests\Feature;

use App\Mail\ProductionErrorReport;
use App\Models\ApplicationErrorAudit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ProductionErrorReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('app.debug', true);
        config()->set('error_reporting.enabled', true);
        config()->set('error_reporting.email', 'private-errors@example.test');

        Schema::dropIfExists('application_error_audits');
        Schema::create('application_error_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('environment', 30);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('method', 10)->nullable();
            $table->text('url')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_data')->nullable();
            $table->json('request_files')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('response_content_type')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('exception_class');
            $table->longText('exception_message');
            $table->text('exception_file');
            $table->unsignedInteger('exception_line');
            $table->longText('exception_trace')->nullable();
            $table->string('email_recipient')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Mail::fake();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('application_error_audits');

        parent::tearDown();
    }

    public function test_production_server_error_is_hidden_audited_and_emailed(): void
    {
        $request = Request::create('/checkout?public_filter=active', 'POST', [
            'description' => 'Visible for diagnosis',
            'password' => 'never-store-this',
            'access_token' => 'never-store-this-either',
        ], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer private-token',
            'HTTP_USER_AGENT' => 'Production test agent',
        ]);
        $exception = new RuntimeException('Sensitive technical database failure');

        $response = app(\App\Exceptions\Handler::class)->render($request, $exception);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Error interno del servidor.', $response->getContent());
        $this->assertStringNotContainsString($exception->getMessage(), $response->getContent());

        $reference = $response->headers->get('X-Error-Reference');
        $this->assertNotEmpty($reference);

        $audit = ApplicationErrorAudit::where('reference', $reference)->firstOrFail();
        $this->assertSame(500, $audit->status_code);
        $this->assertSame($exception->getMessage(), $audit->exception_message);
        $this->assertSame('Visible for diagnosis', $audit->request_data['input']['description']);
        $this->assertSame('[REDACTADO]', $audit->request_data['input']['password']);
        $this->assertSame('[REDACTADO]', $audit->request_data['input']['access_token']);
        $this->assertArrayNotHasKey('authorization', $audit->request_headers);
        $this->assertNotNull($audit->email_sent_at);

        Mail::assertSent(ProductionErrorReport::class, function (ProductionErrorReport $mail) use ($reference): bool {
            return $mail->hasTo('private-errors@example.test')
                && $mail->report['reference'] === $reference
                && $mail->report['request']['data']['input']['password'] === '[REDACTADO]';
        });
    }

    public function test_browser_receives_only_the_generic_500_page(): void
    {
        config()->set('app.debug', false);
        $request = Request::create('/browser-failure', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $exception = new RuntimeException('This must never be rendered to the browser');

        $response = app(\App\Exceptions\Handler::class)->render($request, $exception);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('<h1>Error 500</h1>', $response->getContent());
        $this->assertStringContainsString($response->headers->get('X-Error-Reference'), $response->getContent());
        $this->assertStringNotContainsString($exception->getMessage(), $response->getContent());
    }

    public function test_email_is_still_attempted_when_the_audit_database_is_unavailable(): void
    {
        Schema::dropIfExists('application_error_audits');
        $request = Request::create('/database-failure', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = app(\App\Exceptions\Handler::class)->render(
            $request,
            new RuntimeException('Database unavailable')
        );

        $this->assertSame(500, $response->getStatusCode());
        Mail::assertSent(ProductionErrorReport::class);
    }

    public function test_expected_client_errors_are_not_audited_or_emailed(): void
    {
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = app(\App\Exceptions\Handler::class)->render(
            $request,
            new NotFoundHttpException('Missing test route')
        );

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(0, ApplicationErrorAudit::count());
        Mail::assertNothingSent();
    }
}
