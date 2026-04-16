<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');              // medicine, manufacturer, combination
            $table->string('status');            // pending, validating, staging, complete, failed
            $table->string('filename');
            $table->string('storage_path');
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->json('column_map')->nullable();
            $table->json('settings')->nullable();
            $table->text('error_summary')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
