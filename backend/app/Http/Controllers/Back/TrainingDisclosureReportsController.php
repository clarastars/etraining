<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Exports\TrainingDisclosureCompanyReportExport;
use App\Http\Controllers\Controller;
use App\Services\TrainingDisclosureCompanyReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingDisclosureReportsController extends Controller
{
    public function index(Request $request, TrainingDisclosureCompanyReportService $reports): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $built = $reports->build($this->selectedCompanyId($request));

        return Inertia::render('Back/TrainingDisclosure/Report', [
            'companies' => $built['companies'],
            'selectedCompany' => $built['selected'],
            'rows' => $built['rows'],
            'summary' => $built['summary'],
        ]);
    }

    public function export(Request $request, TrainingDisclosureCompanyReportService $reports): BinaryFileResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $built = $reports->build($this->selectedCompanyId($request));
        $selected = $built['selected'];
        abort_unless(is_array($selected), 404);

        $name = app()->getLocale() === 'en'
            ? ($selected['name_en'] ?: $selected['name_ar'])
            : ($selected['name_ar'] ?: $selected['name_en']);

        return Excel::download(
            new TrainingDisclosureCompanyReportExport(
                $reports->spreadsheetHeadings(),
                $reports->spreadsheetRows($built['rows'])
            ),
            'training-disclosure-'.now()->format('Y-m-d').'-'.$this->filenameSlug((string) $name).'.xlsx'
        );
    }

    private function selectedCompanyId(Request $request): ?string
    {
        $selectedId = $request->query('company');

        return is_string($selectedId) && $selectedId !== '' ? $selectedId : null;
    }

    private function filenameSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? '', '-'));

        return $slug !== '' ? $slug : 'company';
    }
}
