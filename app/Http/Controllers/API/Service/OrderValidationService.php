<?php
namespace App\Http\Controllers\API\Service;

use App\Models\M_CrBlacklist;
use Illuminate\Support\Facades\DB;

class OrderValidationService
{
    public function validate(array $request, iterable $guaranteeVehicles): array
    {
        $ktp         = $request['KTP'] ?? null;
        $kk          = $request['KK'] ?? null;
        $orderNumber = $request['OrderNumber'] ?? null;

        $errors = [];

        $this->validateBlacklist($errors, $ktp, $kk);
        $this->validateActiveCredit($errors, $orderNumber, $ktp, $kk);
        $this->validateCollateral($errors, $orderNumber, $guaranteeVehicles);

        return $errors;
    }

    private function validateBlacklist(array &$errors, ?string $ktp, ?string $kk): void
    {
        if (empty($ktp) && empty($kk)) {
            $errors[] = "Nomor KTP atau Nomor KK wajib diisi salah satu";
            return;
        }

        $blacklist = M_CrBlacklist::query()
            ->where(function ($q) use ($ktp, $kk) {
                $q->when(!empty($ktp), fn($q) => $q->orWhere('KTP', $ktp))
                    ->when(!empty($kk),  fn($q) => $q->orWhere('KK', $kk));
            })
            ->first();

        if ($blacklist) {
            $matchedBy = match (true) {
                !empty($ktp) && $blacklist->KTP === $ktp && !empty($kk) && $blacklist->KK === $kk => "KTP {$ktp} dan KK {$kk}",
                !empty($ktp) && $blacklist->KTP === $ktp => "KTP {$ktp}",
                !empty($kk)  && $blacklist->KK  === $kk  => "KK {$kk}",
            };

            $errors[] = "Atas nama {$blacklist->NAME} teridentifikasi dalam daftar blacklist berdasarkan {$matchedBy}";
        }
    }

    private function validateActiveCredit(
        array &$errors,
        ?string $orderNumber,
        ?string $ktp,
        ?string $kk
    ): void {

        $activeCount = fn(string $field, string $value) => DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->selectRaw('1')
            ->where('a.STATUS', 'A')
            ->where("b.$field", $value)
            ->where('a.ORDER_NUMBER', '!=', $orderNumber)
            ->count();

        if (!empty($ktp) && $activeCount('ID_NUMBER', $ktp) > 0) {
            $errors[] = "No KTP {$ktp} sudah terdaftar pada kredit yang masih aktif";
        }

        if (!empty($kk) && $activeCount('KK_NUMBER', $kk) > 2) {
            $errors[] = "No KK {$kk} telah melebihi batas maksimal 2 kredit aktif";
        }
    }

    private function validateCollateral(
        array &$errors,
        ?string $orderNumber,
        iterable $guaranteeVehicles
    ): void {
        $vehicles = collect($guaranteeVehicles);

        // Cek kendaraan yang kosong
        if ($vehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
            $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
        }

        // Ambil kendaraan yang valid
        $valid = $vehicles->filter(fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER));

        // Cek satu query ke DB, ambil yang belum dirilis
        $unreleased = DB::table('cr_collateral as a')
            ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
            ->selectRaw('a.CHASIS_NUMBER, a.ENGINE_NUMBER')
            ->where('a.STATUS', '!=', 'RILIS')
            ->whereIn('a.CHASIS_NUMBER', $valid->pluck('CHASIS_NUMBER')->all())
            ->whereIn('a.ENGINE_NUMBER', $valid->pluck('ENGINE_NUMBER')->all())
            ->where('b.ORDER_NUMBER', '!=', $orderNumber)
            ->get()
            ->keyBy(fn($row) => "{$row->CHASIS_NUMBER}_{$row->ENGINE_NUMBER}");

        // Bandingkan hasil DB dengan kendaraan valid, tambahkan error jika ditemukan
        $valid
            ->filter(fn($v) => $unreleased->has("{$v->CHASIS_NUMBER}_{$v->ENGINE_NUMBER}"))
            ->each(
                fn($v) => $errors[] =
                    "Jaminan No Mesin {$v->ENGINE_NUMBER} dan No Rangka {$v->CHASIS_NUMBER} masih belum dirilis"
            );
    }
}
