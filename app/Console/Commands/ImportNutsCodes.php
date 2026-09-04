<?php

namespace App\Console\Commands;

use App\Models\NutsCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('import:nuts-codes {file}')]
#[Description('Import NUTS (Nomenclature of Territorial Units for Statistics) codes from a CSV file (code,label,level,parent_code columns).')]
class ImportNutsCodes extends Command
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
        $levelIndex = array_search('level', $header, true);
        $parentCodeIndex = array_search('parent_code', $header, true);

        if ($codeIndex === false || $labelIndex === false || $levelIndex === false) {
            $this->error('CSV must have "code", "label", and "level" columns ("parent_code" optional).');
            fclose($handle);

            return self::FAILURE;
        }

        /** @var array<int, array{code: string, label: string, level: int, parent_code: string|null}> $rows */
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $code = trim((string) ($row[$codeIndex] ?? ''));
            $label = trim((string) ($row[$labelIndex] ?? ''));
            $level = trim((string) ($row[$levelIndex] ?? ''));

            if ($code === '' || $label === '' || $level === '') {
                continue;
            }

            $parentCode = $parentCodeIndex !== false
                ? trim((string) ($row[$parentCodeIndex] ?? ''))
                : '';

            $rows[] = [
                'code' => $code,
                'label' => $label,
                'level' => (int) $level,
                'parent_code' => $parentCode !== '' ? $parentCode : null,
            ];
        }

        fclose($handle);

        // Import lowest level first so a row's parent already exists when its parent_id is resolved.
        usort($rows, static fn (array $a, array $b): int => $a['level'] <=> $b['level']);

        $idsByCode = [];

        foreach ($rows as $row) {
            $parentId = $row['parent_code'] !== null ? ($idsByCode[$row['parent_code']] ?? null) : null;

            $nutsCode = NutsCode::query()->updateOrCreate(['code' => $row['code']], [
                'label' => $row['label'],
                'level' => $row['level'],
                'parent_id' => $parentId,
                'active' => true,
            ]);

            $idsByCode[$row['code']] = $nutsCode->id;
        }

        $this->info('Imported '.count($rows).' NUTS codes.');

        return self::SUCCESS;
    }
}
