<?php

namespace App\Http\Resources;

use App\Models\M_CrSurveyDocument;
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
        $setSurveyId = $this->id;

        $data = [
            'id' => $setSurveyId,
            // 'ao_id' => $getUserId,
            // 'visit_date' => $request->tgl_prospect ?? null,
            // 'tujuan_kredit' => $request->tujuan_kredit ?? '',
            // 'jenis_produk' => $request->jenis_produk ?? '',
            // 'plafond' => $request->plafond ?? '',
            // 'tenor' => $request->tenor ?? '',
            // 'nama' => $request->nama ?? '',
            // 'ktp' => $request->ktp ?? '',
            // 'kk' => $request->kk ?? '',
            // 'tgl_lahir' => $request->tgl_lahir ?? null,
            // 'alamat' => $request->alamat ?? '',
            // 'rt' => $request->rt ?? '',
            // 'rw' => $request->rw ?? '',
            // 'province' => $request->provinsi ?? '',
            // 'city' => $request->kota ?? '',
            // 'kelurahan' => $request->kelurahan ?? '',
            // 'kecamatan' => $request->kecamatan ?? '',
            // 'zip_code' => $request->kode_pos ?? '',
            // 'hp' => $request->hp ?? '',
            // 'usaha' => $request->usaha ?? '',
            // 'sector' => $request->sektor ?? '',
            // 'coordinate' => $request->kordinat ?? '',
            // 'accurate' => $request->accurate ?? '',
            // 'slik' => $request->slik_flag ?? '',
            // 'jenis_angsuran' => $this->jenis_angsuran ?? '',
            // 'data_order' => [
            //     'tujuan_kredit' => $this->tujuan_kredit,
            //     'plafond' => intval($this->plafond),
            //     'tenor' => strval($this->tenor),
            //     'category' => $this->category,
            //     'jenis_angsuran' => $this->jenis_angsuran
            // ],
            // 'data_nasabah' => [
            //     'nama' => $this->nama,
            //     'tgl_lahir' => is_null($this->tgl_lahir) ? null : date('Y-m-d', strtotime($this->tgl_lahir)),
            //     'no_hp' => $this->hp,
            //     'no_ktp' => $this->ktp,
            //     'no_kk' => $this->kk,
            //     'alamat' => $this->alamat,
            //     'rt' => $this->rt,
            //     'rw' => $this->rw,
            //     'provinsi' => $this->province,
            //     'kota' => $this->city,
            //     'kelurahan' => $this->kelurahan,
            //     'kecamatan' => $this->kecamatan,
            //     'kode_pos' => $this->zip_code
            // ],
            // 'data_survey' => [
            //     'usaha' => $this->usaha,
            //     'sektor' => $this->sector,
            //     'lama_bekerja' => $this->work_period,
            //     'pengeluaran' => intval($this->expenses),
            //     'pendapatan_pribadi' => intval($this->income_personal),
            //     'pendapatan_pasangan' => intval($this->income_spouse),
            //     'pendapatan_lainnya' => intval($this->income_other),
            //     'tgl_survey' => is_null($this->visit_date) ? null : date('Y-m-d', strtotime($this->visit_date)),
            //     'catatan_survey' => $this->survey_note,
            // ],
            // 'jaminan' => [],
            // 'prospect_approval' => [
            //     'flag_approval' => $this->survey_approval->ONCHARGE_APPRVL ?? '',
            //     'keterangan' => $this->survey_approval->ONCHARGE_DESCR ?? '',
            //     'status' => $this->survey_approval->APPROVAL_RESULT ?? '',
            //     'status_code' => $this->survey_approval->CODE ?? ''
            // ],
            // "dokumen_indentitas" => getLastAttachment($setSurveyId, ['ktp', 'kk', 'ktp_pasangan']),
            // "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($setSurveyId, ['other']) ?? null,
        ];

        return $data;
    }
}
