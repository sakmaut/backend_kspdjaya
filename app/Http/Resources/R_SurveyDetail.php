<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_SurveyDetail extends JsonResource
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
                'plafond' => intval($this->plafond),
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
                'pengeluaran' => intval($this->expenses),
                'pendapatan_pribadi' => intval($this->income_personal),
                'pendapatan_pasangan' => intval($this->income_spouse),
                'pendapatan_lainnya' => intval($this->income_other),
                'tgl_survey' => is_null($this->visit_date) ? null : date('Y-m-d', strtotime($this->visit_date)),
                'catatan_survey' => $this->survey_note,
            ],
            'jaminan' => [],
            'prospect_approval' => [
                'flag_approval' => $this->survey_approval->ONCHARGE_APPRVL,
                'keterangan' => $this->survey_approval->ONCHARGE_DESCR,
                'status' => $this->survey_approval->APPROVAL_RESULT,
                'status_code' => $this->survey_approval->CODE
            ],
            "dokumen_indentitas" => [],
            // "dokumen_indentitas" => $this->attachment($survey_id, "'ktp', 'kk', 'ktp_pasangan'"),
            // "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($survey_id, ['other']) ?? null,
        ];

        foreach ($this->cr_guarante_vehicle as $list) {
            $data['jaminan'][] = [
                "type" => "kendaraan",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    // "document" => $this->attachment_guarante($survey_id, $list->HEADER_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'")
                ]
            ];
        }

        foreach ($this->cr_survey_document as $list) {
            if (in_array($list->TYPE, ['ktp', 'kk', 'ktp_pasangan'])) {
                // Ensure $data['dokumen_indentitas'] is an object (not an array)
                if ($data['dokumen_indentitas'] === null || $list->TIMEMILISECOND > $data['dokumen_indentitas']->TIMEMILISECOND) {
                    $data['dokumen_indentitas'] = $list;  // Keep the document with the highest TIMEMILISECOND
                }
            }
        }

        return $data;
    }
}
