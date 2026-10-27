<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machine_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('seq');
            $table->unsignedInteger('delta');
            $table->unsignedBigInteger('total')->nullable();
            $table->boolean('is_buffered')->default(false);
            $table->timestampTz('recorded_at');
            $table->timestampTz('received_at');

            $table->unique(['device_id', 'seq']);
            $table->index(['machine_id', 'recorded_at']);
        });

        // Agregasi per jam untuk data lama (Fase 6: retensi)
        Schema::create('machine_counts_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('bucket');
            $table->unsignedBigInteger('total');
            $table->timestamps();
            $table->unique(['machine_id', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_counts_hourly');
        Schema::dropIfExists('machine_counts');
    }
};
