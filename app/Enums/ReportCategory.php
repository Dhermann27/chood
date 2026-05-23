<?php
declare(strict_types=1);

namespace App\Enums;

enum ReportCategory: string
{
    case FSG = 'fsg';
    case Bath = 'bath';
    case Nail = 'nail';
    case Enrichment = 'enrichment';
    case Training = 'training';
    case Other = 'other';

    /**
     * Resolve from structured API fields — no string matching.
     * Falls back to name-based matching only when both API fields are inconclusive.
     */
    public static function resolve(?string $bookingCategoryId, ?string $accountCodeId, ?string $name = null): self
    {
        $fromApi = match ((int)$bookingCategoryId) {
            1, 2 => self::Enrichment,
            3 => self::FSG,
            4 => self::Training,
            default => match ((int)$accountCodeId) {
                8 => self::Bath,
                9 => self::Enrichment,
                10 => self::FSG,
                12 => self::Nail,
                default => null,
            },
        };

        if ($fromApi !== null) return $fromApi;
        return $name ? self::fromServiceName($name) : self::Other;
    }

    /**
     * Fallback for HTML-parsed appointment names where API fields are unavailable.
     */
    public static function fromServiceName(string $name): self
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'full service') || str_contains($lower, 'full-service')) return self::FSG;
        if (str_contains($lower, 'bath') || str_contains($lower, 'basic grooming')) return self::Bath;
        if (str_contains($lower, 'nail')) return self::Nail;
        if (str_contains($lower, 'enrich')) return self::Enrichment;
        if (str_contains($lower, 'train')) return self::Training;
        return self::Other;
    }
}
