<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->string('account_no', 50)->unique();
            $table->string('meter_no', 50)->unique();
            $table->string('tariff_class', 30); // DOMESTIC | COMMERCIAL | INSTITUTIONAL
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE | INACTIVE | DISCONNECTED
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_accounts');
    }
};
