<?php

namespace App\Http\Resources;

use App\Models\M_CrSurveyDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_CrApplicationDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $arrayList = [
            'id_application' => $this->cr_application->ID ?? '',
            'survey_id' => $this->id,
            'order_number' => $this->cr_application->ORDER_NUMBER,
            "flag" => $this->cr_application->credit->ID ? 1 : 0,
            'jenis_angsuran' => $this->cr_application->INSTALLMENT_TYPE ?? $this->jenis_angsuran ?? '',
            'pelanggan' => [
                "nama" => $this->cr_application->cr_personal->NAME ?? $this->nama ?? "",
                "nama_panggilan" => $this->cr_application->cr_personal->ALIAS ?? null,
                "jenis_kelamin" => $this->cr_application->cr_personal->GENDER ?? null,
                "tempat_lahir" => $this->cr_application->cr_personal->BIRTHPLACE ?? null,
                "tgl_lahir" => date_format(date_create($this->cr_application->cr_personal->BIRTHDATE ?? $this->tgl_lahir ?? null), 'Y-m-d'),
                "gol_darah" => $this->cr_application->cr_personal->BLOOD_TYPE ?? null,
                "status_kawin" => $this->cr_application->cr_personal->MARTIAL_STATUS ?? null,
                "tgl_kawin" => $this->cr_application->cr_personal->MARTIAL_DATE ?? null,
                "tipe_identitas" => "KTP",
                "no_identitas" => $this->cr_application->cr_personal->ID_NUMBER ?? $this->ktp ?? null,
                "tgl_terbit_identitas" => $this->cr_application->cr_personal->ID_ISSUE_DATE ?? null,
                "masa_berlaku_identitas" => $this->cr_application->cr_personal->ID_VALID_DATE ?? null,
                "no_kk" => $this->cr_application->cr_personal->KK ?? $this->kk ?? "",
                "warganegara" => $this->cr_application->cr_personal->CITIZEN ?? null
            ],
            'alamat_identitas' => [
                "alamat" => $this->cr_application->cr_personal->ADDRESS ??  $this->alamat ?? null,
                "rt" => $this->cr_application->cr_personal->RT  ?? $this->rt ?? null,
                "rw" => $this->cr_application->cr_personal->RW  ?? $this->rw ?? null,
                "provinsi" => $this->cr_application->cr_personal->PROVINCE  ?? $this->province ?? null,
                "kota" => $this->cr_application->cr_personal->CITY  ?? $this->city ?? null,
                "kelurahan" => $this->cr_application->cr_personal->KELURAHAN  ?? $this->kelurahan ?? null,
                "kecamatan" => $this->cr_application->cr_personal->KECAMATAN  ?? $this->kecamatan ?? null,
                "kode_pos" => $this->cr_application->cr_personal->ZIP_CODE  ?? $this->zip_code ?? null
            ],
            'alamat_tagih' => [
                "alamat" => $this->cr_application->cr_personal->INS_ADDRESS ?? null,
                "rt" => $this->cr_application->cr_personal->INS_RT ?? null,
                "rw" => $this->cr_application->cr_personal->INS_RW ?? null,
                "provinsi" => $this->cr_application->cr_personal->INS_PROVINCE ?? null,
                "kota" => $this->cr_application->cr_personal->INS_CITY ?? null,
                "kelurahan" => $this->cr_application->cr_personal->INS_KELURAHAN ?? null,
                "kecamatan" => $this->cr_application->cr_personal->INS_KECAMATAN ?? null,
                "kode_pos" => $this->cr_application->cr_personal->INS_ZIP_CODE ?? null
            ],
            "barang_taksasi" => [
                "kode_barang" => $this->cr_application->cr_order->KODE_BARANG ?? null,
                "id_tipe" => $this->cr_application->cr_order->ID_TIPE ?? null,
                "tahun" => $this->cr_application->cr_order->TAHUN ?? null,
                "harga_pasar" => $this->cr_application->cr_order->HARGA_PASAR ?? null
            ],
            'pekerjaan' => [
                "pekerjaan" => $this->cr_application->cr_personal->OCCUPATION ?? $this->usaha ?? null,
                "pekerjaan_id" => $this->cr_application->cr_personal->OCCUPATION_ON_ID ?? $this->sector,
                "agama" => $this->cr_application->cr_personal->RELIGION ?? null,
                "pendidikan" => $this->cr_application->cr_personal->EDUCATION ?? null,
                "status_rumah" => $this->cr_application->cr_personal->PROPERTY_STATUS ?? null,
                "telepon_rumah" => $this->cr_application->cr_personal->PHONE_HOUSE ?? null,
                "telepon_selular" => $this->cr_application->cr_personal->PHONE_PERSONAL ?? $this->hp ?? null,
                "telepon_kantor" => $this->cr_application->cr_personal->PHONE_OFFICE ?? null,
                "ekstra1" => $this->cr_application->cr_personal->EXT_1 ?? null,
                "ekstra2" => $this->cr_application->cr_personal->EXT_2 ?? null
            ],
            'order' => [
                "nama_ibu" => $this->cr_application->cr_order->MOTHER_NAME ?? null,
                'cr_prospect_id' => $this->id ?? null,
                "kategori" => $this->cr_application->cr_order->CATEGORY ?? null,
                "gelar" => $this->cr_application->cr_order->TITLE ?? null,
                "lama_bekerja" => intval($this->cr_application->cr_order->WORK_PERIOD ?? $this->work_period ?? 0),
                "tanggungan" => $this->cr_application->cr_order->DEPENDANTS ?? null,
                "biaya_bulanan" => intval($this->cr_application->cr_order->BIAYA ?? $this->expenses),
                "pendapatan_pribadi" => intval($this->cr_application->cr_order->INCOME_PERSONAL ?? $this->income_personal ?? 0),
                "pendapatan_pasangan" => intval($this->cr_application->cr_order->INCOME_SPOUSE ?? $this->income_spouse ?? 0),
                "pendapatan_lainnya" => intval($this->cr_application->cr_order->INCOME_OTHER ?? $this->income_other ?? 0),
                "no_npwp" => $this->cr_application->cr_order->NO_NPWP ?? null,
                "order_tanggal" =>  date('d-m-Y', strtotime($this->visit_date)) ?? null,
                "order_status" =>  $this->cr_application->cr_order->ORDER_STATUS ?? null,
                "order_tipe" =>  $this->cr_application->cr_order->ORDER_TIPE ?? null,
                "unit_bisnis" => $this->cr_application->cr_order->UNIT_BISNIS ?? null,
                "cust_service" => $this->cr_application->cr_order->CUST_SERVICE ?? null,
                "ref_pelanggan" => $this->cr_application->cr_order->REF_PELANGGAN ?? null,
                'ref_pelanggan_oth' => $this->cr_application->cr_order->REF_PELANGGAN_OTHER ?? null,
                "surveyor_name" => User::find($this->created_by)->fullname,
                "catatan_survey" => $this->cr_application->cr_order->SURVEY_NOTE ?? $this->survey_note ?? null,
                "prog_marketing" => $this->cr_application->cr_order->PROG_MARKETING ?? null,
                "cara_bayar" => $this->cr_application->cr_order->CARA_BAYAR ?? null
            ],
            'tambahan' => [
                "nama_bi"  => $this->cr_application->cr_personal_extra->BI_NAME ?? null,
                "email"  => $this->cr_application->cr_personal_extra->EMAIL ?? null,
                "info_khusus"  => $this->cr_application->cr_personal_extra->INFO ?? null,
                "usaha_lain1"  => $this->cr_application->cr_personal_extra->OTHER_OCCUPATION_1 ?? null,
                "usaha_lain2"  => $this->cr_application->cr_personal_extra->OTHER_OCCUPATION_2 ?? null,
                "usaha_lain3"  => $this->cr_application->cr_personal_extra->OTHER_OCCUPATION_3 ?? null,
                "usaha_lain4"  => $this->cr_application->cr_personal_extra->OTHER_OCCUPATION_4 ?? null,
            ],
            'kerabat_darurat' => [
                "nama"  => $this->cr_application->cr_personal_extra->EMERGENCY_NAME ?? null,
                "alamat"  => $this->cr_application->cr_personal_extra->EMERGENCY_ADDRESS ?? null,
                "rt"  => $this->cr_application->cr_personal_extra->EMERGENCY_RT ?? null,
                "rw"  => $this->cr_application->cr_personal_extra->EMERGENCY_RW ?? null,
                "provinsi" => $this->cr_application->cr_personal_extra->EMERGENCY_PROVINCE ?? null,
                "kota" => $this->cr_application->cr_personal_extra->EMERGENCY_CITY ?? null,
                "kelurahan" => $this->cr_application->cr_personal_extra->EMERGENCY_KELURAHAN ?? null,
                "kecamatan" => $this->cr_application->cr_personal_extra->EMERGENCY_KECAMATAN ?? null,
                "kode_pos" => $this->cr_application->cr_personal_extra->EMERGENCY_ZIP_CODE ?? null,
                "no_telp" => $this->cr_application->cr_personal_extra->EMERGENCY_PHONE_HOUSE ?? null,
                "no_hp" => $this->cr_application->cr_personal_extra->EMERGENCY_PHONE_PERSONAL ?? null,
            ],
            "penjamin" => [],
            "pasangan" => [
                "nama_pasangan" => $this->cr_application->cr_spouse->NAME ?? null,
                "tmptlahir_pasangan" => $this->cr_application->cr_spouse->BIRTHPLACE ?? null,
                "pekerjaan_pasangan" => $this->cr_application->cr_spouse->OCCUPATION ?? null,
                "tgllahir_pasangan" => $this->cr_application->cr_spouse->BIRTHDATE ?? null,
                "alamat_pasangan" => $this->cr_application->cr_spouse->ADDRESS ?? null
            ],
            "info_bank" => [],
            "ekstra" => [
                'jenis_angsuran' => strtolower($this->cr_application->INSTALLMENT_TYPE ?? $this->jenis_angsuran ?? ''),
                'tenor' => $this->cr_application->TENOR,
                "nilai_yang_diterima" => intval($this->cr_application->SUBMISSION_VALUE) ??  intval($this->plafond) ?? null,
                "total" => intval($this->cr_application->TOTAL_ADMIN) ?? null,
                "cadangan" => $this->cr_application->CADANGAN ?? null,
                "opt_periode" => $this->cr_application->OPT_PERIODE ?? null,
                "provisi" => $this->cr_application->PROVISION ?? null,
                "asuransi" => $this->cr_application->INSURANCE ?? null,
                "biaya_transfer" => $this->cr_application->TRANSFER_FEE ?? null,
                "eff_rate" => $this->cr_application->EFF_RATE ?? null,
                "angsuran" => intval($this->cr_application->INSTALLMENT) ?? null
            ],
            'jaminan' => [],
            "prospect_approval" => [
                "status" => $this->cr_application->approval->application_result ?? ""
            ],
            "dokumen_indentitas" => getLastAttachment($this->id, ['ktp', 'kk', 'ktp_pasangan', 'selfie']),
            "dokumen_jaminan" => getLastAttachment($this->id, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']),
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($this->id, ['other']) ?? null,
            "dokumen_order" => getLastAttachment($this->id, ['sp', 'pk', 'dok']),
            "approval" =>
            [
                'status' => $this->cr_application->approval->application_result ?? null,
                'kapos' => $this->cr_application->approval->cr_application_kapos_desc ?? null,
                'ho' => $this->cr_application->approval->cr_application_ho_desc ?? null
            ],
            "order_validation" => []
        ];

        return $arrayList;
    }
}
