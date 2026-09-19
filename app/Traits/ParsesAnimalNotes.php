<?php

namespace App\Traits;

trait ParsesAnimalNotes
{
    private function isBoilerplate(string $text): bool
    {
        return (bool) preg_match('/^(none|no \w+ needed|none no \w+ needed)$/i', $text);
    }
}
