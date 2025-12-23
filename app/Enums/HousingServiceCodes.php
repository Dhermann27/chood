<?php
declare(strict_types=1);

namespace App\Enums;

enum HousingServiceCodes: string
{
    case BRDC = 'BRDC';
    case BRDL = 'BRDL';
    case DCFD = 'DCFD';
    case DCHD = 'DCHD'; // Testing: DCH in Test
    case INTV = 'INTV';
    case UNKNOWN = 'GTO';

    public static function housingValues(): array
    {
        return array_map(
            fn(self $c) => $c->value,
            array_filter(self::cases(), fn(self $c) => $c !== self::UNKNOWN)
        );
    }

    public static function isHousingCode(string $code): bool
    {
        return $code === self::BRDC->value || $code === self::BRDL->value
            || $code === self::DCFD->value || $code === self::DCHD->value
            || $code === self::INTV->value;
    }

    public static function isCalendarCode(string $code): bool
    {
        return $code !== '' && $code !== self::BRDC->value && $code !== self::BRDL->value
            && $code !== self::DCFD->value && $code !== self::DCHD->value;
    }
}
