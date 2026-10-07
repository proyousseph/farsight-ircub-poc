<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_targets', function (Blueprint $table) {
            $table->id();
            $table->string('period_type', 20); // MONTH | QUARTER
            $table->string('period_key', 20); // 2026-10 | 2026-Q4
            $table->string('revenue_code', 20)->default('ALL'); // ALL = overall collections
            $table->decimal('target_amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['period_type', 'period_key', 'revenue_code'], 'revenue_targets_unique');
        });

        Schema::create('dashboard_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');
            $table->string('revenue_code', 20);
            $table->string('channel', 30);
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('collected_amount', 14, 2)->default(0);
            $table->unsignedInteger('reversal_count')->default(0);
            $table->decimal('reversed_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'revenue_code', 'channel'], 'dashboard_daily_unique');
            $table->index(['stat_date', 'revenue_code']);
            $table->index(['stat_date', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_daily_aggregates');
        Schema::dropIfExists('revenue_targets');
    }
};
