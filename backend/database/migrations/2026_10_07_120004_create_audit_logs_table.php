<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('action', 50); // CREATED | UPDATED | REVERSED | UPLOADED
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
