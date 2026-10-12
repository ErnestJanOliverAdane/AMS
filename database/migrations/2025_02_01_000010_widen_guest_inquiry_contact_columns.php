<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Encrypted values are longer than the plain text, so the contact columns become text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_inquiries', function (Blueprint $table) {
            $table->text('phone')->change();
            $table->text('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('guest_inquiries', function (Blueprint $table) {
            $table->string('phone', 30)->change();
            $table->string('email')->nullable()->change();
        });
    }
};
