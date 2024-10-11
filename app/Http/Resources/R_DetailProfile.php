<?php

namespace App\Http\Resources;

use App\Models\M_Branch;
use App\Models\M_HrEmployeeDocument;
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
        $attachment = M_HrEmployeeDocument::where('USERS_ID',$request->user()->id)->get();

        $branch = M_Branch::find($request->user()->id);
        
        return [
            'id' => $request->user()->id,
            'username' => $request->user()->username,
            'nama' => $request->user()->fullname,
            'email' => $request->user()->email,
            'cabang_id' => $branch->ID ?? null,
            'cabang_nama' => $branch->NAME ?? null,
            'jabatan' =>$request->user()->position,
            'no_ktp' => $request->user()->no_ktp,
            'alamat' =>$request->user()->alamat,
            'gender' => $request->user()->gender,
            'no_hp' => $request->user()->mobile_number,
            'status' => $request->user()->status,
            'attachment' => [$attachment]
        ];
    }
}
