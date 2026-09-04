<?php

namespace App\Console\Commands;

use App\Models\CpvCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('import:cpv-codes {file}')]
#[Description('Import CPV (Common Procurement Vocabulary) codes from a CSV file (code,label columns).')]
class ImportCpvCodes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $file */
        $file = $this->argument('file');

        if (! File::exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            $this->error("Unable to open file: {$file}");

            return self::FAILURE;
        }

        $header = array_map(
            static fn (?string $column): string => strtolower(trim($column ?? '')),
            fgetcsv($handle) ?: [],
        );

        $codeIndex = array_search('code', $header, true);
        $labelIndex = array_search('label', $header, true);

        if ($codeIndex === false || $labelIndex === false) {
            $this->error('CSV must have "code" and "label" columns.');
            fclose($handle);

            return self::FAILURE;
        }

        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $code = trim((string) ($row[$codeIndex] ?? ''));
            $label = trim((string) ($row[$labelIndex] ?? ''));

            if ($code === '' || $label === '') {
                continue;
            }

            CpvCode::query()->updateOrCreate(['code' => $code], [
                'label' => $label,
                'active' => true,
            ]);

            $count++;
        }

        fclose($handle);

        $this->info("Imported {$count} CPV codes.");

        return self::SUCCESS;
    }
}
