<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_combination_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('drug_combination_id');
            $table->foreign('drug_combination_id')
                ->references('id')->on('drug_combinations')
                ->cascadeOnDelete();

            $table->string('section_key');       // canonical key e.g. 'overview', 'dosage'
            $table->string('section_title');     // display title, can be customized
            $table->longText('content');         // rich HTML or markdown
            $table->string('content_format')->default('html');  // html, markdown, plain
            $table->integer('display_order')->default(0);
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            $table->unique(['drug_combination_id', 'section_key'], 'comb_sec_uniq');
            $table->index(
                ['drug_combination_id', 'display_order'],
                'comb_sec_combo_disp_idx'
            );
            $table->index('section_key');
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_combination_sections');
    }
};
