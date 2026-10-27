<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reject_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('qc_rejects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reject_reason_id')->constrained();
            $table->unsignedInteger('qty');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestamps();
            $table->index('work_order_run_id');
        });

        Schema::create('downtime_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('category', 20)->default('unplanned'); // planned|unplanned
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('downtime_reason_id')->nullable()->constrained();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('source', 10)->default('manual'); // manual|auto
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['machine_id', 'started_at']);
        });

        // Satu mesin hanya boleh punya satu downtime yang sedang berjalan
        DB::statement('CREATE UNIQUE INDEX downtimes_one_open_per_machine ON downtimes (machine_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('downtimes');
        Schema::dropIfExists('downtime_reasons');
        Schema::dropIfExists('qc_rejects');
        Schema::dropIfExists('reject_reasons');
    }
};
