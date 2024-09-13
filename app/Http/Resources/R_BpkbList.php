<?php

namespace App\Http\Resources;

use App\Models\M_BpkbDetail;
use App\Models\M_Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_BpkbList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $branch = M_Branch::where('ID',$this->FROM_BRANCH)->first();
        $results = DB::table('bpkb_detail as a')
                    ->leftJoin('cr_collateral as b','b.ID', '=', 'a.COLLATERAL_ID')
                    ->where('a.BPKB_TRANSACTION_ID', $this->ID)
                    ->select('b.POLICE_NUMBER', 'b.ON_BEHALF', 'b.CHASIS_NUMBER', 'b.ENGINE_NUMBER', 'b.BPKB_NUMBER', 'b.STNK_NUMBER')
                    ->get();
        $user = User::where('id',$this->CREATED_BY)->first();

        return [
            "id" => $this->ID,
            "dari_cabang" =>$branch->NAME ??null,
            "ke_cabang" =>  $this->TO_BRANCH,
            "keterangan" => $this->NOTE,
            "admin" => $user->fullname??null,
            "kurir" => $this->COURIER,
            "tgl" => Carbon::parse($this->CREATED_AT)->format('Y-m-d'),
            "bpkb" => $results
        ];
    }
}
