<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Exports\InvoiceDetailsSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Back\Company;
use App\Models\Back\Invoice;
use App\Services\InvoiceDetailReportService;
use Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PDF;

class InvoiceDetailReportController extends Controller
{
    public function __construct(private InvoiceDetailReportService $reports)
    {
        $this->middleware('can:view-financial-invoices-details');
    }

    public function index(Request $request)
    {
        $filters = [
            'company_id' => $request->input('company_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $rows = [];
        if ($request->filled(['company_id', 'date_from', 'date_to'])) {
            $validated = $this->validateFilters($request);
            $rows = $this->reports->rows($validated['company_id'], $validated['date_from'], $validated['date_to']);
        }

        return Inertia::render('Back/Finance/Invoices/Details', [
            'company' => $this->selectedCompany($request->input('company_id')),
            'filters' => $filters,
            'rows' => $rows,
        ]);
    }

    public function companies(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $search = trim((string) $request->input('search', ''));

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Company::query()
                ->select(['id', 'name_ar', 'name_en', 'code'])
                ->where(function ($query) use ($search) {
                    $query->where('name_ar', 'LIKE', '%'.$search.'%')
                        ->orWhere('name_en', 'LIKE', '%'.$search.'%')
                        ->orWhere('code', $search);
                })
                ->orderBy('name_ar')
                ->limit(15)
                ->get()
        );
    }

    public function update(Request $request, string $invoice)
    {
        $invoiceModel = Invoice::query()->findOrFail($invoice);

        $validated = $request->validate([
            'field' => 'required|in:'.implode(',', InvoiceDetailReportService::EDITABLE_FIELDS),
            'value' => 'nullable',
        ]);

        if (in_array($validated['field'], ['manual_start_date', 'end_date'], true) && $validated['value'] !== null && $validated['value'] !== '') {
            $request->validate([
                'value' => 'date',
            ]);
        }

        if (in_array($validated['field'], ['full_salary', 'full_reward', 'full_fees', 'full_refund'], true) && $validated['value'] !== null && $validated['value'] !== '') {
            $request->validate([
                'value' => 'numeric|min:0',
            ]);
        }

        return response()->json(
            $this->reports->saveCell($invoiceModel, $validated['field'], $validated['value'] ?? null)
        );
    }

    public function excel(Request $request)
    {
        $validated = $this->validateFilters($request);

        return Excel::download(
            new InvoiceDetailsSheetExport($this->reports->rows($validated['company_id'], $validated['date_from'], $validated['date_to'])),
            'invoice-details-report.xlsx'
        );
    }

    public function pdf(Request $request)
    {
        $validated = $this->validateFilters($request);
        $company = Company::query()->findOrFail($validated['company_id']);

        return PDF::loadView('pdf.finance.invoice-details', [
            'rows' => $this->reports->rows($validated['company_id'], $validated['date_from'], $validated['date_to']),
            'companyName' => $company->name_ar,
            'dateFrom' => $validated['date_from'],
            'dateTo' => $validated['date_to'],
        ])->setPaper('a3', 'landscape')->download('invoice-details-report.pdf');
    }

    private function selectedCompany(?string $companyId): ?Company
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }

        return Company::query()
            ->select(['id', 'name_ar', 'name_en', 'code'])
            ->find($companyId);
    }

    /**
     * @return array{company_id: string, date_from: string, date_to: string}
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);
    }
}
