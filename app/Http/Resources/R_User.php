<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_User extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branch = M_Branch::find($this->branch_id);
        
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nama' => $this->fullname,
            'cabang_id' => $branch->ID ?? null,
            'cabang_nama' => $branch->NAME ?? null,
            'jabatan' => $this->position,
            'gender' => $this->gender,
            'no_hp' => $this->mobile_number,
            'status' => $this->status,
            'photo_personal' => M_HrEmployeeDocument::attachment($this->id, 'personal'),
        ];
    }
}
