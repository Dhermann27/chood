<?php

namespace App\Enums;

enum YardCodes: string
{
    case DEFAULT = 'default';
    case THREE_TWO = 'three-two';
    case THREE = 'three';
    case FOUR_TWO = 'four-two';
    case FOUR_THREE = 'four-three';
    case FOUR = 'four';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => '2 yards, all day',
            self::THREE_TWO => '3 yards, 2 11am-1pm',
            self::THREE => '3 yards, all day',
            self::FOUR_TWO => '4 yards, 2 11am-1pm',
            self::FOUR_THREE => '4 yards, 3 11am-1pm',
            self::FOUR => 'All yards, all day',
        };
    }

    public function allowedYards(bool $isMidday): array
    {
        $s = YardIds::SMALL->value;
        $l = YardIds::LARGE->value;
        $a = YardIds::ACTIVE->value;
        $m = YardIds::MEDIUM->value;

        return match ($this) {
            self::DEFAULT => [$s, $l],
            self::THREE_TWO => $isMidday ? [$s, $l] : [$s, $l, $a],
            self::THREE => [$s, $l, $a],
            self::FOUR_TWO => $isMidday ? [$s, $l] : [$s, $l, $a, $m],
            self::FOUR_THREE => $isMidday ? [$s, $l, $a] : [$s, $l, $a, $m],
            self::FOUR => [$s, $l, $a, $m],
        };
    }

}
