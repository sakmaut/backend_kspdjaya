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
        $branch = M_Branch::find($request->user()->branch_id);

        $menuItems = M_MasterMenu::query()
                    ->select('master_menu.menu_name')
                    ->join('master_users_access_menu as t1', 'master_menu.id', '=', 't1.master_menu_id')
                    ->where('t1.users_id', $request->user()->id)
                    ->where('master_menu.deleted_by', null)
                    ->whereIn('master_menu.status', ['active', 'Active'])
                    ->pluck('master_menu.menu_name');

        
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
            'photo_personal' => M_HrEmployeeDocument::attachment($request->user()->id, 'personal'),
            'accessMenu' => $menuItems
        ];
    }
}
