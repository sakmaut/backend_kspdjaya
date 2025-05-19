<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class R_RoDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data =
            [
                'no_ktp' => $this->ID_NUMBER ?? null,
                'no_kk' => $this->KK_NUMBER ?? null,
                'nama' => $this->NAME ?? null,
                'tgl_lahir' => $this->BIRTHDATE ?? null,
                'alamat' => $this->ADDRESS ?? null,
                'rw' => $this->RW ?? null,
                'rt' => $this->RT ?? null,
                'provinsi' => $this->PROVINCE ?? null,
                'city' => $this->CITY ?? null,
                'kecamatan' => $this->KECAMATAN ?? null,
                'kelurahan' => $this->KELURAHAN ?? null,
                'kode_pos' => $this->ZIP_CODE ?? null,
                'no_hp' => $this->PHONE_PERSONAL ?? null,
                'dokumen_indentitas' => $this->customer_document,
                'jaminan' => [],
            ];

        $guaranteeVehicle = DB::table('credit as a')
            ->leftJoin('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
            ->where('a.CUST_CODE', '=', $this->CUST_CODE)
            ->where('a.CREATED_AT', '=', function ($query) {
                $query->select(DB::raw('MAX(CREATED_AT)'))->from('credit');
            })
            ->select('b.*')
            ->get();

        $guaranteeCertificate = DB::table('credit as a')
            ->leftJoin('cr_collateral_sertification as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
            ->where('a.CUST_CODE', '=', $this->CUST_CODE)
            ->where('a.CREATED_AT', '=', function ($query) {
                $query->select(DB::raw('MAX(CREATED_AT)'))->from('credit');
            })
            ->select('b.*')
            ->get();

        foreach ($guaranteeVehicle as $item) {
            if (!empty($item->ID)) {
                $data['jaminan'] = [
                    "type" => "kendaraan",
                    "counter_id" => $item->HEADER_ID,
                    "atr" => [
                        "id" => $item->ID,
                        "status_jaminan" => null,
                        "tipe" => $item->TYPE,
                        "merk" => $item->BRAND,
                        "tahun" => $item->PRODUCTION_YEAR,
                        "warna" => $item->COLOR,
                        "atas_nama" => $item->ON_BEHALF,
                        "no_polisi" => $item->POLICE_NUMBER,
                        "no_rangka" => $item->CHASIS_NUMBER,
                        "no_mesin" => $item->ENGINE_NUMBER,
                        "no_bpkb" => $item->BPKB_NUMBER,
                        "alamat_bpkb" => $item->BPKB_ADDRESS,
                        "no_faktur" => $item->INVOICE_NUMBER,
                        "no_stnk" => $item->STNK_NUMBER,
                        "tgl_stnk" => $item->STNK_VALID_DATE,
                        "nilai" => (int)($item->VALUE ?? 0),
                        "document" => $this->getCollateralDocument($item->ID, [
                            'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'
                        ]),
                    ],
                ];
            }
        }

        foreach ($guaranteeCertificate as $item) {
            if (!empty($item->ID)) {
                $data['jaminan'] = [
                    "type" => "sertifikat",
                    "counter_id" => $item->HEADER_ID,
                    "atr" => [
                        "id" => $item->ID,
                        "status_jaminan" => null,
                        "no_sertifikat" => $item->NO_SERTIFIKAT,
                        "status_kepemilikan" => $item->STATUS_KEPEMILIKAN,
                        "imb" => $item->IMB,
                        "luas_tanah" => $item->LUAS_TANAH,
                        "luas_bangunan" => $item->LUAS_BANGUNAN,
                        "lokasi" => $item->LOKASI,
                        "provinsi" => $item->PROVINSI,
                        "kab_kota" => $item->KAB_KOTA,
                        "kec" => $item->KECAMATAN,
                        "desa" => $item->DESA,
                        "atas_nama" => $item->ATAS_NAMA,
                        "nilai" => (int)($item->NILAI ?? 0),
                        "document" => $this->getCollateralDocument($item->ID, ['sertifikat']),
                    ],
                ];
            }
        }

        return $data;
    }
}
