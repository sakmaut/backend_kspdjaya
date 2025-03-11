<?php

namespace App\Http\Controllers\API;

enum StatusApproval:string
{
    case DRAFT_SURVEY = 'DRSVY';
    case MENUNGGU_ADMIN = 'WADM';
    case DRAFT_ORDER = 'DROR';
    case MENUNGGU_KAPOS = 'WAKPS';
    case APPROVE_KAPOS = 'APKPS';
    case REVISI_KAPOS = 'REORKPS';
    case CANCEL_KAPOS = 'CLKPS';
    case MENUNGGU_HO = 'WAHO';
    case APPROVE_HO = 'APHO';
    case REVISI_HO = 'REORHO';
    case CANCEL_HO = 'CLHO';
}
