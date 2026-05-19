<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\CompanyAttendanceFailureMail;
use App\Models\Back\Company;
use App\Models\Back\CompanyAttendanceReport;
use App\Models\Back\CompanyMail;
use App\Models\Back\JasarahCenterCertificateRow;
use App\Models\Back\UkCertificateRow;
use App\Services\CompaniesService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Mail;

class MailController extends Controller
{
    public function store(Request $request)
    {
        // Debug: Log raw webhook data
        Log::info('Mailgun Webhook Raw Data', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'raw_content' => $request->getContent(),
            'timestamp' => now()->toISOString(),
        ]);

        $eventData = $request->input('event-data');

        // Debug: Log parsed event data
        Log::info('Mailgun Webhook Event Data', [
            'event_data' => $eventData,
            'event_type' => $eventData['event'] ?? 'unknown',
            'timestamp' => now()->toISOString(),
        ]);

        // Debug: Check for user-variables in different locations
        Log::info('Mailgun Webhook User Variables Debug', [
            'user_variables' => $eventData['user-variables'] ?? 'not_found',
            'message_headers' => $eventData['message']['headers'] ?? 'not_found',
            'envelope' => $eventData['envelope'] ?? 'not_found',
            'tags' => $eventData['tags'] ?? 'not_found',
            'timestamp' => now()->toISOString(),
        ]);

