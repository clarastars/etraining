<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TraineeDocumentationDate;
use App\Support\TraineeDocumentationDateSheet;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTraineeDocumentationDatesCommand extends Command
{
    protected $signature = 'invoice-details:sync-documentation-dates';

    protected $description = 'Download the joining-date workbook once and store هوية with تاريخ التوثيق';

    public function handle(TraineeDocumentationDateSheet $sheet): int
    {
        $fileId = (string) config('services.google.joining_dates_file_id');
        if ($fileId === '') {
            $this->error('GOOGLE_DRIVE_JOINING_DATES_FILE_ID is not set.');

            return self::FAILURE;
        }

        $path = tempnam(sys_get_temp_dir(), 'documentation-dates').'.xlsx';
        $this->download($fileId, $path);

        try {
            $rows = $sheet->rows($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $syncedAt = now();
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = [
                'identity_number' => $row['identity_number'],
                'documented_on' => $row['documented_on'],
                'source_sheet' => $row['source_sheet'],
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        DB::transaction(function () use ($payload, $syncedAt): void {
            foreach (array_chunk($payload, 500) as $chunk) {
                TraineeDocumentationDate::query()->upsert(
                    $chunk,
                    ['identity_number'],
                    ['documented_on', 'source_sheet', 'synced_at', 'updated_at']
                );
            }

            TraineeDocumentationDate::query()
                ->where(function ($query) use ($syncedAt): void {
                    $query->where('synced_at', '<', $syncedAt)
                        ->orWhereNull('synced_at');
                })
                ->delete();
        });

        $this->info('Stored '.count($payload).' documentation dates.');

        return self::SUCCESS;
    }

    private function download(string $fileId, string $path): void
    {
        $client = new GoogleClient();
        $client->setApplicationName('ETraining');
        $client->setScopes([GoogleDriveService::DRIVE_READONLY]);

        $credentialsPath = storage_path('app/google-drive-credentials.json');
        if (file_exists($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);
        } else {
            $client->setAuthConfig([
                'type' => 'service_account',
                'project_id' => config('services.google.project_id'),
                'private_key_id' => config('services.google.private_key_id'),
                'private_key' => config('services.google.private_key'),
                'client_email' => config('services.google.client_email'),
                'client_id' => config('services.google.client_id'),
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]);
        }

        $drive = new GoogleDriveService($client);
        $response = $drive->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not write the documentation workbook.');
        }

        try {
            $body = $response->getBody();
            while (! $body->eof()) {
                fwrite($handle, $body->read(1024 * 1024));
            }
        } finally {
            fclose($handle);
        }
    }
}
