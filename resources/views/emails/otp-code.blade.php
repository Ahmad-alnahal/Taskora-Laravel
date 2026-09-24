@component('mail::message')
# تأكيد الدخول إلى Taskora

استخدم الرمز التالي لتأكيد هذا الجهاز والدخول إلى حسابك. صلاحية الرمز 10 دقائق.

@component('mail::panel')
# {{ $code }}
@endcomponent

إذا لم تحاول تسجيل الدخول أو إنشاء حساب، تجاهل هذه الرسالة — لن يُفعَّل أي جهاز بدون هذا الرمز.

شكراً،<br>
{{ config('app.name') }}
@endcomponent