        // tracking delivery of UK certificates
        if (array_key_exists('uk_certificate_row_id', $eventData['user-variables'])) {
            Log::info('UK Certificate tracking: Found uk_certificate_row_id in user-variables', [
                'row_id' => $eventData['user-variables']['uk_certificate_row_id'],
                'event' => $eventData['event'],
                'recipient' => $eventData['recipient'],
                'timestamp' => now()->toISOString(),
            ]);
            
            $rowId = $eventData['user-variables']['uk_certificate_row_id'];
            $row = UkCertificateRow::find($rowId);
            
            if ($row) {
                // Only track delivery status for the primary recipient (trainee's email)
                // Ignore BCC recipients as they shouldn't affect the main delivery status
                $traineeEmail = $row->trainee->email ?? null;
                $currentRecipient = $eventData['recipient'];
                
                if ($traineeEmail && $currentRecipient === $traineeEmail) {
                    Log::info('UK Certificate tracking: Processing primary recipient (trainee)', [
                        'row_id' => $rowId,
                        'trainee_email' => $traineeEmail,
                        'current_recipient' => $currentRecipient,
                        'event' => $eventData['event'],
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    // Try to capture Mailgun Message-Id from headers if available
                    $messageId = Arr::get($eventData, 'message.headers.message-id')
                        ?? Arr::get($eventData, 'message.headers.Message-Id')
                        ?? Arr::get($eventData, 'Message-Id');

                    if ($eventData['event'] === 'delivered') {
                        $row->update([
                            'delivery_status' => 'delivered',
                            'delivered_at' => now(),
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            // Ensure these are set in case job did not update them for any reason
                            'sent_at' => $row->sent_at ?: now(),
                            'status' => 'sent',
                        ]);
                        
                        Log::info('UK Certificate tracking: Updated row as delivered (primary recipient)', [
                            'row_id' => $rowId,
                            'message_id' => $messageId,
                            'recipient' => $currentRecipient,
                            'timestamp' => now()->toISOString(),
                        ]);
                    } elseif ($eventData['event'] === 'failed') {
                        $row->update([
                            'delivery_status' => 'failed',
                            'failed_at' => now(),
                            'delivery_failure_reason' => Arr::get($eventData, 'delivery-status.message', 'Unknown delivery failure'),
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            'status' => 'failed',
                        ]);
                        
                        Log::info('UK Certificate tracking: Updated row as failed (primary recipient)', [
                            'row_id' => $rowId,
                            'message_id' => $messageId,
                            'failure_reason' => Arr::get($eventData, 'delivery-status.message', 'Unknown delivery failure'),
                            'recipient' => $currentRecipient,
                            'timestamp' => now()->toISOString(),
                        ]);
                    } elseif ($eventData['event'] === 'bounced') {
                        $row->update([
                            'delivery_status' => 'failed',
                            'failed_at' => now(),
                            'delivery_failure_reason' => 'Email bounced: ' . (Arr::get($eventData, 'delivery-status.message', 'Unknown bounce reason')),
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            'status' => 'failed',
                        ]);
                        
                        Log::info('UK Certificate tracking: Updated row as bounced (primary recipient)', [
                            'row_id' => $rowId,
                            'message_id' => $messageId,
                            'bounce_reason' => Arr::get($eventData, 'delivery-status.message', 'Unknown bounce reason'),
                            'recipient' => $currentRecipient,
                            'timestamp' => now()->toISOString(),
                        ]);
                    } elseif ($eventData['event'] === 'complained') {
                        $row->update([
                            'delivery_status' => 'failed',
                            'failed_at' => now(),
                            'delivery_failure_reason' => 'Email marked as spam/complaint',
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            'status' => 'failed',
                        ]);
                        
                        Log::info('UK Certificate tracking: Updated row as complained (primary recipient)', [
                            'row_id' => $rowId,
                            'message_id' => $messageId,
                            'recipient' => $currentRecipient,
                            'timestamp' => now()->toISOString(),
                        ]);
                    }
                } else {
                    Log::info('UK Certificate tracking: Ignoring BCC recipient event', [
                        'row_id' => $rowId,
                        'trainee_email' => $traineeEmail,
                        'current_recipient' => $currentRecipient,
                        'event' => $eventData['event'],
                        'reason' => $traineeEmail ? 'BCC recipient' : 'No trainee email found',
                        'timestamp' => now()->toISOString(),
                    ]);
                }
            } else {
                Log::warning('UK Certificate tracking: Row not found in database', [
                    'row_id' => $rowId,
                    'event' => $eventData['event'],
                    'timestamp' => now()->toISOString(),
                ]);
            }
            
            return response()->json(['success' => true, 'uk_certificate_row_id' => $rowId]);
        }

        if (array_key_exists('jasarah_center_certificate_row_id', $eventData['user-variables'])) {
            $rowId = $eventData['user-variables']['jasarah_center_certificate_row_id'];
            $row = JasarahCenterCertificateRow::find($rowId);

            if ($row) {
                $traineeEmail = $row->trainee->email ?? null;
                $currentRecipient = $eventData['recipient'];

                if ($traineeEmail && $currentRecipient === $traineeEmail) {
                    $messageId = Arr::get($eventData, 'message.headers.message-id')
                        ?? Arr::get($eventData, 'message.headers.Message-Id')
                        ?? Arr::get($eventData, 'Message-Id');

                    if ($eventData['event'] === 'delivered') {
                        $row->update([
                            'delivery_status' => 'delivered',
                            'delivered_at' => now(),
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            'sent_at' => $row->sent_at ?: now(),
                            'status' => 'sent',
                        ]);
                    } elseif (in_array($eventData['event'], ['failed', 'bounced', 'complained'], true)) {
                        $reason = match ($eventData['event']) {
                            'bounced' => 'Email bounced: ' . (Arr::get($eventData, 'delivery-status.message', 'Unknown bounce reason')),
                            'complained' => 'Email marked as spam/complaint',
                            default => Arr::get($eventData, 'delivery-status.message', 'Unknown delivery failure'),
                        };

                        $row->update([
                            'delivery_status' => 'failed',
                            'failed_at' => now(),
                            'delivery_failure_reason' => $reason,
                            'mailgun_message_id' => $row->mailgun_message_id ?: $messageId,
                            'status' => 'failed',
                        ]);
                    }
                }
            }

            return response()->json(['success' => true, 'jasarah_center_certificate_row_id' => $rowId]);
        }

        if (!array_key_exists('uk_certificate_row_id', $eventData['user-variables'] ?? [])
            && !array_key_exists('jasarah_center_certificate_row_id', $eventData['user-variables'] ?? [])) {
            Log::info('Certificate tracking: No certificate row id found in user-variables', [
                'user_variables' => $eventData['user-variables'] ?? 'not_found',
                'event' => $eventData['event'],
                'timestamp' => now()->toISOString(),
            ]);
        }

        // Fallback: Try to extract row ID from message headers or other sources
        // This is a workaround in case user-variables are not being passed correctly
        $messageId = Arr::get($eventData, 'message.headers.message-id');
        if ($messageId && $eventData['event'] === 'delivered') {
            Log::info('Attempting fallback UK certificate tracking via message ID', [
                'message_id' => $messageId,
                'event' => $eventData['event'],
                'timestamp' => now()->toISOString(),
            ]);
            
            // Try to find the row by checking if this message ID matches any recent sent certificates
            // This is not ideal but provides a fallback tracking mechanism
            $recentRow = UkCertificateRow::where('status', 'sent')
                ->where('sent_at', '>=', now()->subHours(24))
                ->whereNull('delivery_status')
                ->first();
                
            if ($recentRow) {
                Log::info('Found recent UK certificate row for fallback tracking', [
                    'row_id' => $recentRow->id,
                    'message_id' => $messageId,
                    'timestamp' => now()->toISOString(),
                ]);
                
                $recentRow->update([
                    'delivery_status' => 'delivered',
                    'delivered_at' => now(),
                    'mailgun_message_id' => $messageId,
                ]);
                
                return response()->json(['success' => true, 'fallback_tracking' => true, 'row_id' => $recentRow->id]);
            }
        }

        // tracking delivery of company attendances reports
        if (array_key_exists('company_attendance_report_id', $eventData['user-variables'])) {
            if ($eventData['event'] === 'failed') {

                $email = CompanyAttendanceReport::find($eventData['user-variables']['company_attendance_report_id'])
                    ->emails()
                    ->where('email', $eventData['recipient'])
                    ->first();

                if ($email->failed_at) {
                    // already failed and mailgun is just retrying
                    // (so we don't send an email in order to not annoy people)...
                    $email->update([
                        'failed_at' => now(),
                        'failed_reason' => $eventData['delivery-status']['message'],
                    ]);
                } else {
                    // failed for the first time, email people.
                    $email->update([
                        'failed_at' => now(),
                        'failed_reason' => $eventData['delivery-status']['message'],
                    ]);
                    Mail::to(['sara@ptc-ksa.net', 'ceo@ptc-ksa.net', 'billing@ptc-ksa.net'])
                        ->send(new CompanyAttendanceFailureMail($email));
                }

            } elseif ($eventData['event'] === 'delivered') {
                CompanyAttendanceReport::find($eventData['user-variables']['company_attendance_report_id'])
                    ->emails()
                    ->where('email', $eventData['recipient'])
                    ->update([
                        'delivered_at' => now(),
                    ]);
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => true]);

        $company = CompaniesService::new()->findByDomainName(Str::after($request->input('sender'), '@'));

        if (!$company) {
            throw new \RuntimeException('No company found to save the mail under.');
        }

        $sender = Str::between($request->input('event-data')['message']['headers']['from'], '<', '>'); // mohammad@acme.com
        $from = Str::before($request->input('event-data')['message']['headers']['from'], '<'); // Mohammad <mohammad@acme.com>
        $subject = $request->input('event-data')['message']['headers']['subject'];

        $mailContents = Http::withBasicAuth(
                    config('services.mailgun.domain'),
                    config('services.mailgun.secret')
                )->get($request->input('event-data')['storage']['url']);
        $bodyPlain = $mailContents['Body-Plain'];
        $bodyHtml = $mailContents['Body-HTML'];

        $companyMail = CompanyMail::create([
            'company_id' => $company->id,
            'from' => $from,
            'subject' => $subject,
            'sender' => $sender,
            'body_text' => $bodyPlain,
            'body_html' => $bodyHtml,
        ]);

        if ($request->has('attachments')) {
            $attachments = collect(json_decode($request->input('event-data')['message']['attachments'], true, 512, JSON_THROW_ON_ERROR));

            // loop through each attachment and save them on the local filesystem.
            $attachments->each(function ($attachment) use ($companyMail, $company) {
                $fileContents = Http::withBasicAuth(
                    config('services.mailgun.domain'),
                    config('services.mailgun.secret')
                )->get(Arr::get($attachment, 'url'));

                $companyMail->addMediaFromString($fileContents)
                    ->usingFileName(Arr::get($attachment, 'name'))
                    ->withAttributes([
                        'team_id' => $company->team_id,
                    ])
                    ->toMediaCollection('attachments');
            });
        }

        return response()->json(['company_mail_id' => $companyMail->id]);
    }

    public function viewCompanyMails($company_id, $id)
    {
        $company_mails = CompanyMail::findOrFail($id);

        return Inertia::render('Back/Companies/Emails/Show', [
            'company' => Company::findOrFail($company_id),
            'company_mails' => $company_mails,
        ]);
    }

}
