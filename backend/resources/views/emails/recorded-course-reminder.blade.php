@component('mail::message')

مرحباً {{ $traineeName }}،

لم يكتمل تسجيلك أو إكمالك للدورة التدريبية **{{ $courseName }}**.

@if ($hasCheckedIn)
سجّلت حضورك، وبقي عليك إكمال الدروس المتبقية.
@else
لم يتم تسجيل حضورك بعد. افتح الرابط، سجّل الحضور، ثم أكمل الدروس.
@endif

@component('mail::button', ['url' => $accessUrl])
متابعة الدورة
@endcomponent

أو انسخ الرابط مباشرة:

{{ $accessUrl }}

مع أطيب التحيات،
جسارة للتدريب

@endcomponent
