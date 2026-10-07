<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('severity', 50)->nullable();
            $table->unsignedBigInteger('severity_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'severity_id']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_notifications');
    }
};
