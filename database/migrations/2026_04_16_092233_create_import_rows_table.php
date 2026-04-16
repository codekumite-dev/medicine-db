<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_job_id');
            $table->foreign('import_job_id')->references('id')->on('import_jobs')->cascadeOnDelete();
            $table->integer('row_number');
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->string('status');            // pending, valid, error, imported, skipped
            $table->json('errors')->nullable();
            $table->uuid('resulting_medicine_id')->nullable();
            $table->timestamps();

            $table->index(
                ['import_job_id', 'status'],
                'import_rows_job_status_idx'
            );
            $table->index('row_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
