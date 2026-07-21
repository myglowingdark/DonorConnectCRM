<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorImportBatch extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'uploaded_by',
        'original_filename',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_skipped',
        'rows_assigned',
        'cap_per_volunteer',
        'volunteer_ids',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'volunteer_ids' => 'array',
            'errors' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
