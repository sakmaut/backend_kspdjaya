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
        $data = [
            'id' => $this->id,
            'ao_id' => $this->ao_id,
            'tgl_kunjungan' => Carbon::parse($this->visit_date)->format('Y-m-d'),
            'tujuan_kredit' => $this->tujuan_kredit,
            'jenis_produk' => $this->jenis_produk,
            'plafond' => $this->plafond,
            'tenor' => $this->tenor,
            'nama_nasabah' => $this->nama,
            'ktp' => $this->ktp,
            'kk' => $this->kk,
            'tgl_lahir' => $this->tgl_lahir,
            'alamat' => $this->alamat,
            'rt' => $this->rt,
            'rw' => $this->rw,
            'provinsi' => $this->province,
            'kota' => $this->city,
            'desa' => $this->kelurahan,
            'kecamatan' => $this->kecamatan,
            'kodepos' => $this->zip_code,
            'no_handphone' => $this->hp,
            'usaha' => $this->usaha,
            'sector' => $this->sector,
            'coordinate' => $this->coordinate,
            'accurate' => $this->accurate,
            'slik_request' =>  slikProgress($this->slik),
        ];

        return $data;
    }
}
