<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();
        
            // ── Identity ──────────────────────────────────────────────
            $table->string('name')->index();
            $table->string('slug')->unique();
        
            // ── Composition ───────────────────────────────────────────
            $table->text('short_composition');                        // raw text e.g. "Metformin 500mg"
            $table->string('dosage_form')->nullable();                // tablet, capsule, syrup, injection
            $table->string('strength')->nullable();                   // "500mg", "10mg/5ml"
            $table->string('route_of_administration')->nullable();    // oral, topical, IV
        
            // ── Manufacturer ──────────────────────────────────────────
            $table->uuid('manufacturer_id')->nullable();
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->nullOnDelete();
        
            // ── Classification ────────────────────────────────────────
            $table->string('type')->nullable()->index();              // tablet, syrup, injection
            $table->string('schedule')->nullable();                   // Schedule H, Schedule H1, OTC
            $table->boolean('rx_required')->default(false)->index();  // requires prescription
            $table->string('rx_required_header')->nullable();         // display label: "Rx", "OTC"
            $table->string('atc_code')->nullable();                   // WHO ATC code
        
            // ── Pricing ───────────────────────────────────────────────
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('mrp', 10, 2)->nullable();
        
            // ── Packaging ─────────────────────────────────────────────
            $table->string('pack_size_label')->nullable();           // "10 tablets", "30ml bottle"
            $table->integer('quantity')->nullable();                 // numeric quantity
            $table->string('quantity_unit')->nullable();             // "tablets", "ml", "capsules"
        
            // ── Identifiers ───────────────────────────────────────────
            $table->string('barcode')->nullable()->unique();         // primary barcode
            $table->string('gs1_gtin', 14)->nullable()->unique();   // GS1 Global Trade Item Number
            $table->string('hsn_code', 8)->nullable();              // India HSN for GST
            $table->string('ndc_code')->nullable();                 // if applicable
        
            // ── Storage & Regulatory ──────────────────────────────────
            $table->string('storage_conditions')->nullable();        // "Store below 25°C"
            $table->string('shelf_life')->nullable();                // "24 months"
            $table->string('country_of_origin', 3)->default('IN');
        
            // ── Status ────────────────────────────────────────────────
            $table->boolean('is_discontinued')->default(false)->index();
            $table->string('approval_status')->default('draft');     // draft, reviewed, published, archived
            $table->timestamp('published_at')->nullable();
        
            // ── Content ───────────────────────────────────────────────
            $table->text('description')->nullable();
            $table->text('warnings')->nullable();
        
            // ── Import provenance ─────────────────────────────────────
            $table->string('source')->nullable();                    // "csv_import", "manual", "api_sync"
            $table->string('source_reference')->nullable();          // filename or external ID
        
            // ── Audit ─────────────────────────────────────────────────
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        
            // ── Indexes ───────────────────────────────────────────────
            $table->index('manufacturer_id');
            $table->index('approval_status');
            $table->index('hsn_code');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
