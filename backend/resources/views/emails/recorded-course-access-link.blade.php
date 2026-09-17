@component('mail::message')

مرحباً {{ $traineeName }}،

تم تسجيلك في الدورة التدريبية **{{ $courseName }}**.

@component('mail::button', ['url' => $accessUrl])
فتح الدورة
@endcomponent

أو انسخ الرابط مباشرة:

{{ $accessUrl }}

عند فتح الرابط، سجّل حضورك (Check-in) ثم أكمل الدروس حسب الجدول.

مع أطيب التحيات،
جسارة للتدريب

@endcomponent
