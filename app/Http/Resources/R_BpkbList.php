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
                        ->leftJoin('cr_collateral as b', 'b.ID', '=', 'a.COLLATERAL_ID')
                        ->leftJoin('credit as c', 'c.ID', '=', 'b.CR_CREDIT_ID')
                        ->where('a.BPKB_TRANSACTION_ID', $this->ID)
                        ->select(
                            'a.ID as id', 
                            'b.POLICE_NUMBER', 
                            'b.ON_BEHALF', 
                            'b.CHASIS_NUMBER', 
                            'b.ENGINE_NUMBER', 
                            'b.BPKB_NUMBER', 
                            'b.STNK_NUMBER',
                            'a.STATUS', 
                            'c.LOAN_NUMBER'
                        )
                        ->get();
    

        $user = User::where('id',$this->CREATED_BY)->first();
        $toBranch = M_Branch::find($this->TO_BRANCH);

        return [
            "id" => $this->ID,
            "trx_code" =>$this->TRX_CODE ??null,
            "dari_cabang" =>$branch->NAME ??null,
            "ke_cabang" =>  $toBranch->NAME??null,
            "keterangan" => $this->NOTE,
            "type" => strtoupper($this->CATEGORY),
            "admin" => $user->fullname??null,
            "kurir" => $this->COURIER,
            "tgl" => Carbon::parse($this->CREATED_AT)->format('Y-m-d'),
            "jaminan" => $results,
            "jml_jaminan" => $results->count(),
            "status" => $this->STATUS,
        ];
    }
}
