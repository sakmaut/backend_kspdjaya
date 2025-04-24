<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CrSurveyDetail extends JsonResource
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
            'jenis_angsuran' => $this->jenis_angsuran ?? '',
            'data_order' => [
                'tujuan_kredit' => $this->tujuan_kredit,
                'plafond' => (int) $this->plafond,
                'tenor' => strval($this->tenor),
                'category' => $this->category,
                'jenis_angsuran' => $this->jenis_angsuran
            ],
            'data_nasabah' => [
                'nama' => $this->nama,
                'tgl_lahir' => is_null($this->tgl_lahir) ? null : date('Y-m-d', strtotime($this->tgl_lahir)),
                'no_hp' => $this->hp,
                'no_ktp' => $this->ktp,
                'no_kk' => $this->kk,
                'alamat' => $this->alamat,
                'rt' => $this->rt,
                'rw' => $this->rw,
                'provinsi' => $this->province,
                'kota' => $this->city,
                'kelurahan' => $this->kelurahan,
                'kecamatan' => $this->kecamatan,
                'kode_pos' => $this->zip_code
            ],
            'data_survey' => [
                'usaha' => $this->usaha,
                'sektor' => $this->sector,
                'lama_bekerja' => $this->work_period,
                'pengeluaran' => (int) $this->expenses,
                'pendapatan_pribadi' => (int) $this->income_personal,
                'pendapatan_pasangan' => (int) $this->income_spouse,
                'pendapatan_lainnya' => (int) $this->income_other,
                'tgl_survey' => is_null($this->visit_date) ? null : date('Y-m-d', strtotime($this->visit_date)),
                'catatan_survey' => $this->survey_note,
            ],
            'jaminan' => [],
            'prospect_approval' => [
                'flag_approval' => $approval_detail->ONCHARGE_APPRVL,
                'keterangan' => $approval_detail->ONCHARGE_DESCR,
                'status' => $approval_detail->APPROVAL_RESULT,
                'status_code' => $approval_detail->CODE
            ],
            "dokumen_indentitas" => $this->attachment($survey_id, "'ktp', 'kk', 'ktp_pasangan'"),
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($survey_id, ['other']) ?? null,
        ];

        return $data;
    }
}
