<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_identifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('medicine_id');
            $table->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();

            $table->string('identifier_type');   // barcode, gtin, gs1, internal_sku, regulatory_code, ndc
            $table->string('identifier_value');
            $table->string('issuing_body')->nullable();  // GS1 India, CDSCO, etc.
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(
                ['medicine_id', 'identifier_type', 'identifier_value'],
                'med_ident_med_type_val_uniq'
            );
            $table->index(
                ['identifier_type', 'identifier_value'],
                'med_ident_type_val_idx'
            );   // fast barcode lookup
            $table->index('medicine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_identifiers');
    }
};
