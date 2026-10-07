<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Back\Company;
use App\Models\Back\CompanyContract;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\GosiEmployeeData;
use App\Models\User;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class InvoiceDetailReportTest extends TestCase
{
    use WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Company::disableSearchSyncing();
        CompanyContract::disableSearchSyncing();

        $this->user = (new CreateNewUser())->create([
            'name' => 'Shafiq al-Shaar',
            'email' => 'hello@getShafiq.com',
            'password' => 'hello123123',
            'password_confirmation' => 'hello123123',
        ]);

        $this->user->forceFill([
            'current_team_id' => $this->user->personalTeam()->id,
        ])->save();

        $this->actingAs($this->user);
    }

    public function test_company_search_returns_matches_instead_of_every_company(): void
    {
        $match = $this->makeCompany('1010111222');
        $match->name_ar = 'شركة البحث الخاصة';
        $match->save();
        $this->makeCompany('1010333444');

        $this->get(route('back.finance.invoices.details.companies', ['search' => 'البحث']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $match->id,
                'name_ar' => 'شركة البحث الخاصة',
            ]);

        $this->get(route('back.finance.invoices.details.companies', ['search' => 'ش']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_route_rejects_users_without_permission(): void
    {
        $role = $this->user->roles()->first();
        $role->revokePermissionTo('view-financial-invoices-details');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('back.finance.invoices.details'))
            ->assertForbidden();
    }

    public function test_sheet_uses_cached_masdr_date_and_saves_manual_inputs(): void
    {
        $company = $this->makeCompany('1010999888');
        $trainee = $this->makeTrainee($company, '1108800093', 1500);
        CompanyContract::factory()->create([
            'company_id' => $company->id,
            'team_id' => $this->user->current_team_id,
            'trainee_salary' => 2750,
            'contract_starts_at' => '2024-01-01',
            'contract_ends_at' => '2025-01-01',
            'contract_period_in_months' => 12,
        ]);

        GosiEmployeeData::create([
            'nin_or_iqama' => '1108800093',
            'data' => [
                'employmentStatusInfo' => [
                    [
                        'commercialRegistrationNumber' => '5550001111',
                        'dateOfJoining' => '2019-01-01',
                        'employmentStatus' => 'نشيط',
                    ],
                    [
                        'commercialRegistrationNumber' => '1010999888',
                        'dateOfJoining' => '2020-01-15',
                        'salaryStartingDate' => '2021-02-02',
                        'employmentStatus' => 'نشيط',
                    ],
                ],
            ],
        ]);

        $invoice = $this->makeInvoice($company, $trainee, '2026-05-01', Invoice::STATUS_UNPAID);
        $this->makeInvoice($company, $trainee, '2026-01-01', Invoice::STATUS_PAID);

        $filters = [
            'company_id' => $company->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ];

        $this->get(route('back.finance.invoices.details', $filters))
            ->assertSuccessful()
            ->assertPropValue('rows', function (array $rows) {
                $this->assertCount(1, $rows);
                $this->assertSame('2020-01-15', $rows[0]['masdr_start_date']);
                $this->assertSame(__('words.unpaid'), $rows[0]['status']);
                $this->assertEquals(2750, $rows[0]['full_salary']);
                $this->assertEquals(1500, $rows[0]['full_reward']);
                $this->assertNull($rows[0]['full_fees']);
                $this->assertNull($rows[0]['full_refund']);
                $this->assertNull($rows[0]['day_count']);
                $this->assertNull($rows[0]['salary_due']);
            });

        $this->patchJson(route('back.finance.invoices.details.update', $invoice->id), [
            'field' => 'manual_start_date',
            'value' => '2026-05-14',
        ])->assertOk();

        $this->patchJson(route('back.finance.invoices.details.update', $invoice->id), [
            'field' => 'end_date',
            'value' => '2026-05-30',
        ])->assertOk();

        $this->get(route('back.finance.invoices.details', $filters))
            ->assertSuccessful()
            ->assertPropValue('rows', function (array $rows) {
                $this->assertSame('2020-01-15', $rows[0]['masdr_start_date']);
                $this->assertSame('2026-05-14', $rows[0]['manual_start_date']);
                $this->assertSame('2026-05-30', $rows[0]['end_date']);
                $this->assertSame(17, $rows[0]['day_count']);
                $this->assertEqualsWithDelta(2750 / 30 * 17, $rows[0]['salary_due'], 0.001);
                $this->assertNotSame($rows[0]['masdr_start_date'], $rows[0]['manual_start_date']);
            });

        $this->patchJson(route('back.finance.invoices.details.update', $invoice->id), [
            'field' => 'full_salary',
            'value' => null,
        ])->assertOk();

        $this->get(route('back.finance.invoices.details', $filters))
            ->assertSuccessful()
            ->assertPropValue('rows', function (array $rows) {
                $this->assertNull($rows[0]['full_salary']);
                $this->assertNull($rows[0]['salary_due']);
                $this->assertSame(17, $rows[0]['day_count']);
            });
    }

    public function test_masdr_date_stays_empty_when_company_registration_does_not_match(): void
    {
        $company = $this->makeCompany('1010999888');
        $trainee = $this->makeTrainee($company, '1108800093', null);
        GosiEmployeeData::create([
            'nin_or_iqama' => '1108800093',
            'data' => [
                'employmentStatusInfo' => [
                    [
                        'commercialRegistrationNumber' => '5550001111',
                        'dateOfJoining' => '2019-01-01',
                        'employmentStatus' => 'نشيط',
                    ],
                ],
            ],
        ]);
        $this->makeInvoice($company, $trainee, '2026-05-01', Invoice::STATUS_PAID);

        $this->get(route('back.finance.invoices.details', [
            'company_id' => $company->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))->assertSuccessful()
            ->assertPropValue('rows', function (array $rows) {
                $this->assertNull($rows[0]['masdr_start_date']);
                $this->assertNull($rows[0]['day_count']);
            });
    }

    public function test_excel_export_keeps_days360_formulas(): void
    {
        $company = $this->makeCompany('1010999888');
        $trainee = $this->makeTrainee($company, '1108800093', null);
        $invoice = $this->makeInvoice($company, $trainee, '2026-05-01', Invoice::STATUS_PAID);

        $this->patchJson(route('back.finance.invoices.details.update', $invoice->id), [
            'field' => 'manual_start_date',
            'value' => '2026-05-14',
        ]);
        $this->patchJson(route('back.finance.invoices.details.update', $invoice->id), [
            'field' => 'end_date',
            'value' => '2026-05-30',
        ]);

        $response = $this->get(route('back.finance.invoices.details.excel', [
            'company_id' => $company->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));

        $response->assertOk();
        $binary = $response->baseResponse;
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $binary);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($binary->getFile()->getPathname()) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('DAYS360', $xml);
        $this->assertStringContainsString('SUM(C2:C2)', $xml);
        $this->assertStringContainsString('SUM(N2:N2)', $xml);
        $this->assertStringContainsString('SUM(W2:W2)', $xml);
    }

    public function test_pdf_export_uses_the_saved_sheet(): void
    {
        $company = $this->makeCompany('1010999888');
        $trainee = $this->makeTrainee($company, '1108800093', null);
        $this->makeInvoice($company, $trainee, '2026-05-01', Invoice::STATUS_PAID);

        $wrapper = \Mockery::mock(\Barryvdh\Snappy\PdfWrapper::class);
        $wrapper->shouldReceive('setPaper')->once()->with('a3', 'landscape')->andReturnSelf();
        $wrapper->shouldReceive('download')->once()->andReturn(response('pdf', 200, [
            'Content-Type' => 'application/pdf',
        ]));

        SnappyPdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.finance.invoice-details', \Mockery::on(function (array $data) {
                return count($data['rows']) === 1;
            }))
            ->andReturn($wrapper);

        $this->get(route('back.finance.invoices.details.pdf', [
            'company_id' => $company->id,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))->assertOk();
    }

    private function makeCompany(string $crNumber): Company
    {
        return Company::factory()->create([
            'team_id' => $this->user->current_team_id,
            'cr_number' => $crNumber,
            'name_ar' => 'شركة الاختبار',
            'shelf_number' => '1',
        ]);
    }

    private function makeTrainee(Company $company, string $identity, ?float $reward): Trainee
    {
        $trainee = Trainee::factory()->create([
            'team_id' => $this->user->current_team_id,
            'company_id' => $company->id,
            'name' => 'ياسمين',
            'identity_number' => $identity,
        ]);
        $trainee->platform_reward = $reward;
        $trainee->save();

        return $trainee;
    }

    private function makeInvoice(Company $company, Trainee $trainee, string $fromDate, int $status): Invoice
    {
        $invoice = new Invoice([
            'company_id' => $company->id,
            'trainee_id' => $trainee->id,
            'from_date' => $fromDate,
            'to_date' => $fromDate,
            'sub_total' => 304.35,
            'tax' => 45.65,
            'grand_total' => 350,
            'status' => $status,
        ]);
        $invoice->objection_of_amount = 0;
        $invoice->save();

        return $invoice;
    }
}
