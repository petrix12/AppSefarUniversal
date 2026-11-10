<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminClientExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_09_01_000000_add_contrato_to_users_table.php',
        ]])->assertExitCode(0);
        foreach (['Administrador', 'Admin', 'Cliente', 'Ventas', 'Genealogista'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_guests_cannot_open_the_page_or_download_client_data(): void
    {
        $this->get(route('admin.client-exports.index'))->assertRedirect(route('login'));
        $this->getJson(route('admin.client-exports.download'))->assertUnauthorized();
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_export_is_only_available_to_internal_administrators(array $roles): void
    {
        $actor = User::factory()->create()->syncRoles($roles);
        $this->actingAs($actor)->getJson(route('admin.client-exports.index'))->assertForbidden();
        foreach (['csv', 'xlsx'] as $format) {
            $this->getJson(route('admin.client-exports.download', ['format' => $format]))->assertForbidden();
        }
    }

    public static function nonAdministratorRoles(): array
    {
        return [[['Admin']], [['Ventas']], [['Genealogista']], [['Cliente']], [['Administrador', 'Cliente']]];
    }

    public function test_a_permissive_gate_cannot_grant_exports_to_a_non_administrator(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create()->syncRoles(['Ventas']))
            ->getJson(route('admin.client-exports.download', ['format' => 'csv']))->assertForbidden();
    }

    #[DataProvider('registrationGroups')]
    public function test_each_registration_group_exports_exactly_its_clients(string $group, array $expected): void
    {
        $this->registrationFixtures();
        $this->actingAs($this->administrator());

        $response = $this->get(route('admin.client-exports.download', ['group' => $group, 'format' => 'csv']))->assertOk();
        $rows = $this->csvRows($response->streamedContent());
        $this->assertEqualsCanonicalizing($expected, array_column(array_slice($rows, 1), 4));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('password', implode(',', $rows[0]));
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public static function registrationGroups(): array
    {
        return [
            ['unpaid', ['unpaid@example.test']],
            ['paid', ['paid@example.test', 'paid-signed@example.test']],
            ['complete', ['complete@example.test']],
            ['all', ['unpaid@example.test', 'paid@example.test', 'paid-signed@example.test',
                'complete@example.test', 'unsigned@example.test', 'other@example.test', 'unknown@example.test']],
        ];
    }

    public function test_preview_counts_and_download_cover_all_pages_with_the_same_search_filter(): void
    {
        $actor = $this->administrator();
        User::factory()->count(57)->create(['name' => 'Exportación completa', 'pay' => 1, 'contrato' => 0])
            ->each(fn (User $user) => $user->assignRole('Cliente'));
        User::factory()->create(['name' => 'Otro cliente', 'pay' => 1])->assignRole('Cliente');
        $this->actingAs($actor)->get(route('admin.client-exports.index', ['group' => 'paid', 'q' => 'Exportación completa']))
            ->assertOk()->assertSee('Descargar Excel')
            ->assertViewHas('clients', fn ($clients) => $clients->total() === 57 && $clients->count() === 25)
            ->assertViewHas('counts', fn ($counts) => (int) $counts->paid === 58);

        $response = $this->get(route('admin.client-exports.download', ['group' => 'paid', 'q' => 'Exportación completa', 'format' => 'csv', 'page' => 2]))->assertOk();
        $this->assertCount(58, $this->csvRows($response->streamedContent()));
    }

    public function test_summary_counts_only_clients_and_does_not_treat_unsigned_or_unknown_states_as_complete(): void
    {
        $this->registrationFixtures();
        $this->actingAs($this->administrator())->get(route('admin.client-exports.index'))
            ->assertOk()->assertViewHas('counts', fn ($counts) => (int) $counts->total === 7
                && (int) $counts->unpaid === 1 && (int) $counts->paid === 2 && (int) $counts->complete === 1);
    }

    public function test_excel_preserves_identifiers_and_customer_text_without_executing_formulas(): void
    {
        User::factory()->create([
            'name' => '=1+1', 'email' => 'xlsx@example.test', 'pay' => 2, 'contrato' => 1,
            'passport' => '000123', 'phone' => '+584121234567',
        ])->assignRole('Cliente');
        $response = $this->actingAs($this->administrator())
            ->get(route('admin.client-exports.download', ['group' => 'complete', 'format' => 'xlsx']))
            ->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();

        try {
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestRow());
            $this->assertSame('=1+1', $sheet->getCell('B2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
            $this->assertSame('000123', $sheet->getCell('G2')->getValue());
            $this->assertSame('+584121234567', $sheet->getCell('F2')->getValue());
            $this->assertSame('2', $sheet->getCell('I2')->getValue());
            $this->assertSame('1', $sheet->getCell('J2')->getValue());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $book->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }

    public function test_csv_keeps_unicode_and_quotes_and_neutralizes_formula_prefixes(): void
    {
        User::factory()->create([
            'name' => ' =1+1', 'nombres' => 'María, "José"', 'pay' => 0,
            'passport' => '000999', 'phone' => '+584120000000',
        ])->assignRole('Cliente');
        $body = $this->actingAs($this->administrator())->get(route('admin.client-exports.download', [
            'group' => 'unpaid', 'format' => 'csv',
        ]))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $rows = $this->csvRows($body);
        $this->assertSame("' =1+1", $rows[1][1]);
        $this->assertSame('María, "José"', $rows[1][2]);
        $this->assertSame("'+584120000000", $rows[1][5]);
        $this->assertSame('000999', $rows[1][6]);
    }

    public function test_an_empty_group_still_downloads_the_column_headings(): void
    {
        $body = $this->actingAs($this->administrator())->get(route('admin.client-exports.download', [
            'group' => 'complete', 'format' => 'csv',
        ]))->assertOk()->streamedContent();
        $rows = $this->csvRows($body);
        $this->assertCount(1, $rows);
        $this->assertSame('ID', $rows[0][0]);
    }

    public function test_invalid_groups_and_formats_are_rejected(): void
    {
        $this->actingAs($this->administrator())->getJson(route('admin.client-exports.download', [
            'group' => 'pay>=0', 'format' => 'sql',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['group', 'format']);
    }

    private function administrator(): User
    {
        return User::factory()->create()->syncRoles(['Administrador']);
    }

    private function registrationFixtures(): void
    {
        foreach ([['unpaid', 0, 0], ['paid', 1, 0], ['paid-signed', 1, 1], ['complete', 2, 1],
            ['unsigned', 2, 0], ['other', 3, 1], ['unknown', null, 0]] as [$name, $pay, $contract]) {
            User::factory()->create(['email' => $name.'@example.test', 'pay' => $pay, 'contrato' => $contract])
                ->assignRole('Cliente');
        }
        User::factory()->create(['email' => 'internal@example.test', 'pay' => 0])->syncRoles(['Ventas']);
    }

    private function csvRows(string $body): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($body, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
