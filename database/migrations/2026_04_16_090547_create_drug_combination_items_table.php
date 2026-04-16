<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_combination_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('drug_combination_id');
            $table->foreign('drug_combination_id')
                ->references('id')->on('drug_combinations')
                ->cascadeOnDelete();

            $table->uuid('medicine_id')->nullable();  // link to medicine record if exists
            $table->foreign('medicine_id')->references('id')->on('medicines')->nullOnDelete();

            $table->string('ingredient_name');        // always store plain text too
            $table->string('strength')->nullable();   // "500mg", "10mg"
            $table->string('role')->nullable();       // primary, adjuvant, inactive
            $table->integer('display_order')->default(0);

            $table->timestamps();

            $table->index('drug_combination_id');
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_combination_items');
    }
};
