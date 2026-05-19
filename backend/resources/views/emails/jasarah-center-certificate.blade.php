@component('mail::message')

Dear {{ $trainee->name }},

Please find attached your **Notice of Attendance** for the course **{{ $courseName }}**.

Best regards,

@endcomponent
