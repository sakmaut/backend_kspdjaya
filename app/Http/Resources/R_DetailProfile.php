<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use App\Models\M_MasterMenu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailProfile extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nama' => $this->NAMA ?? '',
            'cabang_nama' => $this->hr_rolling->KANTOR ?? '',
            'gender' => $this->GENDER,
            'no_hp' => $this->HP,
            // 'cabang_id' => $branch->ID ?? null,
            // 
            // 'jabatan' =>$request->user()->position,
            // 'no_ktp' => $request->user()->no_ktp,
            // 'alamat' =>$request->user()->alamat,
            // 
            // 
            // 'status' => $request->user()->status,
            // 'photo_personal' => M_HrEmployeeDocument::attachment($request->user()->id, 'personal'),
            // 'accessMenu' => $menuItems
        ];
    }
}
