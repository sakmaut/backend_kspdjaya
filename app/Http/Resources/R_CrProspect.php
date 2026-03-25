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
            "cabang" => $this->branch->NAME ?? null,
            "credit_id" => $credit?->ID,
            "jenis_angsuran" => $jenis_angsuran,
            'order_number' => $application?->ORDER_NUMBER,
            'visit_date' => $this->visit_date ? Carbon::parse($this->visit_date)->format('d-m-Y') : null,
            'nama_debitur' => $application?->cr_personal?->NAME ?? $this->nama,
            'alamat' => $this->alamat,
            'hp' => $this->hp,
            'plafond' => (int) $application?->SUBMISSION_VALUE ?? $this->plafond,
            'status' => $approval?->APPROVAL_RESULT ?? '',
            'status_code' => $approval?->CODE ?? '',
            'attachment' =>  $this->cr_survey_document
                            ->whereIn('TYPE', ['sp', 'pk', 'dok'])
                            ->sortByDesc('TIMEMILISECOND')
                            ->groupBy('TYPE')
                            ->map(fn($docs) => $docs->first())
                            ->values(),
        ];
    }
}
