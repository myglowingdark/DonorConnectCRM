<?php

namespace App\Enums;

enum MetaTemplateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Meta approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Paused => 'Paused',
        };
    }

    public static function fromMetaApi(?string $status): self
    {
        return match (strtoupper((string) $status)) {
            'APPROVED' => self::Approved,
            'PENDING', 'IN_APPEAL', 'PENDING_DELETION' => self::Pending,
            'REJECTED', 'DISABLED' => self::Rejected,
            'PAUSED' => self::Paused,
            default => self::Draft,
        };
    }
}
