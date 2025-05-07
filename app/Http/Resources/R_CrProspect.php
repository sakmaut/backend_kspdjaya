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
        $getApproval = M_SurveyApproval::where('CR_SURVEY_ID', $this->id)->first();

        $data = [
            'id' => $this->id,
            'ao_id' => $this->ao_id,
            'visit_date' => $this->visit_date,
            'tujuan_kredit' => $this->tujuan_kredit,
            'jenis_produk' => $this->jenis_produk,
            'plafond' => $this->plafond,
            'tenor' => $this->tenor,
            'nama' => $this->nama,
            'ktp' => $this->ktp,
            'kk' => $this->kk,
            'tgl_lahir' => $this->tgl_lahir,
            'alamat' => $this->alamat,
            'rt' => $this->rt,
            'rw' => $this->rw,
            'provinsi' => $this->province,
            'kota' => $this->city,
            'kelurahan' => $this->kelurahan,
            'kecamatan' => $this->kecamatan,
            'kode_pos' => $this->zip_code,
            'hp' => $this->hp,
            'usaha' => $this->usaha,
            'sector' => $this->sector,
            'coordinate' => $this->coordinate,
            'accurate' => $this->accurate,
            'slik' => $this->slik_flag,
        ];

        return $data;
    }
}
