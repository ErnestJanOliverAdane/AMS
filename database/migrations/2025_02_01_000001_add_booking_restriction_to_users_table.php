<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('booking_restricted_at')->nullable()->after('is_active');
            $table->string('restriction_reason')->nullable()->after('booking_restricted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['booking_restricted_at', 'restriction_reason']);
        });
    }
};
