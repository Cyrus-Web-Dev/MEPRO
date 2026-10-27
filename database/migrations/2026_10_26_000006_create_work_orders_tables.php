<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number', 40)->unique();
            $table->string('external_ref', 100)->nullable()->unique(); // ID dari ERP
            $table->foreignId('product_id')->constrained();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('target_qty');
            $table->string('status', 20)->default('planned')->index(); // planned|running|paused|completed|cancelled
            $table->date('planned_start')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('ideal_cycle_seconds', 8, 3)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('work_order_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('operator_id')->constrained('users');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('output_qty')->default(0);   // cache saat run ditutup
            $table->unsignedBigInteger('reject_qty')->default(0);   // cache saat run ditutup
            $table->timestamps();
            $table->index(['machine_id', 'started_at']);
        });

        // Satu mesin hanya boleh punya satu run yang sedang berjalan
        DB::statement('CREATE UNIQUE INDEX work_order_runs_one_open_per_machine ON work_order_runs (machine_id) WHERE ended_at IS NULL');
        // Satu WO hanya boleh berjalan di satu mesin pada satu waktu
        DB::statement('CREATE UNIQUE INDEX work_order_runs_one_open_per_wo ON work_order_runs (work_order_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_runs');
        Schema::dropIfExists('work_orders');
    }
};
