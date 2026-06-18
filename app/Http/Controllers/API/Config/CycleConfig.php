<?php

namespace App\Http\Controllers\API\Config;

class CycleConfig
{
    const BASE_CYCLES = ['C0', 'C1', 'C2', 'C3', 'C4', 'C5', 'C6'];

    public static function getAllowedListbanCycles()
    {
        return [...self::BASE_CYCLES, 'CM', 'CN'];
    }

    public static function getAllowedDeployCycles()
    {
        return self::BASE_CYCLES;
    }
}
