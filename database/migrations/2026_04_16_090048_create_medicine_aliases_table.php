<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('medicine_id');
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
        
            $table->string('alias');
            $table->string('alias_type');  // brand_name, generic_name, spelling_variant, local_name, alternate_pack
            $table->string('language_code', 10)->default('en');
        
            $table->timestamps();
        
            $table->index(['medicine_id']);
            $table->index(['alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_aliases');
    }
};
