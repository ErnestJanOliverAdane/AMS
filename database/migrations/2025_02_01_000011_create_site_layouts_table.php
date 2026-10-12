<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('plan_path')->nullable();
            $table->string('plan_name')->nullable();
            $table->decimal('width_m', 7, 1)->default(30);
            $table->json('layers');
            $table->json('settings')->nullable();
            $table->json('devices')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_layouts');
    }
};
