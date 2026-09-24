@component('mail::message')
# طلب تغيير كلمة المرور

طلبت تغيير كلمة المرور من داخل حسابك في Taskora. استخدم الرمز التالي لتأكيد العملية. صلاحية الرمز 10 دقائق.

@component('mail::panel')
# {{ $code }}
@endcomponent

إذا لم تطلب تغيير كلمة المرور، تجاهل هذه الرسالة وتحقق من أمان حسابك.

شكراً،<br>
{{ config('app.name') }}
@endcomponent
