<table>
    <thead>
    <tr>
        <th style="border:1px solid black;width:50px; text-align:center;background-color:yellow;"><strong>{{ __('words.date') }}:</strong></th>
        <th style="border:1px solid black;width:50px; text-align:center;background-color:yellow;"><strong>{{ now()->format('Y-m-d') }}</strong></th>
    </tr>
    <tr></tr>
    <tr>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.invoice-number') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.company') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.nature_of_work') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.trainee') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.email') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.identity_number') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.established-fees') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.subtotal') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.tax') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.grand-total') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.status') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.invoice-date') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.created-at') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.paid-at') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.chased-at') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.verified-at') }}</strong></th>
        <th style="border:1px solid black;background-color:#a0a0a0;width:50px; text-align:center"><strong>{{ __('words.created-by') }}</strong></th>
    </tr>
    </thead>

    <tbody>
    @foreach ($invoices as $invoice)
        <tr>
            <td style="width:50px;text-align:center;border:1px solid black;">{{ $invoice->number_formatted }}</td>
            <td style="width:50px;text-align:center;border:1px solid black;">{{ $invoice->company->name_ar }}</td>
            <td style="width:50px;text-align:center;border:1px solid black;">{{ $invoice->company->nature_of_work }}</td>
            <td style="border:1px solid black;">{{ $invoice->trainee->name }}</td>
            <td style="border:1px solid black;">{{ $invoice->trainee->email }}</td>
            <td style="border:1px solid black;">{{ $invoice->trainee->identity_number }}</td>
            <td style="border:1px solid black;">{{ optional($invoice->trainee)->override_training_costs }}</td>
            <td style="border:1px solid black;">{{ $invoice->sub_total }}</td>
            <td style="border:1px solid black;">{{ $invoice->tax }}</td>
            <td style="border:1px solid black;">{{ $invoice->grand_total }}</td>
            <td style="border:1px solid black;">{{ $invoice->status_formatted }}</td>
            <td style="border:1px solid black;">{{ $invoice->from_date }}</td>
            <td style="border:1px solid black;">{{ optional(optional($invoice->created_at)->setTimezone('Asia/Riyadh'))->toDateTimeString() }}</td>
            <td style="border:1px solid black;">{{ optional(optional($invoice->paid_at)->setTimezone('Asia/Riyadh'))->toDateTimeString() }}</td>
            <td style="border:1px solid black;">{{ optional(optional($invoice->chased_at)->setTimezone('Asia/Riyadh'))->toDateTimeString() }}</td>
            <td style="border:1px solid black;">{{ optional(optional($invoice->verified_at)->setTimezone('Asia/Riyadh'))->toDateTimeString() }}</td>
            <td style="border:1px solid black;">{{ optional($invoice->created_by)->email }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
