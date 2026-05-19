<?php

namespace App\Jobs;

use App\Models\Back\Trainee;
use App\Models\JobTracker;
use App\Notifications\ReportCompletedNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class TraineesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 7200;

    public $tracker;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\JobTracker $tracker
     */
    public function __construct(JobTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception|\Throwable
     */
    public function handle()
    {
        Log::info('[TraineesReportJob] Started. Tracker ID: '.$this->tracker->id);
        $this->tracker->update(['started_at' => now()]);

        $startTime = microtime(true);
        $maxExecutionTime = 7000; // 7000 seconds (just under 2 hours)

        try {
            $filters = $this->tracker->metadata ?? [];

            $query = !empty($filters['deleted_mark'])
                ? Trainee::onlyTrashed()
                : Trainee::withTrashed();

            // Apply filters
            if (!empty($filters['age_under'])) {
                $dateThreshold = Carbon::now()->subYears($filters['age_under']);
                $query->where('birthday', '>', $dateThreshold);
            }

            if (!empty($filters['has_invoices'])) {
                if ($filters['has_invoices'] === 'yes') {
                    $query->whereHas('invoices');
                } else {
                    $query->whereDoesntHave('invoices');
                }
            }

            if (!empty($filters['assigned_to_company'])) {
                if ($filters['assigned_to_company'] === 'yes') {
                    $query->whereNotNull('company_id');
                } else {
                    $query->whereNull('company_id');
                }
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['phone_is_owned'])) {
                $isOwned = $filters['phone_is_owned'] === 'true';
                $query->where('phone_is_owned', $isOwned);
            }

            if (!empty($filters['educational_level_id'])) {
                $query->where('educational_level_id', $filters['educational_level_id']);
            }

            if (!empty($filters['deleted_mark'])) {
                $query->where('deleted_remark', $filters['deleted_mark']);
            }

            // Get total count for progress tracking
            $totalCount = $query->count();
            Log::info("[TraineesReportJob] Total trainees to process: {$totalCount}");

            // Update tracker with total count
            $this->tracker->update([
                'total_records' => $totalCount,
                'processed_records' => 0,
                'progress_percentage' => 0
            ]);

            // Create Excel file
            $fileName = $this->generateExcel($query, $totalCount, $startTime, $maxExecutionTime);

            $executionTime = microtime(true) - $startTime;
            Log::info('[TraineesReportJob] Report generated in ' . $executionTime . ' seconds');

            // Save to media collection
            $this->tracker->addMedia(storage_path('app/'.$fileName))
                ->withAttributes([
                    'team_id' => $this->tracker->team_id,
                ])->toMediaCollection('excel');

            $this->tracker->update([
                'finished_at' => now(),
                'progress_percentage' => 100
            ]);
            
            // Send notification
            if ($this->tracker->user_id && $user = \App\Models\User::find($this->tracker->user_id)) {
                $user->notify(new ReportCompletedNotification($this->tracker));
            }

            Log::info('[TraineesReportJob] Completed. Tracker ID: '.$this->tracker->id);
        } catch (Throwable $e) {
            $this->failed($e);
        }
    }

    /**
     * Generate Excel file from query
     */
    private function generateExcel($query, $totalCount, $startTime, $maxExecutionTime)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers - specific columns requested by user in Arabic
        $headers = [
            'الاسم',
            'البريد الإلكتروني',
            'رقم الهوية',
            'رقم الجوال',
            'تاريخ الميلاد',
            'المستوى التعليمي',
            'المدينة',
            'الحالة الاجتماعية',
            'عدد الأطفال',
            'اسم الشركة',
            'تاريخ الحذف',
            'ملاحظة الإيقاف',
            'تاريخ الإيقاف',
            'تاريخ الإنشاء',
            'الحالة',
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style headers with RTL support
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E2E8F0']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

        // Set RTL direction for the entire sheet
        $sheet->setRightToLeft(true);

        // Process data in chunks for memory efficiency
        $chunkSize = 100;
        $processed = 0;
        $row = 2;

        // Load relationships for text values
        $query->with(['educational_level', 'city', 'marital_status', 'company']);

        $query->chunk($chunkSize, function ($trainees) use ($sheet, &$row, &$processed, $totalCount, $startTime, $maxExecutionTime) {
            try {
                foreach ($trainees as $trainee) {
                    // Map status to Arabic text
                    $statusText = '';
                    switch ($trainee->status) {
                        case Trainee::STATUS_PENDING_UPLOADING_FILES:
                            $statusText = 'في انتظار رفع الملفات';
                            break;
                        case Trainee::STATUS_PENDING_APPROVAL:
                            $statusText = 'في انتظار الموافقة';
                            break;
                        case Trainee::STATUS_APPROVED:
                            $statusText = 'تمت الموافقة';
                            break;
                        default:
                            $statusText = 'غير معروف';
                            break;
                    }

                    // Extract data with text values for relationships
                    $data = [
                        $trainee->name,
                        $trainee->email,
                        $trainee->identity_number,
                        $trainee->phone,
                        $trainee->birthday,
                        optional($trainee->educational_level)->name_ar ?? '',
                        optional($trainee->city)->name_ar ?? '',
                        optional($trainee->marital_status)->name_ar ?? '',
                        $trainee->children_count,
                        optional($trainee->company)->name_ar ?? '',
                        $trainee->deleted_at,
                        $trainee->deleted_remark ?? '',
                        $trainee->suspended_at,
                        $trainee->created_at,
                        $statusText,
                    ];
                    
                    $sheet->fromArray($data, null, 'A' . $row);
                    
                    // Apply RTL alignment to data rows
                    $sheet->getStyle('A' . $row . ':O' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    
                    $row++;
                    $processed++;
                }
                
                // Update real progress in database
                $progressPercentage = $totalCount > 0 ? round(($processed / $totalCount) * 100, 2) : 0;
                
                $this->tracker->update([
                    'processed_records' => $processed,
                    'progress_percentage' => $progressPercentage
                ]);
                
                Log::info("[TraineesReportJob] Progress: {$processed}/{$totalCount} ({$progressPercentage}%)");
                
                // Check for timeout
                if ((microtime(true) - $startTime) > $maxExecutionTime) {
                    throw new \Exception("Job execution time exceeded maximum allowed time of {$maxExecutionTime} seconds");
                }
            } catch (Throwable $e) {
                Log::error("[TraineesReportJob] Error processing chunk: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                throw $e;
            }
        });

        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Save file
        $fileName = 'trainees_report_' . now()->format('Y_m_d_H_i_s') . '.xlsx';
        $filePath = storage_path('app/' . $fileName);
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $fileName;
    }



    /**
     * Handle job failure.
     *
     * @param Throwable $e
     */
    public function failed(Throwable $e)
    {
        Log::error('[TraineesReportJob] Failed. Tracker ID: ' . $this->tracker->id . ' | Error: ' . $e->getMessage());
        Log::error($e->getTraceAsString());

        $this->tracker->update([
            'failure_reason' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
} 