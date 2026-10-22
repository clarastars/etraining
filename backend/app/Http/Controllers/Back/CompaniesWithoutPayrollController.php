<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Exports\CompaniesWithoutPayrollExport;
use App\Http\Controllers\Controller;
use App\Services\CompaniesWithoutPayrollReportService;
use Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompaniesWithoutPayrollController extends Controller
{
    public function __construct(private CompaniesWithoutPayrollReportService $reports)
    {
        $this->middleware('can:view-backoffice-reports');
    }

    public function index(Request $request)
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $rows = [];
        if ($request->filled(['date_from', 'date_to'])) {
            $validated = $this->validateFilters($request);
            $rows = $this->reports->rows($validated['date_from'], $validated['date_to']);
        }

        return Inertia::render('Back/Finance/CompaniesWithoutPayroll', [
            'filters' => $filters,
            'rows' => $rows,
        ]);
    }

    public function excel(Request $request)
    {
        $validated = $this->validateFilters($request);
        $rows = $this->reports->rows($validated['date_from'], $validated['date_to']);

        return Excel::download(
            new CompaniesWithoutPayrollExport($rows),
            'companies-without-payroll.xlsx'
        );
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);
    }
}
