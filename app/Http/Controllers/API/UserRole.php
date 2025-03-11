<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

class UserRole
{
    const MARKETING = 'MCF';
    const KOLEKTOR = 'KOLEKTOR';
    const ADMIN = 'ADMIN';
    const MANAGER = 'KAPOS';
    const HEAD_OFFICE = 'HO';

    const LEVEL_MARKETING = 1;
    const LEVEL_KOLEKTOR = 1;
    const LEVEL_ADMIN = 2;
    const LEVEL_MANAGER = 3;
    const LEVEL_HEAD_OFFICE = 4;

    function getNextLevel($position)
    {
        $positionLevels = [
            self::MARKETING => self::LEVEL_MARKETING,
            self::KOLEKTOR => self::LEVEL_KOLEKTOR,
            self::ADMIN => self::LEVEL_ADMIN,
            self::MANAGER => self::LEVEL_MANAGER,
            self::HEAD_OFFICE => self::LEVEL_HEAD_OFFICE
        ];

        $levelPositions = [
            self::LEVEL_MARKETING => self::MARKETING,
            self::LEVEL_KOLEKTOR => self::KOLEKTOR,
            self::LEVEL_ADMIN => self::ADMIN,
            self::LEVEL_MANAGER => self::MANAGER,
            self::LEVEL_HEAD_OFFICE => self::HEAD_OFFICE
        ];

        if (!array_key_exists($position, $positionLevels)) {
            throw new Exception("Invalid position");
        }

        $currentLevel = $positionLevels[$position];

        $nextLevel = $currentLevel + 1;

        if ($nextLevel > self::LEVEL_HEAD_OFFICE) {
            throw new Exception("No higher level available");
        }

        return $levelPositions[$nextLevel];
    }



}
