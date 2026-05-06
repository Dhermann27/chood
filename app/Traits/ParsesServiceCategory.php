<?php

namespace App\Traits;

trait ParsesServiceCategory
{
    private function serviceCategory(string $name): ?string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'day care') || str_contains($lower, 'daycare') || str_contains($lower, 'day camp')) return 'Daycare';
        if (str_contains($lower, 'board')) return 'Boarding';
        if (str_contains($lower, 'train')) return 'Training';
        if (str_contains($lower, 'groom') || str_contains($lower, 'bath') || str_contains($lower, 'nail')) return 'Grooming';
        if (str_contains($lower, 'enrich')) return 'Enrichment';
        return null;
    }
}
