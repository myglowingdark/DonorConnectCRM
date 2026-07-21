<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonorImportBatch extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'uploaded_by',
        'campaign_id',
        'original_filename',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_skipped',
        'rows_assigned',
        'cap_per_volunteer',
        'volunteer_ids',
        'errors',
        'donor_ids',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'volunteer_ids' => 'array',
            'errors' => 'array',
            'donor_ids' => 'array',
            'tags' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donors(): HasMany
    {
        return $this->hasMany(Donor::class, 'import_batch_id');
    }
}
