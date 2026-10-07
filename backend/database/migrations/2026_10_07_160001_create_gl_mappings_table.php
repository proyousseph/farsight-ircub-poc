<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('revenue_code', 50)->unique();
            $table->string('gl_code', 50);
            $table->string('gl_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_mappings');
    }
};
