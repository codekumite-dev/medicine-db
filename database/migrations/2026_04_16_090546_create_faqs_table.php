<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('drug_combination_id');
            $table->foreign('drug_combination_id')
                ->references('id')->on('drug_combinations')
                ->cascadeOnDelete();

            $table->text('question');
            $table->longText('answer');
            $table->integer('display_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(
                ['drug_combination_id', 'display_order'],
                'faqs_combo_disp_idx'
            );
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
