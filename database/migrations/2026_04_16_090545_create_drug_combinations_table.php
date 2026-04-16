<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_combinations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ── Identity ──────────────────────────────────────────────
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('canonical_name');            // canonical molecule name
            $table->string('short_name')->nullable();    // abbreviated label

            // ── Core content ──────────────────────────────────────────
            $table->text('summary')->nullable();
            $table->json('alternate_names')->nullable(); // array of strings

            // ── Editorial metadata ────────────────────────────────────
            $table->string('editorial_status')->default('draft');  // draft, in_review, medically_reviewed, published, retired
            $table->string('evidence_level')->nullable();           // A, B, C, expert_opinion
            $table->boolean('is_featured')->default(false);

            // ── SEO ───────────────────────────────────────────────────
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('schema_markup')->nullable();

            // ── Workflow ──────────────────────────────────────────────
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            // ── Source ────────────────────────────────────────────────
            $table->string('source')->nullable();
            $table->string('source_reference')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('editorial_status');
            $table->index('published_at');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_combinations');
    }
};
