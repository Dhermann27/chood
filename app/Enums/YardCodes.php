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
        return match($this) {
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
        return match ($this) {
            self::DEFAULT => [1000, 1001],
            self::THREE_TWO => $isMidday ? [1000, 1001] : [1000, 1001, 1002],
            self::THREE => [1000, 1001, 1002],
            self::FOUR_TWO => $isMidday ? [1000, 1001] : [1000, 1001, 1002, 1003],
            self::FOUR_THREE => $isMidday ? [1000, 1001, 1002] : [1000, 1001, 1002, 1003],
            self::FOUR => [1000, 1001, 1002, 1003, 1004],
        };
    }

}
