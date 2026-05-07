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
    // public function toArray(Request $request): array
    // {

    //     $cleanDate = trim($this->LAST_PAY);
    //     $cleanDate = preg_replace('/[^\d\/\-\.]/', '', $cleanDate);

    //     $userCheck = User::where('id', $this->SURVEYOR_ID)->first() ?? "";

    //     $nbot = ( in_array($this->STATUS_MCF, ['AKTIF', 'ACTIVE', 'active'])
    //         && in_array($this->LAST_INST, [1, 2, 3])
    //         && in_array($this->CYCLE_AWAL, ['CM', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0'])
    //         // && in_array($this->POSITION, ['MCF'])
    //     ) || (
    //         in_array($this->STATUS_MCF, ['RESIGN', 'MUTASI JABATAN', 'MUTASI POS'])
    //         && in_array($this->LAST_INST, [1, 2, 3])
    //         && in_array($this->CYCLE_AWAL, ['CM', 'C0', 'C1', 'C2'])
    //     )
    //         ? 'Y'
    //         : 'N';

    //     return [
    //         "KODE CABANG" => $this->KODE ?? '',
    //         "CREDIT_ID" => $this->CREDIT_ID ?? '',
    //         "CUST_CODE" => $this->CUST_CODE ?? '',
    //         "NAMA CABANG" => $this->NAMA_CABANG ?? '',
    //         "NO KONTRAK" => is_numeric($this->NO_KONTRAK) ? (int) $this->NO_KONTRAK ?? '' : $this->NO_KONTRAK ?? '',
    //         "NAMA PELANGGAN" => $this->NAME ?? '',
    //         "TGL BOOKING" => isset($this->TGL_BOOKING) && !empty($this->TGL_BOOKING) ?  Carbon::parse($this->TGL_BOOKING)->format('m/d/Y') : '',
    //         "ALAMAT TAGIH" => $this->INS_ADDRESS ?? '',
    //         "KECAMATAN" => $this->INS_KECAMATAN ?? '',
    //         "KELURAHAN" => $this->INS_KELURAHAN ?? '',
    //         "NO TELP" => $this->PHONE_HOUSE ?? '',
    //         "NO HP1" => $this->PHONE_PERSONAL ?? '',
    //         "NO HP2" => $this->PHONE_PERSONAL ?? '',
    //         "PEKERJAAN" => $this->OCCUPATION ?? '',
    //         "SUPPLIER" => $this->supplier ?? '',
    //         "SURVEYOR" => $userCheck->fullname ?? $this->SURVEYOR_ID ?? '',
    //         "SURVEYOR STATUS" => $this->STATUS_MCF ?? '',
    //         "CATT SURVEY" => $this->CATT_SURVEY ?? '',
    //         "PKK HUTANG" => (int) $this->PKK_HUTANG ?? 0,
    //         "JML ANGS" => $this->JUMLAH_ANGSURAN ?? '',
    //         "JRK ANGS" => (int) $this->JARAK_ANGSURAN ?? '',
    //         "PERIOD" => $this->PERIOD ?? '',
    //         "OUT PKK AWAL" => (int) $this->OUTSTANDING ?? 0,
    //         "OUT BNG AWAL" => (int) $this->OS_BUNGA ?? 0,
    //         "OVERDUE AWAL" => $this->OVERDUE_AWAL ?? 0,
    //         "AMBC PKK AWAL" => (int) $this->AMBC_PKK_AWAL,
    //         "AMBC BNG AWAL" => (int) $this->AMBC_BNG_AWAL,
    //         "AMBC TOTAL AWAL" => (int) $this->AMBC_TOTAL_AWAL,
    //         "CYCLE AWAL" => $this->CYCLE_AWAL ?? '',
    //         "STS KONTRAK" => $this->STATUS_REC ?? '',
    //         "STS BEBAN" => $this->STATUS_BEBAN ?? '',
    //         "POLA BYR AWAL" => '',
    //         "OUTS PKK AKHIR" => (int) $this->OS_PKK_AKHIR ?? 0,
    //         "OUTS BNG AKHIR" => (int) $this->OS_BNG_AKHIR ?? 0,
    //         "OVERDUE AKHIR" => (int) $this->OVERDUE_AKHIR ?? 0,
    //         "ANGSURAN" => (int) $this->INSTALLMENT ?? 0,
    //         "ANGS KE" => (int) $this->LAST_INST ?? '',
    //         "TIPE ANGSURAN" => $this->POLA_BAYAR === 'bunga_menurun' ? str_replace('_', ' ', $this->POLA_BAYAR) : $this->POLA_BAYAR ?? '',
    //         "JTH TEMPO AWAL" => $this->F_ARR_CR_SCHEDL == '0' || $this->F_ARR_CR_SCHEDL == '' || $this->F_ARR_CR_SCHEDL == 'null' ? '' :  Carbon::parse($this->F_ARR_CR_SCHEDL)->format('m/d/Y'),
    //         "JTH TEMPO AKHIR" => $this->CURR_ARR == '0' || $this->CURR_ARR == '' || $this->CURR_ARR == 'null' ? '' : Carbon::parse($this->CURR_ARR)->format('m/d/Y'),
    //         "TGL BAYAR" => $this->LAST_PAY == '0' || $this->LAST_PAY == '' || $this->LAST_PAY == 'null' ? '' : Carbon::parse($cleanDate)->format('m/d/Y'),
    //         "KOLEKTOR" => $this->COLLECTOR ?? "",
    //         "CARA BYR" => $this->CARA_BAYAR ?? "",
    //         "CYCLE AKHIR" => $this->CYCLE_AKHIR ?? '',
    //         "POLA BYR AKHIR" => '',
    //         "NAMA BRG" => $this->JENIS_JAMINAN ?? "",
    //         "TIPE BRG" =>  $this->COLLATERAL ?? '',
    //         "NO SURAT" => $this->NO_SURAT ?? '',
    //         "PIC" => $this->username ?? '',
    //         "NBOT" => $nbot ?? ''
    //     ];
    // }

    public function toArray(Request $request): array
    {
        $cleanDate = trim($this->LAST_PAY);
        $cleanDate = preg_replace('/[^\d\/\-\.]/', '', $cleanDate);

        $nbot = (
            in_array($this->surveyor->keterangan ?? '', ['AKTIF', 'ACTIVE', 'active'])
            && in_array($this->LAST_INST, [1, 2, 3])
            && in_array($this->CYCLE_AWAL, ['CM', 'C5', 'C4', 'C3', 'C2', 'C1', 'C0'])
        ) || (
            in_array($this->surveyor->keterangan ?? '', ['RESIGN', 'MUTASI JABATAN', 'MUTASI POS'])
            && in_array($this->LAST_INST, [1, 2, 3])
            && in_array($this->CYCLE_AWAL, ['CM', 'C0', 'C1', 'C2'])
        )
            ? 'Y'
            : 'N';

        return [
            "KODE CABANG"      => $this->KODE ?? '',
            "CREDIT_ID"        => $this->CREDIT_ID ?? '',
            "CUST_CODE"        => $this->CUST_CODE ?? '',
            "NAMA CABANG"      => $this->NAMA_CABANG ?? '',
            "NO KONTRAK"       => is_numeric($this->NO_KONTRAK)
                ? (int) $this->NO_KONTRAK
                : $this->NO_KONTRAK,

            "NAMA PELANGGAN"   => $this->customer->NAME ?? '',
            "ALAMAT TAGIH"     => $this->customer->INS_ADDRESS ?? '',
            "KECAMATAN"        => $this->customer->INS_KECAMATAN ?? '',
            "KELURAHAN"        => $this->customer->INS_KELURAHAN ?? '',
            "NO TELP"          => $this->customer->PHONE_HOUSE ?? '',
            "NO HP1"           => $this->customer->PHONE_PERSONAL ?? '',
            "NO HP2"           => $this->customer->PHONE_PERSONAL ?? '',
            "PEKERJAAN"        => $this->customer->OCCUPATION ?? '',

            "SURVEYOR"         => $this->surveyor->fullname ?? $this->SURVEYOR_ID ?? '',
            "SURVEYOR STATUS"  => $this->surveyor->keterangan ?? 'RESIGN',
            "PIC"              => $this->surveyor->username ?? '',

            "CATT SURVEY"      => $this->CATT_SURVEY ?? '',
            "PKK HUTANG"       => (int) ($this->PKK_HUTANG ?? 0),
            "JML ANGS"         => $this->JUMLAH_ANGSURAN ?? '',
            "JRK ANGS"         => (int) ($this->JARAK_ANGSURAN ?? 0),
            "PERIOD"           => $this->PERIOD ?? '',

            "OUT PKK AWAL"     => (int) ($this->OUTSTANDING ?? 0),
            "OUT BNG AWAL"     => (int) ($this->OS_BUNGA ?? 0),
            "OVERDUE AWAL"     => $this->OVERDUE_AWAL ?? 0,

            "AMBC PKK AWAL"    => (int) ($this->AMBC_PKK_AWAL ?? 0),
            "AMBC BNG AWAL"    => (int) ($this->AMBC_BNG_AWAL ?? 0),
            "AMBC TOTAL AWAL"  => (int) ($this->AMBC_TOTAL_AWAL ?? 0),

            "CYCLE AWAL"       => $this->CYCLE_AWAL ?? '',
            "STS BEBAN"        => $this->STATUS_BEBAN ?? '',

            "OUTS PKK AKHIR"   => (int) ($this->OS_PKK_AKHIR ?? 0),
            "OUTS BNG AKHIR"   => (int) ($this->OS_BNG_AKHIR ?? 0),
            "OVERDUE AKHIR"    => (int) ($this->OVERDUE_AKHIR ?? 0),

            "ANGSURAN"         => (int) ($this->INSTALLMENT ?? 0),
            "ANGS KE"          => (int) ($this->LAST_INST ?? 0),

            "TIPE ANGSURAN"    => $this->POLA_BAYAR === 'bunga_menurun'
                ? str_replace('_', ' ', $this->POLA_BAYAR)
                : $this->POLA_BAYAR,

            "JTH TEMPO AWAL"   => empty($this->F_ARR_CR_SCHEDL) || $this->F_ARR_CR_SCHEDL == '0'
                ? ''
                : Carbon::parse($this->F_ARR_CR_SCHEDL)->format('m/d/Y'),

            "JTH TEMPO AKHIR"  => empty($this->CURR_ARR) || $this->CURR_ARR == '0'
                ? ''
                : Carbon::parse($this->CURR_ARR)->format('m/d/Y'),

            "TGL BAYAR"        => empty($this->LAST_PAY) || $this->LAST_PAY == '0'
                ? ''
                : Carbon::parse($cleanDate)->format('m/d/Y'),

            "KOLEKTOR"         => $this->COLLECTOR ?? '',
            "CARA BYR"         => $this->CARA_BAYAR ?? '',
            "CYCLE AKHIR"      => $this->CYCLE_AKHIR ?? '',
            "NAMA BRG"         => $this->JENIS_JAMINAN ?? '',
            "NO SURAT"         => $this->NO_SURAT ?? '',
            "NBOT"             => $nbot
        ];
    }
}
