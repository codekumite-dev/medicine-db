<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\Medicine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessMedicineImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ImportJob $importJob) {}

    public function handle(): void
    {
        $rows = $this->importJob->rows()->where('status', 'valid')->cursor();

        foreach ($rows as $row) {
            try {
                $medicine = Medicine::updateOrCreate(
                    ['slug' => Str::slug($row->mapped_data['name'])],
                    array_merge($row->mapped_data, [
                        'created_by' => $this->importJob->created_by,
                        'source' => 'csv_import',
                        'source_reference' => $this->importJob->filename,
                    ])
                );

                $row->update([
                    'status' => 'imported',
                    'resulting_medicine_id' => $medicine->id,
                ]);
            } catch (\Exception $e) {
                $row->update(['status' => 'error', 'errors' => ['import' => $e->getMessage()]]);
            }
        }
    }
}
