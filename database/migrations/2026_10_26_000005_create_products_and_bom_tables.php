<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->decimal('ideal_cycle_seconds', 8, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->timestamps();
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->decimal('qty_per_unit', 14, 4);
            $table->decimal('scrap_percent', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('products');
    }
};
