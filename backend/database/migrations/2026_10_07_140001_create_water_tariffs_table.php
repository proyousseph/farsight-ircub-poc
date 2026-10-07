<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('tariff_class', 30); // DOMESTIC | COMMERCIAL | INSTITUTIONAL
            $table->string('name');
            $table->json('tiers'); // [{from, to, rate_per_m3}]
            $table->decimal('fixed_charge', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['tariff_class', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_tariffs');
    }
};
