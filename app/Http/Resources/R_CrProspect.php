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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class R_CrProspect extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // public function toArray(Request $request): array
    // {
    //     $check_exist = M_Credit::where('ORDER_NUMBER', $this->order_number)->first();
    //     $getApproval = M_SurveyApproval::where('CR_SURVEY_ID', $this->id)->first();

    //     $type = empty($this->INSTALLMENT_TYPE) ? ($this->jenis_angsuran ?? '') : ($this->INSTALLMENT_TYPE ?? '');

    //     switch (strtolower($type)) {
    //         case 'bunga_menurun':
    //             $jenis_angsuran =  'BUNGA MENURUN';
    //             break;
    //         case 'bulanan':
    //             $jenis_angsuran =  'BULANAN';
    //             break;
    //         case 'musiman':
    //             $jenis_angsuran =  'MUSIMAN';
    //             break;
    //         default:
    //             $jenis_angsuran=  $type;
    //             break;
    //     }

    //     $data = [
    //         'id' => $this->id,
    //         "flag" => !$check_exist ? 0 : 1,
    //         "credit_id" => !$check_exist ? null : $check_exist->ID ?? null,
    //         "jenis_angsuran" =>  $jenis_angsuran,
    //         'order_number' => $this->order_number,
    //         'visit_date' => $this->visit_date  == null ? null : date('d-m-Y', strtotime($this->visit_date)),
    //         'nama_debitur' => $this->nama_debitur,
    //         'alamat' => $this->alamat,
    //         'hp' => $this->hp,
    //         'plafond' => $this->plafond,
    //         'status' => $getApproval ? $getApproval->APPROVAL_RESULT : '',
    //         'status_code' => $getApproval ? $getApproval->CODE : '',
    //         'attachment' => $this->attachment($this->id, "'sp', 'pk', 'dok'")
    //     ];

    //     return $data;
    // }

    public function toArray(Request $request): array
    {
        $application = $this->cr_application;
        $credit = $application?->credit;
        $approval = $this->survey_approval;

        $type = $application->INSTALLMENT_TYPE
            ?? $this->jenis_angsuran
            ?? '';

        $jenis_angsuran = match (strtolower($type)) {
            'bunga_menurun' => 'BUNGA MENURUN',
            'bulanan' => 'BULANAN',
            'musiman' => 'MUSIMAN',
            default => $type
        };

        return [
            'id' => $this->id,
            "flag" => $credit ? 1 : 0,
            "credit_id" => $credit?->ID,
            "jenis_angsuran" => $jenis_angsuran,
            'order_number' => $application?->ORDER_NUMBER,
            'visit_date' => $this->visit_date ? Carbon::parse($this->visit_date)->format('d-m-Y') : null,
            'nama_debitur' => $application?->cr_personal?->NAME ?? $this->nama,
            'alamat' => $this->alamat,
            'hp' => $this->hp,
            'plafond' => $application?->SUBMISSION_VALUE ?? $this->plafond,
            'status' => $approval?->APPROVAL_RESULT ?? '',
            'status_code' => $approval?->CODE ?? '',
            'attachment' =>  $this->attachment($this->id, "'sp', 'pk', 'dok'"),
        ];
    }

    // public function latestAttachments()
    // {
    //     return $this->cr_survey_document()
    //         ->whereIn('TYPE', ['sp', 'pk', 'dok'])
    //         ->whereIn(DB::raw('(TYPE, TIMEMILISECOND)'), function ($query) {
    //             $query->selectRaw('TYPE, MAX(TIMEMILISECOND)')
    //                 ->from('cr_survey_document')
    //                 ->whereColumn('CR_SURVEY_ID', 'cr_survey.CR_SURVEY_ID')
    //                 ->whereIn('TYPE', ['sp', 'pk', 'dok'])
    //                 ->groupBy('TYPE');
    //         })
    //         ->orderByDesc('TIMEMILISECOND')
    //         ->get();
    // }

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
