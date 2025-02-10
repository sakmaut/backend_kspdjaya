<?php

namespace App\Http\Resources;

use App\Models\M_Credit;
use App\Models\M_CrProspect;
use App\Models\M_CrSurveyDocument;
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
        $check_exist = M_Credit::where('ORDER_NUMBER', $this->order_number)->first();

        $data = [
            'id' => $this->id,
            "flag" => !$check_exist ? 0 : 1,
            "credit_id" => !$check_exist ? null : $check_exist->ID ?? null,
            "jenis_angsuran" =>  empty($this->INSTALLMENT_TYPE) ? $this->jenis_angsuran ?? '' : $this->INSTALLMENT_TYPE ?? '',
            'order_number' => $this->order_number,
            'visit_date' => $this->visit_date  == null ? null : date('d-m-Y', strtotime($this->visit_date)),
            'nama_debitur' => $this->nama_debitur,
            'alamat' => $this->alamat,
            'hp' => $this->hp,
            'plafond' => $this->plafond,
            'status' => $this->APPROVAL_RESULT ?? '',
            'status_code' => $this->CODE ?? '',
            'attachment' => $this->attachment($this->id, "'sp', 'pk', 'dok'")
        ];

        return $data;
    }

    public function attachment($survey_id, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        return $documents;
    }
}
