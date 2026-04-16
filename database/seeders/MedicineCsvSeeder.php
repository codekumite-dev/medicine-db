<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatusEnum;
use App\Enums\DosageFormEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Telescope\Telescope;

class MedicineCsvSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = database_path('medicine_data.csv');

        if (! file_exists($filePath)) {
            $this->command->error("CSV file not found at: {$filePath}");

            return;
        }

        $this->command->info('Starting CSV import...');

        // Keep import memory stable for very large CSVs.
        DB::connection()->disableQueryLog();
        DB::connection()->unsetEventDispatcher();

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file); // Read header
        if ($header === false) {
            $this->command->error('CSV header is missing.');
            fclose($file);

            return;
        }

        $rowCount = 0;
        $batchSize = 500;
        $medicinesBatch = [];
        $manufacturersCache = DB::table('manufacturers')
            ->pluck('id', 'name')
            ->all();
        $now = now()->toDateTimeString();

        $limit = 1000000;

        while (($row = fgetcsv($file)) !== false && $rowCount < $limit) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                continue;
            }

            $manufacturerName = trim($data['manufacturer_name'] ?? 'Unknown');

            if (! isset($manufacturersCache[$manufacturerName])) {
                $manufacturerId = Str::uuid()->toString();

                DB::table('manufacturers')->insert([
                    'id' => $manufacturerId,
                    'name' => $manufacturerName,
                    'slug' => Str::slug($manufacturerName).'-'.substr(Str::uuid()->toString(), 0, 8),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $manufacturersCache[$manufacturerName] = $manufacturerId;
            }

            $name = $data['name'];
            $slug = Str::slug($name).'-'.substr(Str::uuid(), 0, 8);

            // Try to guess dosage form from name or pack_size_label
            $dosageForm = $this->guessDosageForm($name.' '.($data['pack_size_label'] ?? ''));

            $medicinesBatch[] = [
                'id' => Str::uuid()->toString(),
                'name' => $name,
                'slug' => $slug,
                'short_composition' => $data['short_composition'] ?? '',
                'manufacturer_id' => $manufacturersCache[$manufacturerName],
                'type' => $data['type'] ?? 'allopathy',
                'price' => is_numeric($data['price']) ? $data['price'] : 0,
                'pack_size_label' => $data['pack_size_label'] ?? '',
                'rx_required' => str_contains(strtolower($data['rx_required_header'] ?? ''), 'prescription'),
                'rx_required_header' => $data['rx_required_header'] ?? '',
                'quantity' => is_numeric($data['quantity']) ? (int) $data['quantity'] : 0,
                'is_discontinued' => strtolower($data['is_discontinued'] ?? '') === 'true',
                'dosage_form' => $dosageForm,
                'approval_status' => ApprovalStatusEnum::Published->value,
                'published_at' => $now,
                'source' => 'csv_import',
                'source_reference' => 'medicine_data.csv',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rowCount++;

            if (count($medicinesBatch) >= $batchSize) {
                DB::table('medicines')->insert($medicinesBatch);
                $medicinesBatch = [];
                $this->command->info("Imported {$rowCount} rows...");

                // Help long-running CLI processes release cyclic allocations.
                if ($rowCount % 10000 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        if (! empty($medicinesBatch)) {
            DB::table('medicines')->insert($medicinesBatch);
        }

        fclose($file);

        $this->command->info("Import completed! Total rows: {$rowCount}");
    }

    private function guessDosageForm(string $text): ?string
    {
        $text = strtolower($text);
        foreach (DosageFormEnum::cases() as $form) {
            if (str_contains($text, $form->value)) {
                return $form->value;
            }
        }

        return null;
    }
}
