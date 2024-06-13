<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_Karyawan extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = M_Branch::find($this->BRANCH_ID);
        return [
            'id' => $this->ID,
            'username' => $this->user ? $this->user['username'] : null,
            'nama' => $this->NAMA,
            'cabang_id' => $branch->ID ?? null,
            'cabang_nama' => $branch->NAME ?? null,
            'jabatan' => $this->JABATAN,
            'gender' => $this->GENDER,
            'no_hp' => $this->HP,
            'status' => $this->STATUS_MST,
            'photo_personal' => M_HrEmployeeDocument::attachment($this->ID, 'personal'),
        ];
    }
}
