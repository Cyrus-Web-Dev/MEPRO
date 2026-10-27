<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // dipakai di topic MQTT
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('status', 20)->default('offline')->index(); // offline|idle|running|downtime
            $table->boolean('is_online')->default(false);
            $table->decimal('ideal_cycle_seconds', 8, 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_count_at')->nullable();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('device_uid', 50)->unique();
            $table->string('mqtt_username', 50)->unique();
            $table->string('firmware_version', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('machines');
    }
};
