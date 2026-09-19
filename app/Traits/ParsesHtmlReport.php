<?php

namespace App\Traits;

use DOMDocument;
use DOMXPath;

trait ParsesHtmlReport
{
    private function loadXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        return new DOMXPath($dom);
    }
}
