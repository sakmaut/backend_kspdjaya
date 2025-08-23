<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_CrSurvey extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        switch (strtolower($this->jenis_angsuran)) {
            case 'bunga_menurun':
                $jenis_angsuran =  'BUNGA MENURUN';
                break;
            case 'bulanan':
                $jenis_angsuran =  'BULANAN';
                break;
            case 'musiman':
                $jenis_angsuran =  'MUSIMAN';
                break;
            default:
                $jenis_angsuran =  $this->jenis_angsuran;
                break;
        }

        $data = [
            'id' => $this->id,
            "jenis_angsuran" =>  $jenis_angsuran ?? '',
            'visit_date' => $this->visit_date ? date('d-m-Y', strtotime($this->visit_date)) : null,
            'nama_debitur' => $this->nama ?? '',
            'alamat' => $this->alamat ?? '',
            'hp' => $this->hp ?? '',
            'plafond' => $this->plafond ?? 0,
            'status' => $this->survey_approval->APPROVAL_RESULT ?? '',
            'status_code' => $this->survey_approval->CODE ?? ''
        ];

        return $data;
    }
}
