<?php

namespace App\Http\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class R_TagihanDetail extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $getUsers = User::find($this->SURVEYOR);

        $cleanDate = trim($this->LAST_PAY);
        $cleanDate = preg_replace('/[^\d\/\-\.]/', '', $cleanDate);

        return [
            "KODE CABANG" => $this->KODE ?? '',
            "NAMA CABANG" => $this->NAMA_CABANG ?? '',
            "NO KONTRAK" => is_numeric($this->NO_KONTRAK) ? (int) $this->NO_KONTRAK ?? '' : $this->NO_KONTRAK ?? '',
            "NAMA PELANGGAN" => $this->NAMA_PELANGGAN ?? '',
            "TGL BOOKING" => isset($this->TGL_BOOKING) && !empty($this->TGL_BOOKING) ?  Carbon::parse($this->TGL_BOOKING)->format('m/d/Y') : '',
            "UB" => $this->UB ?? '',
            "PLATFORM" => $this->PLATFORM ?? '',
            "ALAMAT TAGIH" => $this->ALAMAT_TAGIH ?? '',
            "KECAMATAN" => $this->KODE_POST ?? '',
            "KELURAHAN" => $this->SUB_ZIP ?? '',
            "NO TELP" => $this->NO_TELP ?? '',
            "NO HP1" => $this->NO_HP ?? '',
            "NO HP2" => $this->NO_HP2 ?? '',
            "PEKERJAAN" => $this->PEKERJAAN ?? '',
            "SUPPLIER" => $this->supplier ?? '',
            "SURVEYOR" => $getUsers ? $getUsers->fullname ?? '' : $this->SURVEYOR ?? '',
            "SURVEYOR_STATUS" => $this->SURVEYOR_STATUS ?? '',
            "CATT SURVEY" => $this->CATT_SURVEY ?? '',
            "PKK HUTANG" => (int) $this->PKK_HUTANG ?? 0,
            "JML ANGS" => $this->JUMLAH_ANGSURAN ?? '',
            "JRK ANGS" => (int) $this->JARAK_ANGSURAN ?? '',
            "PERIOD" => $this->PERIOD ?? '',
            "OUT PKK AWAL" => (int) $this->OUTSTANDING ?? 0,
            "OUT BNG AWAL" => (int) $this->OS_BUNGA ?? 0,
            "OVERDUE AWAL" => $this->OVERDUE_AWAL ?? 0,
            "AMBC PKK AWAL" => (int) $this->AMBC_PKK_AWAL,
            "AMBC BNG AWAL" => (int) $this->AMBC_BNG_AWAL,
            "AMBC TOTAL AWAL" => (int) $this->AMBC_TOTAL_AWAL,
            "CYCLE AWAL" => $this->CYCLE_AWAL ?? '',
            "STS KONTRAK" => $this->STATUS_REC ?? '',
            "STS BEBAN" => $this->STATUS_BEBAN ?? '',
            "POLA BYR AWAL" => '',
            "OUTS PKK AKHIR" => (int) $this->OS_PKK_AKHIR ?? 0,
            "OUTS BNG AKHIR" => (int) $this->OS_BNG_AKHIR ?? 0,
            "OVERDUE AKHIR" => (int) $this->OVERDUE_AKHIR ?? 0,
            "ANGSURAN" => (int) $this->INSTALLMENT ?? 0,
            "ANGS KE" => (int) $this->LAST_INST ?? '',
            "TIPE ANGSURAN" => $this->pola_bayar === 'bunga_menurun' ? str_replace('_', ' ', $this->pola_bayar) : $this->pola_bayar ?? '',
            "JTH TEMPO AWAL" => $this->F_ARR_CR_SCHEDL == '0' || $this->F_ARR_CR_SCHEDL == '' || $this->F_ARR_CR_SCHEDL == 'null' ? '' :  Carbon::parse($this->F_ARR_CR_SCHEDL)->format('m/d/Y'),
            "JTH TEMPO AKHIR" => $this->curr_arr == '0' || $this->curr_arr == '' || $this->curr_arr == 'null' ? '' : Carbon::parse($this->curr_arr)->format('m/d/Y'),
            "TGL BAYAR" => $this->LAST_PAY == '0' || $this->LAST_PAY == '' || $this->LAST_PAY == 'null' ? '' : Carbon::parse($cleanDate)->format('m/d/Y'),
            "KOLEKTOR" => $this->COLLECTOR,
            "CARA BYR" => $this->cara_bayar,
            "AMBC PKK_AKHIR" => (int) $this->AMBC_PKK_AKHIR ?? 0,
            "AMBC BNG_AKHIR" => (int) $this->AMBC_BNG_AKHIR ?? 0,
            "AMBC TOTAL_AKHIR" => (int) $this->AMBC_TOTAL_AKHIR ?? 0,
            "AC PKK" => (int) $this->AC_PKK,
            "AC BNG MRG" => (int) $this->AC_BNG_MRG,
            "AC TOTAL" => (int) $this->AC_TOTAL,
            "CYCLE AKHIR" => $this->CYCLE_AKHIR,
            "POLA BYR AKHIR" => '',
            "NAMA BRG" => $this->jenis_jaminan,
            "TIPE BRG" =>  $this->COLLATERAL ?? '',
            "NO POL" =>  $this->POLICE_NUMBER ?? '',
            "NO MESIN" =>  $this->ENGINE_NUMBER ?? '',
            "NO RANGKA" =>  $this->CHASIS_NUMBER ?? '',
            "TAHUN" => (int) $this->PRODUCTION_YEAR ?? '',
            "NILAI PINJAMAN" => (int) $this->NILAI_PINJAMAN ?? 0,
            "ADMIN" => (int) $this->TOTAL_ADMIN ?? '',
            "CUST_ID" => is_numeric($this->CUST_CODE) ? (int) $this->CUST_CODE ?? '' : $this->CUST_CODE ?? '',
            "NO SURAT" => $this->NO_SURAT ?? '',
            "PIC" => $this->username ?? '',
            "NBOT" => $this->nbot ?? ''
        ];
    }
}
