<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fmis_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 40)->unique();
            $table->date('journal_date');
            $table->string('status', 20)->default('PENDING'); // PENDING | POSTED | FAILED | REVERSED
            $table->string('fmis_reference', 100)->nullable()->unique();
            $table->unsignedInteger('line_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('failure_reason')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['journal_date', 'status']);
        });

        Schema::create('fmis_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fmis_journal_batch_id')->constrained('fmis_journal_batches')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('revenue_code', 50);
            $table->string('gl_code', 50);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('description')->nullable();
            $table->string('fmis_line_ref', 100)->nullable();
            $table->timestamps();

            // A payment may appear only once in an active (non-reversed) posting trail.
            $table->unique('payment_id');
            $table->index(['gl_code', 'revenue_code']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('fmis_reference', 100)->nullable()->after('fmis_status');
            $table->timestamp('fmis_posted_at')->nullable()->after('fmis_reference');
            $table->foreignId('fmis_journal_line_id')->nullable()->after('fmis_posted_at')
                ->constrained('fmis_journal_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fmis_journal_line_id');
            $table->dropColumn(['fmis_reference', 'fmis_posted_at']);
        });

        Schema::dropIfExists('fmis_journal_lines');
        Schema::dropIfExists('fmis_journal_batches');
    }
};
