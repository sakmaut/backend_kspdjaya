<?php

namespace App\Http\Resources;

use App\Models\M_CrProspect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Models\M_HrEmployee;
use App\Models\M_ProspectApproval;
use App\Models\M_SurveyApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class R_CrProspect extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stts_approval = M_SurveyApproval::where('CR_SURVEY_ID',$this->id)->first();

        $data = [
            'id' => $this->id,
            "flag" => '',
            'order_number' => $this->order_number,
            'visit_date' => $this->visit_date  == null ? null :date('Y-m-d', strtotime($this->visit_date)),
            'nama_debitur' => $this->nama_debitur,
            'alamat' => $this->alamat,
            'hp' => $this->hp,
            'plafond' => $this->plafond,
            'status' => ($stts_approval)?$stts_approval->APPROVAL_RESULT:''
        ];
        
        return $data;
    }
}
