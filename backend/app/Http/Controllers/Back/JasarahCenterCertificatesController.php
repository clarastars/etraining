<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessJasarahCenterCertificateFinalizeJob;
use App\Models\Back\Course;
use App\Models\Back\JasarahCenterCertificate;
use App\Models\Back\JasarahCenterCertificateRow;
use App\Models\Back\Trainee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class JasarahCenterCertificatesController extends Controller
{
    public function index()
    {
        $imports = JasarahCenterCertificate::with(['course:id,name_ar', 'course.instructor:id,name'])
            ->select('id', 'course_id', 'status', 'total_rows', 'matched_count', 'unmatched_count', 'sent_count', 'failed_count', 'started_at', 'completed_at', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Back/JasarahCenterCertificates/Index', [
            'imports' => $imports,
        ]);
    }

    public function create()
    {
        $courses = Course::select('id', 'name_ar', 'name_en', 'instructor_id', 'created_at')
            ->with('instructor:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        return Inertia::render('Back/JasarahCenterCertificates/Create', [
            'courses' => $courses,
        ]);
    }

    public function uploadCsv(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt',
            'course_id' => 'required|exists:courses,id',
        ]);

        $courseId = $request->input('course_id');
        $csvFile = $request->file('csv');

        $certificate = JasarahCenterCertificate::create([
            'course_id' => $courseId,
            'status' => JasarahCenterCertificate::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $csvS3Path = 'jasarah-center-certificates/' . $certificate->id . '/original.csv';
        Storage::disk('s3')->put($csvS3Path, file_get_contents($csvFile->getPathname()));
        $certificate->update(['csv_path' => $csvS3Path]);

        $matched = [];
        $unmatched = [];
        $totalRows = 0;
        $failedCount = 0;

        $handle = fopen($csvFile->getPathname(), 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Could not read CSV file'], 400);
        }

        $header = null;
        $idIndex = null;
        $nameIndex = null;
        $rowNumber = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($col) => strtolower(trim($col)), $data);
                $idIndex = $this->findColumnIndex($header, ['id', 'رقم الهوية']);
                $nameIndex = $this->findColumnIndex($header, ['name (english)', 'name', 'name english']);

                if ($idIndex === null) {
                    fclose($handle);

                    return response()->json(['error' => 'CSV must contain an ID column'], 400);
                }

                continue;
            }

            $identityNumber = isset($data[$idIndex]) ? trim($data[$idIndex]) : '';
            $traineeNameEn = ($nameIndex !== null && isset($data[$nameIndex])) ? trim($data[$nameIndex]) : '';

            if ($identityNumber === '' && $traineeNameEn === '') {
                continue;
            }

            $rowNumber++;
            $totalRows++;
            $rowKey = $identityNumber . '_' . $rowNumber;

            $identityNumber = $this->convertArabicNumeralsToEnglish($identityNumber);

            if ($identityNumber === '' || !is_numeric($identityNumber)) {
                JasarahCenterCertificateRow::create([
                    'jasarah_center_certificate_id' => $certificate->id,
                    'row_key' => $rowKey,
                    'identity_number' => $identityNumber,
                    'trainee_name_en' => $traineeNameEn,
                    'status' => JasarahCenterCertificateRow::STATUS_FAILED,
                    'error_message' => 'Invalid or missing identity number',
                ]);
                $failedCount++;

                continue;
            }

            $trainee = Trainee::withTrashed()->where('identity_number', $identityNumber)->first();

            if (!$trainee) {
                $arabicIdentity = $this->englishToArabicNumerals($identityNumber);
                $trainee = Trainee::withTrashed()->where('identity_number', $arabicIdentity)->first();
            }

            if ($trainee) {
                JasarahCenterCertificateRow::create([
                    'jasarah_center_certificate_id' => $certificate->id,
                    'trainee_id' => $trainee->id,
                    'row_key' => $rowKey,
                    'identity_number' => $identityNumber,
                    'trainee_name_en' => $traineeNameEn ?: ($trainee->name_en ?? $trainee->name),
                    'status' => JasarahCenterCertificateRow::STATUS_PENDING,
                ]);

                $matched[] = [
                    'row_key' => $rowKey,
                    'id' => $trainee->id,
                    'name' => $trainee->name,
                    'identity_number' => $identityNumber,
                    'email' => $trainee->email,
                    'trainee_name_en' => $traineeNameEn,
                ];
            } else {
                JasarahCenterCertificateRow::create([
                    'jasarah_center_certificate_id' => $certificate->id,
                    'row_key' => $rowKey,
                    'identity_number' => $identityNumber,
                    'trainee_name_en' => $traineeNameEn,
                    'status' => JasarahCenterCertificateRow::STATUS_PENDING,
                ]);

                $unmatched[] = [
                    'row_key' => $rowKey,
                    'identity_number' => $identityNumber,
                    'trainee_name_en' => $traineeNameEn,
                    'searchQuery' => '',
                    'searchResults' => [],
                    'selectedTrainee' => null,
                ];
            }
        }

        fclose($handle);

        $certificate->update([
            'status' => JasarahCenterCertificate::STATUS_COMPLETED,
            'total_rows' => $totalRows,
            'matched_count' => count($matched),
            'unmatched_count' => count($unmatched),
            'failed_count' => $failedCount,
            'completed_at' => now(),
        ]);

        return response()->json([
            'import_id' => $certificate->id,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ]);
    }

    public function finalizeImport(Request $request)
    {
        $request->validate([
            'import_id' => 'required|exists:jasarah_center_certificates,id',
        ]);

        $certificate = JasarahCenterCertificate::with('course')->findOrFail($request->input('import_id'));
        $mappings = $request->input('mappings', []);

        foreach ($mappings as $mapping) {
            $row = JasarahCenterCertificateRow::where('jasarah_center_certificate_id', $certificate->id)
                ->where('row_key', $mapping['row_key'])
                ->first();

            if ($row && !$row->trainee_id) {
                $trainee = Trainee::withTrashed()->find($mapping['trainee_id']);
                if ($trainee) {
                    $row->update([
                        'trainee_id' => $trainee->id,
                        'identity_number' => $trainee->identity_number ?? $row->identity_number,
                    ]);
                }
            }
        }

        $matchedCount = $certificate->rows()->whereNotNull('trainee_id')->count();
        $unmatchedCount = $certificate->rows()
            ->whereNull('trainee_id')
            ->where('status', '!=', JasarahCenterCertificateRow::STATUS_FAILED)
            ->count();
        $failedCount = $certificate->rows()->where('status', JasarahCenterCertificateRow::STATUS_FAILED)->count();

        if ($unmatchedCount > 0) {
            $certificate->update([
                'matched_count' => $matchedCount,
                'unmatched_count' => $unmatchedCount,
                'failed_count' => $failedCount,
            ]);

            return response()->json(['error' => 'All missing trainees must be linked before processing PDFs'], 422);
        }

        $certificate->update([
            'status' => JasarahCenterCertificate::STATUS_PROCESSING,
            'matched_count' => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'failed_count' => $failedCount,
        ]);

        dispatch(new ProcessJasarahCenterCertificateFinalizeJob($certificate->id));

        return response()->json(['success' => true]);
    }

    public function sendEmails(Request $request)
    {
        $request->validate([
            'import_id' => 'required|exists:jasarah_center_certificates,id',
        ]);

        $certificate = JasarahCenterCertificate::findOrFail($request->input('import_id'));

        if ($certificate->status !== JasarahCenterCertificate::STATUS_READY_TO_SEND) {
            return response()->json(['error' => 'Import is not ready to send'], 422);
        }

        $certificate->update([
            'status' => JasarahCenterCertificate::STATUS_SENDING,
        ]);

        dispatch(new SendJasarahCenterCertificateJob($certificate));

        return response()->json(['success' => true]);
    }

    public function downloadCertificate($row_id)
    {
        $row = JasarahCenterCertificateRow::findOrFail($row_id);

        if ($row->pdf_path && Storage::disk('s3')->exists($row->pdf_path)) {
            $pdfContent = Storage::disk('s3')->get($row->pdf_path);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="notice-of-attendance-' . $row->identity_number . '.pdf"');
        }

        abort(404, 'Certificate not found');
    }

    public function showProcessing($importId)
    {
        $import = JasarahCenterCertificate::with(['course:id,name_ar', 'rows'])->findOrFail($importId);

        return Inertia::render('Back/JasarahCenterCertificates/Processing', [
            'import' => $import,
        ]);
    }

    public function getProcessingStatus($importId)
    {
        $certificate = JasarahCenterCertificate::with(['rows' => function ($query) {
            $query->select(
                'id',
                'jasarah_center_certificate_id',
                'status',
                'trainee_id',
                'identity_number',
                'trainee_name_en',
                'row_key',
                'error_message',
                'delivery_status',
                'delivered_at',
                'failed_at',
                'delivery_failure_reason',
                'mailgun_message_id',
                'pdf_path',
                'sent_at'
            );
        }, 'rows.trainee:id,name,email'])->findOrFail($importId);

        $matched = $certificate->rows->where('trainee_id', '!=', null)->map(function ($row) {
            return [
                'id' => $row->trainee_id,
                'row_id' => $row->id,
                'name' => $row->trainee->name ?? 'Unknown',
                'email' => $row->trainee->email ?? '',
                'identity_number' => $row->identity_number,
                'trainee_name_en' => $row->trainee_name_en,
                'row_key' => $row->row_key,
                'has_pdf' => !empty($row->pdf_path),
                'status' => $row->status,
                'delivery_status' => $row->delivery_status,
                'delivered_at' => $row->delivered_at,
                'failed_at' => $row->failed_at,
                'delivery_failure_reason' => $row->delivery_failure_reason,
                'mailgun_message_id' => $row->mailgun_message_id,
                'sent_at' => $row->sent_at,
            ];
        })->values();

        $unmatched = $certificate->rows->where('trainee_id', null)->where('status', '!=', JasarahCenterCertificateRow::STATUS_FAILED)->map(function ($row) {
            return [
                'row_key' => $row->row_key,
                'identity_number' => $row->identity_number,
                'trainee_name_en' => $row->trainee_name_en,
                'status' => $row->status,
                'searchQuery' => '',
                'searchResults' => [],
                'selectedTrainee' => null,
            ];
        })->values();

        $failed = $certificate->rows->where('status', JasarahCenterCertificateRow::STATUS_FAILED)->map(function ($row) {
            return [
                'row_key' => $row->row_key,
                'identity_number' => $row->identity_number,
                'trainee_name_en' => $row->trainee_name_en,
                'error_message' => $row->error_message,
                'status' => $row->status,
            ];
        })->values();

        return response()->json([
            'import_id' => $certificate->id,
            'status' => $certificate->status,
            'total_rows' => $certificate->total_rows ?? 0,
            'matched_count' => $certificate->matched_count ?? 0,
            'unmatched_count' => $certificate->unmatched_count ?? 0,
            'failed_count' => $certificate->failed_count ?? 0,
            'started_at' => $certificate->started_at,
            'completed_at' => $certificate->completed_at,
            'course_name' => $certificate->course->name_ar ?? 'Unknown Course',
            'matched' => $matched,
            'unmatched' => $unmatched,
            'failed' => $failed,
        ]);
    }

    public function downloadDeliveryReport($importId)
    {
        $certificate = JasarahCenterCertificate::with(['course:id,name_ar', 'rows.trainee:id,name,email'])->findOrFail($importId);
        $rows = $certificate->rows()->with('trainee')->get();

        $reportData = [];
        foreach ($rows as $row) {
            $reportData[] = [
                'row_key' => $row->row_key,
                'trainee_name_en' => $row->trainee_name_en,
                'identity_number' => $row->identity_number,
                'trainee_email' => $row->trainee->email ?? 'N/A',
                'status' => $row->status,
                'delivery_status' => $row->delivery_status,
                'sent_at' => $row->sent_at ? $row->sent_at->format('Y-m-d H:i:s') : 'N/A',
                'delivered_at' => $row->delivered_at ? $row->delivered_at->format('Y-m-d H:i:s') : 'N/A',
                'failed_at' => $row->failed_at ? $row->failed_at->format('Y-m-d H:i:s') : 'N/A',
                'delivery_failure_reason' => $row->delivery_failure_reason ?? 'N/A',
                'mailgun_message_id' => $row->mailgun_message_id ?? 'N/A',
            ];
        }

        $filename = 'jasarah_center_certificate_delivery_report_' . $certificate->id . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($reportData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Row Key',
                'Name (English)',
                'Identity Number',
                'Trainee Email',
                'Status',
                'Delivery Status',
                'Sent At',
                'Delivered At',
                'Failed At',
                'Delivery Failure Reason',
                'Mailgun Message ID',
            ]);
            foreach ($reportData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function delete($importId)
    {
        try {
            $certificate = JasarahCenterCertificate::findOrFail($importId);

            if ($certificate->status === JasarahCenterCertificate::STATUS_PROCESSING) {
                $certificate->update([
                    'status' => JasarahCenterCertificate::STATUS_CANCELLED,
                    'completed_at' => now(),
                ]);
            }

            JasarahCenterCertificateRow::where('jasarah_center_certificate_id', $certificate->id)->delete();
            $certificate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Import and all associated files have been deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete Jasarah Center certificate import', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete import: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function findColumnIndex(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search(strtolower($candidate), $header, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function convertArabicNumeralsToEnglish(string $text): string
    {
        $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicNumerals, $englishNumerals, $text);
    }

    private function englishToArabicNumerals(string $input): string
    {
        $numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        return str_replace($numbers, $arabicNumbers, $input);
    }
}
