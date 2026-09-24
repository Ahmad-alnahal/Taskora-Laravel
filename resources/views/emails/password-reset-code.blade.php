@component('mail::message')
# طلب إعادة تعيين كلمة المرور

استخدم الرمز التالي لإتمام إعادة تعيين كلمة المرور في تطبيق Taskora. صلاحية الرمز 15 دقيقة.

@component('mail::panel')
# {{ $code }}
@endcomponent

إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة بأمان.

شكراً،<br>
{{ config('app.name') }}
@endcomponent
