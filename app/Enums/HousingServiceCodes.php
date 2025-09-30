<?php
declare(strict_types=1);

namespace App\Enums;

enum HousingServiceCodes: string
{
    case BRDC = 'BRDC';
    case BRDL = 'BRDL';
    case DCFD = 'DCFD';
    case DCHD = 'DCHD';
    case INTV = 'INTV';
    case UNKNOWN = 'GTO';

    const array HOUSING_CODES_ARRAY = [self::BRDC->value, self::BRDL->value, self::DCFD->value, self::DCHD->value,
        self::INTV->value];

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
