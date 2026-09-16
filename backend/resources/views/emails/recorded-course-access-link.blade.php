@component('mail::message')

مرحباً {{ $traineeName }}،

تم تسجيلك في الدورة التدريبية **{{ $courseName }}**.

يمكنك متابعة الدورة عبر الرابط التالي دون الحاجة إلى اسم مستخدم أو كلمة مرور:

@component('mail::button', ['url' => $accessUrl])
فتح الدورة
@endcomponent

أو انسخ الرابط مباشرة:

{{ $accessUrl }}

عند فتح الرابط، سجّل حضورك (Check-in) ثم أكمل الدروس حسب الجدول.

مع أطيب التحيات،
جسارة للتدريب

@endcomponent
