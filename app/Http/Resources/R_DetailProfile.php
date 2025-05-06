<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use App\Models\M_MasterMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailProfile extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nama' => $this->NAMA ?? '',
            'cabang_nama' => $this->hr_rolling->KANTOR ?? '',
            'gender' => $this->GENDER ?? '',
            'email' => $this->EMAIL ?? '',
            'no_hp' => $this->HP ?? '',
            'no_ktp' => $this->NO_KTP ?? '',
            'alamat' => $this->ALAMAT_KTP ?? '',
            'status' => $this->STATUS_MST ?? '',
            'jabatan' => $this->hr_rolling->hr_position->MASTER_NAME ?? '',
            // 'photo_personal' => M_HrEmployeeDocument::attachment($request->user()->id, 'personal'),
            // 'accessMenu' => $menuItems
        ];
    }
}
