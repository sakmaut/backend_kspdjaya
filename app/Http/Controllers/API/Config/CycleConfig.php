<?php

namespace App\Http\Controllers\API\Config;

class CycleConfig
{
    public static function getAllowedListbanCycles()
    {
        return ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];
    }

    public static function getAllowedDeployCycles()
    {
        return ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'];
    }
}
