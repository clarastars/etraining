<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>{{ __('words.invoices-details-report') }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px; text-align: center; }
        th { background: #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ __('words.invoices-details-report') }}</h1>
    <p>{{ $companyName }} — {{ $dateFrom }} / {{ $dateTo }}</p>
    <table>
        <thead>
        <tr>
            <th>{{ __('words.trainee') }}</th>
            <th>{{ __('words.identity_number') }}</th>
            <th>{{ __('words.subtotal') }}</th>
            <th>{{ __('words.tax') }}</th>
            <th>{{ __('words.grand-total') }}</th>
            <th>{{ __('words.status') }}</th>
            <th>{{ __('words.masdr-start-date') }}</th>
            <th>{{ __('words.invoice-date') }}</th>
            <th>{{ __('words.manual-start-date') }}</th>
            <th>{{ __('words.invoice-details-end-date') }}</th>
            <th>{{ __('words.day-count') }}</th>
            <th>{{ __('words.full-salary') }}</th>
            <th>{{ __('words.daily-salary-cost') }}</th>
            <th>{{ __('words.salary-due') }}</th>
            <th>{{ __('words.full-reward') }}</th>
            <th>{{ __('words.daily-reward-cost') }}</th>
            <th>{{ __('words.reward-due') }}</th>
            <th>{{ __('words.full-fees') }}</th>
            <th>{{ __('words.daily-fees-cost') }}</th>
            <th>{{ __('words.training-fees-due') }}</th>
            <th>{{ __('words.full-refund') }}</th>
            <th>{{ __('words.daily-refund-cost') }}</th>
            <th>{{ __('words.refund-due') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['trainee_name'] }}</td>
                <td>{{ $row['identity_number'] }}</td>
                <td>{{ $row['sub_total'] }}</td>
                <td>{{ $row['tax'] }}</td>
                <td>{{ $row['grand_total'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['masdr_start_label'] ?: '—' }}</td>
                <td>{{ $row['invoice_date'] }}</td>
                <td>{{ $row['manual_start_date'] }}</td>
                <td>{{ $row['end_date'] }}</td>
                <td>{{ $row['day_count'] }}</td>
                <td>{{ $row['full_salary'] }}</td>
                <td>{{ $row['daily_salary'] !== null ? number_format($row['daily_salary'], 2) : '' }}</td>
                <td>{{ $row['salary_due'] !== null ? number_format($row['salary_due'], 2) : '' }}</td>
                <td>{{ $row['full_reward'] }}</td>
                <td>{{ $row['daily_reward'] !== null ? number_format($row['daily_reward'], 2) : '' }}</td>
                <td>{{ $row['reward_due'] !== null ? number_format($row['reward_due'], 2) : '' }}</td>
                <td>{{ $row['full_fees'] }}</td>
                <td>{{ $row['daily_fees'] !== null ? number_format($row['daily_fees'], 2) : '' }}</td>
                <td>{{ $row['fees_due'] !== null ? number_format($row['fees_due'], 2) : '' }}</td>
                <td>{{ $row['full_refund'] }}</td>
                <td>{{ $row['daily_refund'] !== null ? number_format($row['daily_refund'], 2) : '' }}</td>
                <td>{{ $row['refund_due'] !== null ? number_format($row['refund_due'], 2) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
