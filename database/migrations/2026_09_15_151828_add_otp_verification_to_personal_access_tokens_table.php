<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('last_used_at');
            $table->string('otp_code_hash')->nullable()->after('verified_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_code_hash');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'otp_code_hash', 'otp_attempts', 'otp_expires_at']);
        });
    }
};
