<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Back\Company;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\TraineeDocumentationDate;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompaniesWithoutPayrollReportTest extends TestCase
{
    use WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Company::disableSearchSyncing();

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

    public function test_route_rejects_users_without_permission(): void
    {
        $role = $this->user->roles()->first();
        $role->revokePermissionTo('view-backoffice-reports');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('back.finance.companies-without-payroll'))
            ->assertForbidden();
    }

    public function test_lists_companies_with_start_dates_and_no_invoices_in_period(): void
    {
        $missing = $this->makeCompany('شركة بدون رواتب');
        $trainee = $this->makeTrainee($missing, '1104839079');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1104839079',
            'documented_on' => '2026-04-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);
        $oldInvoice = $this->makeInvoice($missing, $trainee, '2026-03-01');
        $oldInvoice->forceFill(['created_at' => '2026-03-10 12:00:00'])->save();

        $paid = $this->makeCompany('شركة لديها فواتير');
        $paidTrainee = $this->makeTrainee($paid, '1108800093');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1108800093',
            'documented_on' => '2026-03-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);
        $this->makeInvoice($paid, $paidTrainee, '2026-05-01');

        $tooLate = $this->makeCompany('شركة مباشرة لاحقة');
        $this->makeTrainee($tooLate, '1079494041');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1079494041',
            'documented_on' => '2026-06-15',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);

        $this->get(route('back.finance.companies-without-payroll', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Back/Finance/CompaniesWithoutPayroll')
                ->has('rows', 1)
                ->where('rows.0.company_id', $missing->id)
                ->where('rows.0.trainees_count', 1)
                ->where('rows.0.earliest_start_date', '2026-04-01')
                ->where('rows.0.last_invoice_at', '2026-03-10')
                ->where('rows.0.missing_months_count', 1)
                ->where('rows.0.months.0.key', '2026-05')
                ->where('rows.0.months.0.label', '05/2026')
                ->where('rows.0.months.0.has_invoice', false)
                ->where('rows.0.trainees.0.trainee_id', $trainee->id)
            );
    }

    public function test_orders_companies_from_worst_to_best_with_month_coverage(): void
    {
        $worst = $this->makeCompany('أ أسوأ شركة');
        $worstTrainee = $this->makeTrainee($worst, '1104839079');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1104839079',
            'documented_on' => '2026-01-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);

        $partial = $this->makeCompany('ب شركة جزئية');
        $partialTrainee = $this->makeTrainee($partial, '1108800093');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1108800093',
            'documented_on' => '2026-01-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);
        $this->makeInvoice($partial, $partialTrainee, '2026-04-10');

        $complete = $this->makeCompany('ج شركة مكتملة');
        $completeTrainee = $this->makeTrainee($complete, '1079494041');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1079494041',
            'documented_on' => '2026-01-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);
        $this->makeInvoice($complete, $completeTrainee, '2026-04-01');
        $this->makeInvoice($complete, $completeTrainee, '2026-05-01');
        $this->makeInvoice($complete, $completeTrainee, '2026-06-01');

        $this->get(route('back.finance.companies-without-payroll', [
            'date_from' => '2026-04-01',
            'date_to' => '2026-06-30',
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Back/Finance/CompaniesWithoutPayroll')
                ->has('rows', 2)
                ->where('rows.0.company_id', $worst->id)
                ->where('rows.0.missing_months_count', 3)
                ->where('rows.0.months.0.has_invoice', false)
                ->where('rows.0.months.1.has_invoice', false)
                ->where('rows.0.months.2.has_invoice', false)
                ->where('rows.1.company_id', $partial->id)
                ->where('rows.1.missing_months_count', 2)
                ->where('rows.1.months.0.has_invoice', true)
                ->where('rows.1.months.0.label', '04/2026')
                ->where('rows.1.months.1.has_invoice', false)
                ->where('rows.1.months.2.has_invoice', false)
            );
    }

    public function test_excel_export_downloads_for_matching_companies(): void
    {
        $company = $this->makeCompany('شركة بدون رواتب');
        $this->makeTrainee($company, '1104839079');
        TraineeDocumentationDate::query()->create([
            'identity_number' => '1104839079',
            'documented_on' => '2026-04-01',
            'source_sheet' => '2026',
            'synced_at' => now(),
        ]);

        $this->get(route('back.finance.companies-without-payroll.excel', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))->assertOk();
    }

    private function makeCompany(string $nameAr): Company
    {
        return Company::factory()->create([
            'team_id' => $this->user->current_team_id,
            'name_ar' => $nameAr,
            'shelf_number' => '1',
        ]);
    }

    private function makeTrainee(Company $company, string $identity): Trainee
    {
        return Trainee::factory()->create([
            'team_id' => $this->user->current_team_id,
            'company_id' => $company->id,
            'name' => 'ياسمين',
            'identity_number' => $identity,
        ]);
    }

    private function makeInvoice(Company $company, Trainee $trainee, string $fromDate): Invoice
    {
        $invoice = new Invoice([
            'company_id' => $company->id,
            'trainee_id' => $trainee->id,
            'from_date' => $fromDate,
            'to_date' => $fromDate,
            'sub_total' => 304.35,
            'tax' => 45.65,
            'grand_total' => 350,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        $invoice->objection_of_amount = 0;
        $invoice->save();

        return $invoice;
    }
}
