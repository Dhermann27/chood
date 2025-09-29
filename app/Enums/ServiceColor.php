<?php

namespace App\Enums;

enum ServiceColor: string
{
    case Orientation = 'orientation';
    case BasicGrooming = 'basic grooming';
    case Enrichment = 'enrichment';
    case FullServiceGrooming = 'full service grooming';
    case Future = 'future';
    case NeedsUpdating = 'needs updating';

    public static function forCategory(?string $category): ?self
    {
        return match (strtolower($category ?? '')) {
            'interview', 'orientation' => self::Orientation,
            'bath', 'basic grooming' => self::BasicGrooming,
            'enrichment' => self::Enrichment,
            'full service grooming' => self::FullServiceGrooming,
            default => null,
        };
    }

    public function googleColorId(): string
    {
        return match ($this) {
            self::Orientation => '11',     // red
            self::BasicGrooming => '5',    // yellow
            self::Enrichment => '9',       // blue
            self::FullServiceGrooming => '10', // green
            self::Future => '8',           // gray
            self::NeedsUpdating => '4',    // alert red
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::Orientation => '#9E1B32',
            self::BasicGrooming => '#FFDE17',
            self::Enrichment => '#87B3D1',
            self::FullServiceGrooming => '#88C999',
            self::Future => '#58595B',
            self::NeedsUpdating => '#FF4F4F',
        };
    }
}
