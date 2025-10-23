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
        $userName = User::where('id', $this->CREATED_BY)->first();

        return [
            'no_surat' => $this->REFERENCE_ID ?? "",
            'ket' => $this->DESCRIPTION ?? "",
            'tgl_jb' => $this->CONFIRM_DATE ? Carbon::parse($this->CONFIRM_DATE)->format('Y-m-d') : null,
            'file' => json_decode($this->PATH ?? []),
            'oleh' => $userName->fullname ?? "",
            'tgl_buat' => $this->CREATED_AT ?? "",
        ];
    }
}
