<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// يمنع نمو جدول idempotency_keys إلى ما لا نهاية — يحذف الصفوف منتهية
// الصلاحية فعلياً (وليس فقط تجاهلها في الاستعلامات كما يفعل EnsureIdempotency
// حالياً). راجع IdempotencyKey::prunable(). يحتاج مُشغِّلاً فعلياً
// (`php artisan schedule:work` أثناء التطوير، أو Cron حقيقي على أي استضافة
// لاحقة) — بدونه هذا السطر مكتوب لكن لا يُنفَّذ أبداً.
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->daily();
