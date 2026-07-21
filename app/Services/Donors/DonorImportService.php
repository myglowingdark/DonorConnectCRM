<?php

namespace App\Services\Donors;

use App\Enums\DonorStatus;
use App\Models\Campaign;
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
     *     assign_after_import?: bool,
     *     tags?: array<int, string>|string|null,
     *     campaign_id?: int|null,
     *     new_campaign_name?: string|null
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
                'file' => 'No donor rows found. Use the template headers: full_name, phone, email, city, state, preferred_language, tags.',
            ]);
        }

        $volunteerIds = array_values(array_unique(array_map('intval', $options['volunteer_ids'] ?? [])));
        $cap = isset($options['cap_per_volunteer']) ? (int) $options['cap_per_volunteer'] : null;
        $assign = (bool) ($options['assign_after_import'] ?? false);
        $batchTags = $this->normalizeTags($options['tags'] ?? []);

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
        $campaignId = $this->resolveCampaignId($organizationId, $options);

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
            $batchTags,
            $campaignId,
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
                $rowTags = $rawTags !== '' ? $this->normalizeTags($rawTags) : [];

                $payload = [
                    'full_name' => $name,
                    'phone' => $phone !== '' ? $phone : ($existing?->phone),
                    'email' => $email ?: $existing?->email,
                    'city' => trim((string) ($row['city'] ?? '')) ?: $existing?->city,
                    'state' => trim((string) ($row['state'] ?? '')) ?: $existing?->state,
                    'preferred_language' => $language ?: $existing?->preferred_language,
                ];

                if ($campaignId) {
                    $payload['campaign_id'] = $campaignId;
                }

                if ($existing) {
                    $tags = collect($existing->tags ?? [])
                        ->merge(['imported'])
                        ->merge($batchTags)
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
                        'tags' => array_values(array_unique(array_merge(['imported'], $batchTags, $rowTags))),
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

        $batch = DonorImportBatch::create([
            'organization_id' => $organizationId,
            'uploaded_by' => $actor->id,
            'campaign_id' => $campaignId,
            'original_filename' => $file->getClientOriginalName(),
            'rows_total' => $rows->count(),
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_skipped' => $skipped,
            'rows_assigned' => $assigned,
            'cap_per_volunteer' => $cap,
            'volunteer_ids' => $volunteerIds ?: null,
            'errors' => $errors ?: null,
            'donor_ids' => $importedDonorIds ?: null,
            'tags' => $batchTags ?: null,
        ]);

        if ($importedDonorIds) {
            Donor::query()
                ->whereIn('id', $importedDonorIds)
                ->update(['import_batch_id' => $batch->id]);
        }

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveCampaignId(int $organizationId, array $options): ?int
    {
        $newName = trim((string) ($options['new_campaign_name'] ?? ''));
        if ($newName !== '') {
            $campaign = Campaign::query()->create([
                'organization_id' => $organizationId,
                'name' => $newName,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ]);

            return $campaign->id;
        }

        $campaignId = isset($options['campaign_id']) ? (int) $options['campaign_id'] : 0;
        if ($campaignId < 1) {
            return null;
        }

        $exists = Campaign::query()
            ->where('organization_id', $organizationId)
            ->whereKey($campaignId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'campaign_id' => 'Selected campaign was not found for this organization.',
            ]);
        }

        return $campaignId;
    }

    /**
     * @param  array<int, string>|string|null  $tags
     * @return list<string>
     */
    protected function normalizeTags(array|string|null $tags): array
    {
        if ($tags === null || $tags === '' || $tags === []) {
            return [];
        }

        $parts = is_array($tags)
            ? $tags
            : (preg_split('/[|,;]+/', (string) $tags) ?: []);

        return collect($parts)
            ->map(fn ($t) => strtolower(trim((string) $t)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, string>>
     */
    protected function parseFile(UploadedFile $file)
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $path = $file->getRealPath();

        // Some servers report xlsx as zip / octet-stream — sniff by ZIP contents.
        if (! in_array($extension, ['xlsx', 'xlsm', 'csv', 'txt'], true)) {
            if ($this->looksLikeXlsx($path)) {
                $extension = 'xlsx';
            } else {
                $extension = 'csv';
            }
        }

        if (in_array($extension, ['xlsx', 'xlsm'], true) || $this->looksLikeXlsx($path)) {
            return collect($this->parseXlsx($path));
        }

        return collect($this->parseCsv($path));
    }

    protected function looksLikeXlsx(string $path): bool
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        return $ok;
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
                // Strip UTF-8 BOM from first header cell.
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? (string) $data[0];
                }
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
                    if (isset($si->t)) {
                        $shared[] = trim((string) $si->t);
                    } else {
                        $text = '';
                        foreach ($si->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                        $shared[] = trim($text);
                    }
                }
            }
        }

        $sheetPath = $this->resolveFirstSheetPath($zip);
        $sheetXml = $sheetPath ? $zip->getFromName($sheetPath) : false;
        $zip->close();

        if (! $sheetXml) {
            throw ValidationException::withMessages(['file' => 'Excel worksheet not found. Export sheet 1 as .xlsx or use the CSV template.']);
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
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
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

    protected function resolveFirstSheetPath(\ZipArchive $zip): ?string
    {
        $candidates = ['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet.xml'];
        foreach ($candidates as $path) {
            if ($zip->locateName($path) !== false) {
                return $path;
            }
        }

        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! $workbook || ! $rels) {
            return null;
        }

        $wb = simplexml_load_string($workbook);
        $rx = simplexml_load_string($rels);
        if (! $wb || ! $rx) {
            return null;
        }

        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = $wb->xpath('//m:sheets/m:sheet') ?: [];
        if ($sheets === []) {
            return null;
        }

        $rid = (string) ($sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
        if ($rid === '') {
            return null;
        }

        $rx->registerXPathNamespace('pr', 'http://schemas.openxmlformats.org/package/2006/relationships');
        foreach ($rx->Relationship ?? [] as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                if (! str_starts_with($target, 'xl/')) {
                    $target = 'xl/'.ltrim($target, '/');
                }

                return $target;
            }
        }

        return null;
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

        $clean = preg_replace('/[^\d+]/', '', $phone) ?: '';

        return trim($clean);
    }
}
