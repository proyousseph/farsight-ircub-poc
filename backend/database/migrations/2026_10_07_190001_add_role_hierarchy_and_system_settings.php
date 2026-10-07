<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('is_active')
                ->constrained('roles')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(0)->after('parent_id');
            $table->index(['parent_id', 'level']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('label');
            $table->string('group')->default('general')->index();
            $table->string('type')->default('string'); // string|int|bool|json
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('level');
        });
    }
};
