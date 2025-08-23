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

        return [
            "no_kontrak"     => $this->LOAN_NUMBER,
            "atas_nama"      => $this->NAME ?? '',
            "nama_cabang"      => $this->nama_cabang ?? '',

            // Dokumen customer
            "ktp"            => $customer_doc->get('ktp')['PATH'] ?? '',
            "kk"             => $customer_doc->get('kk')['PATH'] ?? '',
            "ktp_pasangan"   => $customer_doc->get('ktp_pasangan')['PATH'] ?? '',

            // Dokumen collateral
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
