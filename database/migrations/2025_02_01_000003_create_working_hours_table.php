<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->boolean('is_open')->default(true);
            $table->time('opens_at')->default('08:00:00');
            $table->time('closes_at')->default('17:00:00');
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
