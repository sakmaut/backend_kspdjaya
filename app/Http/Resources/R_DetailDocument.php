<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_DetailDocument extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer_doc = collect($this->customer->customer_document ?? [])->keyBy('TYPE');
        $collateral_doc = collect($this->collateral->documents ?? [])->keyBy('TYPE');

        $ktp = $customer_doc->get('ktp')['PATH'] ?? null;
        $kk  = $customer_doc->get('kk')['PATH'] ?? null;
        $ktp_pasangan  = $customer_doc->get('ktp_pasangan')['PATH'] ?? null;

        $survey_docs = collect(
            $this->cr_application->cr_survey->cr_survey_document ?? []
        )->keyBy('TYPE');

        if (!$ktp) {
            $ktp = $survey_docs->get('ktp')['PATH'] ?? '';
        }

        if (!$kk) {
            $kk = $survey_docs->get('kk')['PATH'] ?? '';
        }

        if (!$ktp_pasangan) {
            $ktp_pasangan = $survey_docs->get('ktp_pasangan')['PATH'] ?? '';
        }

        return [
            "no_kontrak"     => $this->LOAN_NUMBER,
            "atas_nama"      => $this->customer->NAME ?? '',
            "nama_cabang"      => $this->branch->NAME ?? '',
            "ktp"               => $ktp,
            "kk"                => $kk,
            "ktp_pasangan"   => $ktp_pasangan,
            "no_rangka"      => $collateral_doc->get('no_rangka')['PATH'] ?? '',
            "no_mesin"       => $collateral_doc->get('no_mesin')['PATH'] ?? '',
            "stnk"           => $collateral_doc->get('stnk')['PATH'] ?? '',
            "depan"          => $collateral_doc->get('depan')['PATH'] ?? '',
            "belakang"       => $collateral_doc->get('belakang')['PATH'] ?? '',
            "kanan"          => $collateral_doc->get('kanan')['PATH'] ?? '',
            "kiri"           => $collateral_doc->get('kiri')['PATH'] ?? '',
        ];
    }
}
