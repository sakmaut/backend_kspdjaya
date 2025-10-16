<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Rs_SurveyLogs extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userName = User::where('username', $this->USER_ID)->first();

        return [
            'no_surat' => $this->REFERENCE_ID ?? "",
            'ket' => $this->DESCRIPTION ?? "",
            'tgl_jb' => Carbon::parse($this->CONFIRM_DATE)->format('Y-m-d') ?? "",
            'file' => json_decode($this->PATH ?? []),
            'oleh' => $userName->fullname ?? "",
            'tgl_buat' => $this->CREATED_AT ?? "",
        ];
    }
}
