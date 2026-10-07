<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('channel', 30); // BANK | MOBILE_MONEY | ALL
            $table->string('status', 20)->default('COMPLETED');
            $table->decimal('ircub_total', 15, 2)->default(0);
            $table->decimal('channel_total', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('ircub_only_count')->default(0);
            $table->unsignedInteger('channel_only_count')->default(0);
            $table->string('statement_path')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_date', 'channel']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained('reconciliation_runs')->cascadeOnDelete();
            $table->string('match_status', 20); // MATCHED | IRCUB_ONLY | CHANNEL_ONLY
            $table->string('external_ref', 100)->nullable();
            $table->decimal('ircub_amount', 15, 2)->nullable();
            $table->decimal('channel_amount', 15, 2)->nullable();
            $table->string('channel', 30)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['reconciliation_run_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliation_runs');
    }
};
