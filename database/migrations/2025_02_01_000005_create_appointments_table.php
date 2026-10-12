<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->unsignedSmallInteger('reschedule_count')->default(0);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('strike_at')->nullable();
            $table->timestamps();

            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
