<?php

namespace App\Http\Controllers\API;

class StatusApproval
{
    const DRAFT_SURVEY = 'DRSVY';
    const MENUNGGU_ADMIN = 'WADM';
    const DRAFT_ORDER = 'DROR';
    const MENUNGGU_KAPOS = 'WAKPS';
    const APPROVE_KAPOS = 'APKPS';
    const REVISI_KAPOS = 'REORKPS';
    const CANCEL_KAPOS = 'CLKPS';
    const MENUNGGU_HO = 'WAHO';
    const APPROVE_HO = 'APHO';
    const REVISI_HO = 'REORHO';
    const CANCEL_HO = 'CLHO';
}
