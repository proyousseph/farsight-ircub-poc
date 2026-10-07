<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 10)->default('USD');
            $table->string('quote_currency', 10); // e.g. SOS
            $table->decimal('rate', 18, 6); // quote per 1 base
            $table->string('source', 50)->default('mock-api');
            $table->timestamp('retrieved_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency', 'retrieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
