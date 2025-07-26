<?php

namespace App\Http\Controllers\Enum\UserPosition;

class UserPositionEnum
{
    public const MCF = 'MCF';
    public const KOLEKTOR = 'KOLEKTOR';
    public const ADMIN = 'ADMIN';
    public const KAPOS = 'KAPOS';
    public const HO = 'HO';
    public const SUPERADMIN = 'SUPERADMIN';

    public function checkUserPositionPayment($request)
    {
        $getCurrentPosition = $request->user()->position;
        $setPositionAvailable  = [$this::MCF, $this::KOLEKTOR];

        return in_array($getCurrentPosition, $setPositionAvailable);
    }
}
