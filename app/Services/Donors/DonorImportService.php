<?php

namespace App\Services\Donors;

use App\Enums\DonorStatus;
use App\Models\Donor;
use App\Models\DonorImportBatch;
use App\Models\Organization;
use App\Models\User;
use App\Support\Languages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DonorImportService
{
    public function __construct(private AssignmentService $assignmentService) {}

    /**
     * @param  array{
     *     volunteer_ids?: array<int>,
     *     cap_per_volunteer?: int|null,
     *     assign_after_import?: bool
     * }  $options
     */
    public function import(
        int $organizationId,
        UploadedFile $file,
        User $actor,
        array $options = [],
    ): DonorImportBatch {
        $rows = $this->parseFile($file);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'No donor rows found. Use the template headers: full_name, phone, email, city, state, preferred_language.',
            ]);
        }

        $volunteerIds = array_values(array_unique(array_map('intval', $options['volunteer_ids'] ?? [])));
        $cap = isset($options['cap_per_volunteer']) ? (int) $options['cap_per_volunteer'] : null;
        $assign = (bool) ($options['assign_after_import'] ?? false);

        if ($assign && empty($volunteerIds)) {
            throw ValidationException::withMessages([
                'volunteer_ids' => 'Select at least one volunteer to assign uploaded donors.',
            ]);
        }

        if ($cap !== null && $cap < 1) {
            throw ValidationException::withMessages([
                'cap_per_volunteer' => 'Cap must be at least 1 when provided.',
            ]);
        }

        $organization = Organization::query()->findOrFail($organizationId);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $assigned = 0;
        $errors = [];
        $importedDonorIds = [];

        DB::transaction(function () use (
            $organization,
            $organizationId,
            $rows,
            &$created,
            &$updated,
            &$skipped,
            &$errors,
            &$importedDonorIds,
        ) {
            foreach ($rows as $index => $row) {
                $line = $index + 2; // header is line 1
                $name = trim((string) ($row['full_name'] ?? $row['name'] ?? ''));
                $phone = $this->normalizePhone((string) ($row['phone'] ?? $row['mobile'] ?? ''));
                $email = strtolower(trim((string) ($row['email'] ?? ''))) ?: null;

                if ($name === '') {
                    $skipped++;
                    $errors[] = "Row {$line}: full_name is required.";
                    continue;
                }

                if ($phone === '' && blank($email)) {
                    $skipped++;
                    $errors[] = "Row {$line}: phone or email is required.";
                    continue;
                }

                $language = strtolower(trim((string) ($row['preferred_language'] ?? $row['language'] ?? '')));
                if ($language !== '' && ! in_array($language, Languages::codes(), true)) {
                    $matched = collect(Languages::options())->search(
                        fn ($label) => strcasecmp($label, $language) === 0
                    );
                    $language = $matched !== false ? $matched : null;
                } else {
                    $language = $language !== '' ? $language : null;
                }

                $existing = null;
                if ($phone !== '') {
                    $existing = Donor::query()
                        ->forOrganization($organizationId)
                        ->where('phone', $phone)
                        ->first();
                }
                if (! $existing && $email) {
                    $existing = Donor::query()
                        ->forOrganization($organizationId)
                        ->where('email', $email)
                        ->first();
                }

                $rawTags = trim((string) ($row['tags'] ?? $row['tag'] ?? ''));
                $rowTags = $rawTags !== ''
                    ? collect(preg_split('/[|,;]+/', $rawTags) ?: [])
                        ->map(fn ($t) => strtolower(trim($t)))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all()
                    : [];

                $payload = [
                    'full_name' => $name,
                    'phone' => $phone !== '' ? $phone : ($existing?->phone),
                    'email' => $email ?: $existing?->email,
                    'city' => trim((string) ($row['city'] ?? '')) ?: $existing?->city,
                    'state' => trim((string) ($row['state'] ?? '')) ?: $existing?->state,
                    'preferred_language' => $language ?: $existing?->preferred_language,
                ];

                if ($existing) {
                    $tags = collect($existing->tags ?? [])
                        ->merge(['imported'])
                        ->merge($rowTags)
                        ->unique()
                        ->values()
                        ->all();
                    $existing->update([...$payload, 'tags' => $tags]);
                    $importedDonorIds[] = $existing->id;
                    $updated++;
                } else {
                    if (! $organization->canAcceptNewDonors(1)) {
                        $skipped++;
                        $limit = (int) $organization->donors_limit;
                        $errors[] = "Row {$line}: donor list limit reached (limit {$limit}).";
                        continue;
                    }

                    $donor = Donor::create([
                        'organization_id' => $organizationId,
                        'external_donor_id' => 'import-'.Str::lower(Str::ulid()),
                        'donor_status' => DonorStatus::New,
                        'do_not_call' => false,
                        'country' => 'India',
                        'total_donated' => 0,
                        'tags' => array_values(array_unique(array_merge(['imported'], $rowTags))),
                        ...$payload,
                    ]);
                    $importedDonorIds[] = $donor->id;
                    $created++;
                }
            }
        });

        if ($assign && $importedDonorIds) {
            $assigned = $this->assignmentService->distributeEquallyWithCap(
                $organizationId,
                $volunteerIds,
                $actor,
                $cap,
                $importedDonorIds,
            );
        }

        return DonorImportBatch::create([
            'organization_id' => $organizationId,
            'uploaded_by' => $actor->id,
            'original_filename' => $file->getClientOriginalName(),
            'rows_total' => $rows->count(),
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_skipped' => $skipped,
            'rows_assigned' => $assigned,
            'cap_per_volunteer' => $cap,
            'volunteer_ids' => $volunteerIds ?: null,
            'errors' => $errors ?: null,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    protected function parseFile(UploadedFile $file)
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $path = $file->getRealPath();

        if (in_array($extension, ['xlsx', 'xlsm'], true)) {
            return collect($this->parseXlsx($path));
        }

        return collect($this->parseCsv($path));
    }

    /**
     * @return list<array<string, string>>
     */
    protected function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read uploaded file.']);
        }

        $header = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => Str::snake(trim((string) $h)), $data);
                continue;
            }

            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Minimal XLSX reader (first sheet) without external packages.
     *
     * @return list<array<string, string>>
     */
    protected function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'Unable to open Excel file.']);
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    $shared[] = trim((string) ($si->t ?? $si->r->t ?? ''));
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            throw ValidationException::withMessages(['file' => 'Excel sheet1.xml not found.']);
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet) {
            throw ValidationException::withMessages(['file' => 'Unable to parse Excel sheet.']);
        }

        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $rIndex = ((int) $row['r']) - 1;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/([A-Z]+)/', $ref, $m);
                $col = $this->columnIndex($m[1] ?? 'A');
                $type = (string) ($cell['t'] ?? '');
                $value = (string) ($cell->v ?? '');
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $matrix[$rIndex][$col] = trim($value);
            }
        }

        if ($matrix === []) {
            return [];
        }

        ksort($matrix);
        $headerRow = array_shift($matrix);
        ksort($headerRow);
        $headers = [];
        foreach ($headerRow as $col => $label) {
            $headers[$col] = Str::snake(trim($label));
        }

        $rows = [];
        foreach ($matrix as $row) {
            $mapped = [];
            $hasValue = false;
            foreach ($headers as $col => $key) {
                if ($key === '') {
                    continue;
                }
                $val = trim((string) ($row[$col] ?? ''));
                $mapped[$key] = $val;
                if ($val !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    protected function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        // Keep leading + and digits/spaces.
        $clean = preg_replace('/[^\d+]/', '', $phone) ?: '';

        return trim($clean);
    }
}
