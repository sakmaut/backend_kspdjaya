<?php

namespace App\Http\Controllers\API\Config;

class CycleConfig
{
    public static function getAllowedListbanCycles()
    {
        return ['C0','C1', 'C2', 'C3', 'C4', 'C5', 'C6','CM','CN'];
    }

    public static function getAllowedDeployCycles()
    {
        return ['C0','C1', 'C2', 'C3', 'C4', 'C5', 'C6'];
    }
}
