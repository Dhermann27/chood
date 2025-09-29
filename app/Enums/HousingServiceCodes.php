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

    public static function isHousingCode(string $code): bool
    {
        return $code === self::BRDC->value || $code === self::BRDL->value
            || $code === self::DCFD->value || $code === self::DCHD->value
            || $code === self::INTV->value;
    }
}
