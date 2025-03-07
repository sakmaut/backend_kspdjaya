<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_CollateralTransaction extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "type" => "kendaraan",
            'nama_debitur' => $this->credit->customer->NAME ?? '',
            'order_number' => $this->credit->LOAN_NUMBER ?? '',
            'no_jaminan' => $this->BPKB_NUMBER ?? '',
            'status_kontrak' => $this->credit->STATUS == 'D' || $this->credit->STATUS == '' ? 'inactive' : 'active',
            'id' => $this->ID ?? '',
            'status_jaminan' => $this->STATUS ?? '',
            "tipe" => $this->TYPE ?? '',
            "merk" => $this->BRAND ?? '',
            "tahun" => $this->PRODUCTION_YEAR ?? '',
            "warna" => $this->COLOR ?? '',
            "atas_nama" => $this->ON_BEHALF ?? '',
            "no_polisi" => $this->POLICE_NUMBER ?? '',
            "no_rangka" => $this->CHASIS_NUMBER ?? '',
            "no_mesin" => $this->ENGINE_NUMBER ?? '',
            "no_bpkb" => $this->BPKB_NUMBER ?? '',
            "no_stnk" => $this->STNK_NUMBER ?? '',
            "tgl_stnk" => $this->STNK_VALID_DATE ?? '',
            "nilai" =>  intval($this->VALUE),
            "asal_lokasi" => $this->originBranch->NAME ?? '',
            "lokasi" => $this->currentBranch->NAME ?? '',
            "document" => $this->getCollateralDocument($this->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']) ?? null,
            "document_rilis" => $this->attachment($this->ID, "'doc_rilis'") ?? null,
        ];
    }

    public function attachment($collateralId, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_collateral_document_release AS csd
                WHERE (TYPE, COUNTER_ID) IN (
                    SELECT TYPE, MAX(COUNTER_ID)
                    FROM cr_collateral_document_release
                    WHERE TYPE IN ($data)
                        AND COLLATERAL_ID = '$collateralId'
                    GROUP BY TYPE
                )
                ORDER BY COUNTER_ID DESC"
        );

        return $documents;
    }

    function getCollateralDocument($creditID, $param)
    {

        $documents = DB::table('cr_collateral_document')
            ->whereIn('TYPE', $param)
            ->where('COLLATERAL_ID', '=', $creditID)
            ->get();

        return $documents;
    }
}
