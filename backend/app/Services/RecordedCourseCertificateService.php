<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Support\Facades\Storage;
use PDF;

class RecordedCourseCertificateService
{
    public function generateAndStore(RecordedCourseEnrollment $enrollment): string
    {
        $enrollment->loadMissing([
            'trainee',
            'recordedCourse' => function ($q): void {
                $q->withoutGlobalScopes();
            },
        ]);

        /** @var RecordedCourse $course */
        $course = $enrollment->recordedCourse;
        $trainee = $enrollment->trainee;

        $pdf = PDF::setOption('margin-bottom', 30)
            ->setOption('page-size', 'A4')
            ->setOption('orientation', 'landscape')
            ->setOption('encoding', 'utf-8')
            ->setOption('dpi', 300)
            ->setOption('image-dpi', 300)
            ->setOption('lowquality', false)
            ->setOption('no-background', false)
            ->setOption('enable-internal-links', true)
            ->setOption('enable-external-links', true)
            ->setOption('javascript-delay', 1000)
            ->setOption('no-stop-slow-scripts', true)
            ->setOption('margin-right', 0)
            ->setOption('margin-left', 0)
            ->setOption('margin-top', 0)
            ->setOption('margin-bottom', 0)
            ->setOption('disable-smart-shrinking', true)
            ->setOption('viewport-size', '1024×768')
            ->setOption('zoom', 0.78)
            ->loadView('pdf.recorded-course-certificate.show', [
                'traineeName' => $trainee->name ?? '',
                'identityNumber' => $trainee->identity_number ?? '',
                'courseName' => $course->name_ar ?: $course->name_en,
            ]);

        $relativePath = 'recorded-course-certificates/'.$enrollment->id.'.pdf';
        Storage::disk('local')->put($relativePath, $pdf->output());

        return $relativePath;
    }

    public function pdfBinary(RecordedCourseEnrollment $enrollment): string
    {
        if ($enrollment->certificate_path && Storage::disk('local')->exists($enrollment->certificate_path)) {
            return Storage::disk('local')->get($enrollment->certificate_path);
        }

        $path = $this->generateAndStore($enrollment);
        $enrollment->certificate_path = $path;
        $enrollment->save();

        return Storage::disk('local')->get($path);
    }
}
