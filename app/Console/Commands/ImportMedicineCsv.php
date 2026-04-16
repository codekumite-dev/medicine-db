<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportMedicineCsv extends Command
{
    protected $signature = 'import:medicines
                            {file=medicine_data.csv : Path to CSV file (relative to project root)}
                            {--chunk=5000 : Number of rows per transaction batch}
                            {--truncate : Truncate medicines & manufacturers tables before importing}';

    protected $description = 'Bulk-import medicines from a CSV file using raw PDO (fast SQLite mode)';

    // In-memory maps for deduplication
    private array $manufacturerMap = [];   // name → uuid
    private array $slugCounter     = [];   // slug → int counter (for uniqueness within run)

    public function handle(): int
    {
        // ── 1. Resolve file ───────────────────────────────────────────────
        $filePath = base_path($this->argument('file'));
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        // ── 2. Get raw PDO connection ─────────────────────────────────────
        /** @var \PDO $pdo */
        $pdo = DB::connection()->getPdo();

        // ── 3. Enable SQLite fast-write mode ─────────────────────────────
        $pdo->exec('PRAGMA journal_mode    = WAL');
        $pdo->exec('PRAGMA synchronous     = NORMAL');
        $pdo->exec('PRAGMA cache_size      = -65536');   // 64 MB page cache
        $pdo->exec('PRAGMA temp_store      = MEMORY');
        $pdo->exec('PRAGMA foreign_keys    = OFF');      // skip FK checks during bulk import

        // ── 4. Optional truncate ──────────────────────────────────────────
        if ($this->option('truncate')) {
            $this->warn('Truncating medicines & manufacturers tables…');
            $pdo->exec('DELETE FROM medicine_aliases');
            $pdo->exec('DELETE FROM medicine_identifiers');
            $pdo->exec('DELETE FROM medicines');
            $pdo->exec('DELETE FROM manufacturers');
        }

        // ── 5. Pre-load existing manufacturers into memory ────────────────
        $this->info('Pre-loading manufacturers…');
        $existing = $pdo->query('SELECT id, name FROM manufacturers')->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($existing as $row) {
            $this->manufacturerMap[$row['name']] = $row['id'];
        }
        $this->info('Loaded ' . count($this->manufacturerMap) . ' existing manufacturers.');

        // ── 6. Pre-load existing slugs into memory (collision guard) ──────
        $this->info('Pre-loading existing medicine slugs…');
        $existingSlugs = $pdo->query('SELECT slug FROM medicines')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($existingSlugs as $slug) {
            $this->slugCounter[$slug] = 0;
        }

        // ── 7. Open CSV ───────────────────────────────────────────────────
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        if (! $headers) {
            $this->error('CSV appears to be empty.');
            fclose($handle);
            return self::FAILURE;
        }
        $headers = array_map(fn($h) => trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF"), $headers);
        $this->info('Columns: ' . implode(', ', $headers));

        $totalLines = (int) shell_exec('wc -l < ' . escapeshellarg($filePath));
        $totalRows  = max(0, $totalLines - 1);
        $this->info("Estimated rows: " . number_format($totalRows));

        // ── 8. Prepare statements ─────────────────────────────────────────
        $mfrStmt = $pdo->prepare(
            'INSERT OR IGNORE INTO manufacturers (id, name, slug, country_code, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );

        $medStmt = $pdo->prepare(
            'INSERT OR IGNORE INTO medicines
             (id, name, slug, short_composition, type, manufacturer_id,
              price, mrp, currency, pack_size_label, quantity,
              rx_required, rx_required_header, is_discontinued,
              approval_status, published_at, source, source_reference,
              created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        // ── 9. Stream and batch-insert ────────────────────────────────────
        $chunkSize  = (int) $this->option('chunk');
        $bar        = $this->output->createProgressBar($totalRows);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% • %elapsed:6s%');
        $bar->start();

        $imported  = 0;
        $skipped   = 0;
        $rowBatch  = [];
        $mfrBatch  = [];
        $now       = date('Y-m-d H:i:s');
        $source    = basename($filePath);

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row)) === 0) { $skipped++; $bar->advance(); continue; }

            $data = array_combine($headers, array_pad($row, count($headers), null));
            if (! $data) { $skipped++; $bar->advance(); continue; }

            $name = trim($data['name'] ?? '');
            if ($name === '') { $skipped++; $bar->advance(); continue; }

            // Manufacturer
            $mfrName       = trim($data['manufacturer_name'] ?? '');
            $manufacturerId = null;
            if ($mfrName !== '') {
                if (! isset($this->manufacturerMap[$mfrName])) {
                    $mfrId = (string) Str::uuid();
                    $mfrSlug = Str::slug($mfrName) ?: Str::random(8);
                    $this->manufacturerMap[$mfrName] = $mfrId;
                    $mfrBatch[] = [$mfrId, $mfrName, $mfrSlug, 'IN', $now, $now];
                }
                $manufacturerId = $this->manufacturerMap[$mfrName];
            }

            // Slug deduplication
            $slug = $this->makeSlug($name);

            // Numeric fields
            $price = is_numeric($data['price'] ?? '') ? (float) $data['price'] : null;
            $qty   = is_numeric($data['quantity'] ?? '') ? (int) $data['quantity'] : null;

            // Rx
            $rxHeader  = trim($data['rx_required_header'] ?? '');
            $rxRequired = (! empty($rxHeader) && strtolower($rxHeader) !== 'otc') ? 1 : 0;

            // Discontinued
            $disc = strtolower(trim($data['is_discontinued'] ?? 'false'));
            $isDisc = in_array($disc, ['true', '1', 'yes']) ? 1 : 0;

            $rowBatch[] = [
                (string) Str::uuid(),
                $name,
                $slug,
                trim($data['short_composition'] ?? ''),
                trim($data['type'] ?? '') ?: null,
                $manufacturerId,
                $price,
                $price,      // mrp = price (CSV only has price)
                'INR',
                trim($data['pack_size_label'] ?? '') ?: null,
                $qty,
                $rxRequired,
                $rxHeader ?: null,
                $isDisc,
                'published',
                $now,
                'csv_import',
                $source,
                $now,
                $now,
            ];

            if (count($rowBatch) >= $chunkSize) {
                $this->flushBatch($pdo, $mfrStmt, $mfrBatch, $medStmt, $rowBatch);
                $imported += count($rowBatch);
                $bar->advance(count($rowBatch));
                $rowBatch = [];
                $mfrBatch = [];
            }
        }

        // Final flush
        if (! empty($rowBatch)) {
            $this->flushBatch($pdo, $mfrStmt, $mfrBatch, $medStmt, $rowBatch);
            $imported += count($rowBatch);
            $bar->advance(count($rowBatch));
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        // Re-enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON');

        $this->info('✅  Import complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rows imported',    number_format($imported)],
                ['Rows skipped',     number_format($skipped)],
                ['Manufacturers',    number_format(count($this->manufacturerMap))],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Wrap a batch of manufacturers + medicines in a single transaction.
     */
    private function flushBatch(
        \PDO $pdo,
        \PDOStatement $mfrStmt,
        array $mfrBatch,
        \PDOStatement $medStmt,
        array $medBatch
    ): void {
        $pdo->beginTransaction();
        foreach ($mfrBatch as $m) {
            $mfrStmt->execute($m);
        }
        foreach ($medBatch as $m) {
            $medStmt->execute($m);
        }
        $pdo->commit();
    }

    /**
     * Generate a unique slug, appending a counter on collision.
     */
    private function makeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'medicine-' . Str::random(6);

        if (! array_key_exists($base, $this->slugCounter)) {
            $this->slugCounter[$base] = 0;
            return $base;
        }

        $this->slugCounter[$base]++;
        return $base . '-' . $this->slugCounter[$base];
    }
}
