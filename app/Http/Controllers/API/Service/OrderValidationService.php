<?php
namespace App\Http\Controllers\API\Service;

use App\Models\M_CrBlacklist;
use Illuminate\Support\Facades\DB;

class OrderValidationService
{
    public function validate(array $request,iterable $guaranteeVehicles): array {

        $ktp         = $request['KTP'] ?? null;
        $kk          = $request['KK'] ?? null;
        $orderNumber = $request['OrderNumber'] ?? null;

        $errors = [];

        $this->validateBasic($errors, $ktp, $kk, $guaranteeVehicles);

        if (!empty($errors)) {
            return $errors;
        }

        $this->validateLimitCredit($errors, $ktp, $kk, $orderNumber);

        $this->validateLimitCollateral($errors, $guaranteeVehicles, $orderNumber);

        return $errors;
    }

    private function validateBasic(
        array &$errors,
        ?string $ktp,
        ?string $kk,
        iterable $vehicles
    ): void {

        if (empty($ktp) && empty($kk)) {
            $errors[] = "Nomor KTP atau Nomor KK wajib diisi salah satu";
        }

        $vehicles = collect($vehicles);

        if ($vehicles->isEmpty()) {
            $errors[] = "Minimal 1 jaminan harus diisi";
            return;
        }

        if ($vehicles->some(
            fn($v) =>
            empty($v->CHASIS_NUMBER) || empty($v->ENGINE_NUMBER)
        )) {
            $errors[] = "No Rangka dan No Mesin tidak boleh kosong";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ LIMIT 2 KREDIT AKTIF PER NASABAH
    |--------------------------------------------------------------------------
    */
    private function validateLimitCredit(
        array &$errors,
        ?string $ktp,
        ?string $kk,
        ?string $orderNumber
    ): void {

        $count = DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->where('a.STATUS_REC', 'AC')
            ->when(
                $orderNumber,
                fn($q) =>
                $q->where('a.ORDER_NUMBER', '!=', $orderNumber)
            )
            ->where(function ($q) use ($ktp, $kk) {
                if (!empty($ktp)) {
                    $q->orWhere('b.ID_NUMBER', $ktp);
                }
                if (!empty($kk)) {
                    $q->orWhere('b.KK_NUMBER', $kk);
                }
            })
            ->count();

        if ($count >= 2) {
            $errors[] = "Nasabah telah memiliki 2 kredit aktif (maksimal 2)";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3️⃣ LIMIT 2 KREDIT AKTIF PER JAMINAN
    |--------------------------------------------------------------------------
    */
    private function validateLimitCollateral(
        array &$errors,
        iterable $vehicles,
        ?string $orderNumber
    ): void {

        $vehicles = collect($vehicles);

        $chasisNumbers = $vehicles->pluck('CHASIS_NUMBER')->unique()->values();
        $engineNumbers = $vehicles->pluck('ENGINE_NUMBER')->unique()->values();

        if ($chasisNumbers->isEmpty() && $engineNumbers->isEmpty()) {
            return;
        }

        $collaterals = DB::table('cr_collateral as c')
            ->join('credit as a', 'a.ID', '=', 'c.CR_CREDIT_ID')
            ->where('a.STATUS_REC', 'AC')
            ->when(
                $orderNumber,
                fn($q) =>
                $q->where('a.ORDER_NUMBER', '!=', $orderNumber)
            )
            ->where(function ($q) use ($chasisNumbers, $engineNumbers) {
                if ($chasisNumbers->isNotEmpty()) {
                    $q->orWhereIn('c.CHASIS_NUMBER', $chasisNumbers);
                }
                if ($engineNumbers->isNotEmpty()) {
                    $q->orWhereIn('c.ENGINE_NUMBER', $engineNumbers);
                }
            })
            ->select('c.CHASIS_NUMBER', 'c.ENGINE_NUMBER')
            ->get()
            ->groupBy(function ($item) {
                return $item->CHASIS_NUMBER . '-' . $item->ENGINE_NUMBER;
            });

        foreach ($vehicles as $v) {

            $key = $v->CHASIS_NUMBER . '-' . $v->ENGINE_NUMBER;

            $count = $collaterals[$key]->count() ?? 0;

            if ($count >= 2) {
                $errors[] =
                    "Jaminan No Rangka {$v->CHASIS_NUMBER} / "
                    . "No Mesin {$v->ENGINE_NUMBER} "
                    . "sudah digunakan pada 2 kredit aktif (maksimal 2)";
            }
        }
    }
}

// class OrderValidationService
// {

//     public function validate(
//         array $request,
//         iterable $guaranteeVehicles
//     ): array {

//         $ktp         = $request['KTP'] ?? null;
//         $kk          = $request['KK'] ?? null;
//         $orderNumber = $request['OrderNumber'] ?? null;

//         $errors = [];

//         $this->validateBasic($errors, $ktp, $kk, $guaranteeVehicles);

//         if (!empty($errors)) {
//             return $errors;
//         }

//         $this->validateLimitCredit($errors, $ktp, $kk, $orderNumber);

//         $this->validateLimitCollateral($errors, $guaranteeVehicles, $orderNumber);

//         return $errors;
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | 1️⃣ VALIDASI DASAR
//     |--------------------------------------------------------------------------
//     */
//     private function validateBasic(
//         array &$errors,
//         ?string $ktp,
//         ?string $kk,
//         iterable $vehicles
//     ): void {

//         if (empty($ktp) && empty($kk)) {
//             $errors[] = "Nomor KTP atau Nomor KK wajib diisi salah satu";
//         }

//         $vehicles = collect($vehicles);

//         if ($vehicles->isEmpty()) {
//             $errors[] = "Minimal 1 jaminan harus diisi";
//             return;
//         }

//         if ($vehicles->some(
//             fn($v) =>
//             empty($v->CHASIS_NUMBER) || empty($v->ENGINE_NUMBER)
//         )) {
//             $errors[] = "No Rangka dan No Mesin tidak boleh kosong";
//         }
//     }


//     private function validateSimple(
//         array &$errors,
//         ?string $orderNumber,
//         ?string $ktp,
//         ?string $kk,
//         iterable $guaranteeVehicles
//     ): void {

//         /*
//     |--------------------------------------------------------------------------
//     | 1. VALIDASI KTP / KK
//     |--------------------------------------------------------------------------
//     */
//         if (empty($ktp) && empty($kk)) {
//             $errors[] = "Nomor KTP atau Nomor KK wajib diisi salah satu";
//             return;
//         }

//         /*
//     |--------------------------------------------------------------------------
//     | 2. VALIDASI JAMINAN KOSONG
//     |--------------------------------------------------------------------------
//     */
//         $vehicles = collect($guaranteeVehicles);

//         if ($vehicles->some(
//             fn($v) =>
//             empty($v->CHASIS_NUMBER) || empty($v->ENGINE_NUMBER)
//         )) {
//             $errors[] = "No Rangka dan No Mesin tidak boleh kosong";
//             return;
//         }

//         /*
//     |--------------------------------------------------------------------------
//     | 3. CEK LIMIT KREDIT AKTIF (MAKS 2)
//     |--------------------------------------------------------------------------
//     */
//         $activeCreditCount = DB::table('credit as a')
//             ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
//             ->where('a.STATUS_REC', 'AC')
//             ->where(function ($q) use ($ktp, $kk) {
//                 if (!empty($ktp)) {
//                     $q->orWhere('b.ID_NUMBER', $ktp);
//                 }
//                 if (!empty($kk)) {
//                     $q->orWhere('b.KK_NUMBER', $kk);
//                 }
//             })
//             ->when(
//                 $orderNumber,
//                 fn($q) =>
//                 $q->where('a.ORDER_NUMBER', '!=', $orderNumber)
//             )
//             ->count();

//         if ($activeCreditCount >= 2) {
//             $errors[] = "Melebihi batas maksimal 2 kredit aktif";
//         }

//         /*
//     |--------------------------------------------------------------------------
//     | 4. CEK JAMINAN DIPAKAI LEBIH DARI 2 KALI
//     |--------------------------------------------------------------------------
//     */
//         foreach ($vehicles as $v) {

//             $collateralCount = DB::table('cr_collateral as c')
//                 ->join('credit as a', 'a.ID', '=', 'c.CR_CREDIT_ID')
//                 ->where('a.STATUS_REC', 'AC')
//                 ->where(function ($q) use ($v) {
//                     $q->where('c.CHASIS_NUMBER', $v->CHASIS_NUMBER)
//                         ->orWhere('c.ENGINE_NUMBER', $v->ENGINE_NUMBER);
//                 })
//                 ->count();

//             if ($collateralCount >= 2) {
//                 $errors[] =
//                     "Jaminan No Rangka {$v->CHASIS_NUMBER} / "
//                     . "No Mesin {$v->ENGINE_NUMBER} "
//                     . "sudah digunakan lebih dari 2 kredit aktif";
//             }
//         }
//     }

//     // public function validate(array $request, iterable $guaranteeVehicles): array
//     // {
//     //     $ktp         = $request['KTP'] ?? null;
//     //     $kk          = $request['KK'] ?? null;
//     //     $orderNumber = $request['OrderNumber'] ?? null;

//     //     $errors = [];

//     //     $this->validateBlacklist($errors, $ktp, $kk);
//     //     $this->validateActiveCredit($errors, $orderNumber, $ktp, $kk, $guaranteeVehicles);
//     //     $this->validateCollateral($errors, $orderNumber, $guaranteeVehicles);

//     //     return $errors;
//     // }

//     // private function validateBlacklist(array &$errors, ?string $ktp, ?string $kk): void
//     // {
//     //     if (empty($ktp) && empty($kk)) {
//     //         $errors[] = "Nomor KTP atau Nomor KK wajib diisi salah satu";
//     //         return;
//     //     }

//     //     $blacklist = M_CrBlacklist::query()
//     //         ->where(function ($q) use ($ktp, $kk) {
//     //             $q->when(!empty($ktp), fn($q) => $q->orWhere('KTP', $ktp))
//     //                 ->when(!empty($kk),  fn($q) => $q->orWhere('KK', $kk));
//     //         })
//     //         ->first();

//     //     if ($blacklist) {
//     //         $matchedBy = match (true) {
//     //             !empty($ktp) && $blacklist->KTP === $ktp && !empty($kk) && $blacklist->KK === $kk => "KTP {$ktp} dan KK {$kk}",
//     //             !empty($ktp) && $blacklist->KTP === $ktp => "KTP {$ktp}",
//     //             !empty($kk)  && $blacklist->KK  === $kk  => "KK {$kk}",
//     //         };

//     //         $errors[] = "Atas nama {$blacklist->NAME} teridentifikasi dalam daftar BLACKLIST berdasarkan {$matchedBy}";
//     //     }
//     // }

//     // // private function validateActiveCredit(array &$errors,?string  $orderNumber,?string $ktp,?string $kk,iterable $guaranteeVehicles): void {
//     // //     $allVehicles = collect($guaranteeVehicles);

//     // //     if ($allVehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
//     // //         $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
//     // //     }

//     // //     $vehicles = $allVehicles->filter(
//     // //         fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER)
//     // //     );

//     // //     $newChasis  = $vehicles->pluck('CHASIS_NUMBER')->filter()->unique();
//     // //     $newEngines = $vehicles->pluck('ENGINE_NUMBER')->filter()->unique();

//     // //     $getActiveCredits = function (string $field, string $value) use ($orderNumber): array {
//     // //         $activeCredits = DB::select("
//     // //             SELECT a.ID, a.ORDER_NUMBER
//     // //             FROM credit a
//     // //             JOIN customer b ON b.CUST_CODE = a.CUST_CODE
//     // //             WHERE a.STATUS = 'A'
//     // //             AND b.{$field} = ?
//     // //             AND a.ORDER_NUMBER != ?
//     // //         ", [$value, $orderNumber ?? '']);

//     // //         if (empty($activeCredits)) {
//     // //             return [
//     // //                 'order_count' => 0,
//     // //                 'collaterals' => collect(),
//     // //             ];
//     // //         }

//     // //         $creditIds    = collect($activeCredits)->pluck('ID')->all();
//     // //         $placeholders = implode(',', array_fill(0, count($creditIds), '?'));

//     // //         // Sekalian JOIN ke credit untuk ambil ORDER_NUMBER per collateral
//     // //         $collaterals = DB::select("
//     // //             SELECT
//     // //                 c.CR_CREDIT_ID,
//     // //                 c.CHASIS_NUMBER,
//     // //                 c.ENGINE_NUMBER,
//     // //                 c.STATUS        AS COLLATERAL_STATUS,
//     // //                 a.ORDER_NUMBER  AS CREDIT_ORDER_NUMBER,
//     // //                 a.LOAN_NUMBER
//     // //             FROM cr_collateral c
//     // //             JOIN credit a ON a.ID = c.CR_CREDIT_ID
//     // //             WHERE c.CR_CREDIT_ID IN ({$placeholders})
//     // //         ", $creditIds);

//     // //         return [
//     // //             'order_count' => count($activeCredits),
//     // //             'collaterals' => collect($collaterals),
//     // //         ];
//     // //     };

//     // //     $checkLimit = function (string $identifier,string $labelPrefix,string $field) use (
//     // //         $getActiveCredits,
//     // //         $newChasis,
//     // //         $newEngines,
//     // //         &$errors
//     // //     ): void {
//     // //         $result           = $getActiveCredits($field, $identifier);
//     // //         $activeOrderCount = $result['order_count'];
//     // //         $collaterals      = $result['collaterals'];

//     // //         if ($activeOrderCount === 0) return;

//     // //         // Ambil hanya collateral yang overlap dengan jaminan baru
//     // //         $overlappingCollaterals = $collaterals->filter(
//     // //             fn($r) => (!empty($r->CHASIS_NUMBER) && $newChasis->contains($r->CHASIS_NUMBER))
//     // //                 || (!empty($r->ENGINE_NUMBER)  && $newEngines->contains($r->ENGINE_NUMBER))
//     // //         );

//     // //         if ($overlappingCollaterals->isEmpty()) {
//     // //             if ($activeOrderCount >= 2) {
//     // //                 // Kumpulkan semua no kontrak aktif untuk keterangan
//     // //                 $activeOrders = $collaterals
//     // //                     ->pluck('CREDIT_ORDER_NUMBER')
//     // //                     ->unique()
//     // //                     ->filter()
//     // //                     ->join(', ');

//     // //                 $errors[] = "{$labelPrefix} {$identifier} telah melebihi batas maksimal 2 kredit AKTIF "
//     // //                         . "(No Kontrak Aktif: {$activeOrders})";
//     // //             }
//     // //             return;
//     // //         }

//     // //         $hasUnreleased = $overlappingCollaterals->some(
//     // //             fn($r) => $r->COLLATERAL_STATUS !== 'RILIS'
//     // //         );

//     // //         $collateralInfo = $overlappingCollaterals->map(function ($r) {
//     // //             $parts = [];

//     // //             if (!empty($r->CHASIS_NUMBER)) {
//     // //                 $parts[] = "No Rangka: {$r->CHASIS_NUMBER}";
//     // //             }
//     // //             if (!empty($r->ENGINE_NUMBER)) {
//     // //                 $parts[] = "No Mesin: {$r->ENGINE_NUMBER}";
//     // //             }

//     // //             $vehicleInfo = implode(', ', $parts);
//     // //             $status      = $r->COLLATERAL_STATUS !== 'RILIS' ? 'Belum Rilis' : 'Rilis';

//     // //             return "No Kontrak {$r->LOAN_NUMBER} [{$vehicleInfo}, Status Jaminan: {$status}]";
//     // //         })->join(' | ');

//     // //         $reason = $hasUnreleased
//     // //             ? "jaminan sama masih aktif dan belum dirilis"
//     // //             : "jaminan sama masih terdaftar pada kredit aktif";

//     // //         $errors[] = "{$labelPrefix} {$identifier} tidak dapat diproses, {$reason}. "
//     // //                 . "Detail: {$collateralInfo}";
//     // //     };

//     // //     if (!empty($ktp)) {
//     // //         $checkLimit($ktp, 'No KTP', 'ID_NUMBER');
//     // //     }

//     // //     if (!empty($kk)) {
//     // //         $checkLimit($kk, 'No KK', 'KK_NUMBER');
//     // //     }
//     // // }

//     // private function validateCollateral(
//     //     array &$errors,
//     //     ?string $orderNumber,
//     //     iterable $guaranteeVehicles
//     // ): void {
//     //     $vehicles = collect($guaranteeVehicles);

//     //     // Cek kendaraan yang kosong
//     //     if ($vehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
//     //         $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
//     //     }

//     //     // Ambil kendaraan yang valid
//     //     $valid = $vehicles->filter(fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER));

//     //     if ($valid->isEmpty()) return;

//     //     // Satu query ambil semua collateral yang cocok
//     //     $collaterals = DB::table('cr_collateral as a')
//     //         ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
//     //         ->selectRaw('a.CHASIS_NUMBER, a.ENGINE_NUMBER, a.STATUS, b.STATUS_REC')
//     //         ->where(function ($q) use ($valid) {
//     //             $q->whereIn('a.CHASIS_NUMBER', $valid->pluck('CHASIS_NUMBER')->all())
//     //                 ->orWhereIn('a.ENGINE_NUMBER', $valid->pluck('ENGINE_NUMBER')->all());
//     //         })
//     //         ->where('b.ORDER_NUMBER', '!=', $orderNumber)
//     //         ->get();

//     //     if ($collaterals->isEmpty()) return;

//     //     $valid->each(function ($v) use ($collaterals, &$errors) {
//     //         $matchFn = fn($r) => $r->CHASIS_NUMBER === $v->CHASIS_NUMBER
//     //             || $r->ENGINE_NUMBER === $v->ENGINE_NUMBER;

//     //         // Cari yang belum dirilis
//     //         $unreleased = $collaterals->first(fn($r) => $matchFn($r) && $r->STATUS !== 'RILIS');

//     //         // Cari yang masih aktif (tidak peduli STATUS)
//     //         $active = $collaterals->first(fn($r) => $matchFn($r) && $r->STATUS_REC === 'AC');

//     //         if (!$unreleased && !$active) return;

//     //         // Ambil row untuk info keterangan
//     //         $row  = $unreleased ?? $active;
//     //         $info = match (true) {
//     //             $row->CHASIS_NUMBER === $v->CHASIS_NUMBER && $row->ENGINE_NUMBER === $v->ENGINE_NUMBER
//     //             => "No Rangka {$v->CHASIS_NUMBER} dan No Mesin {$v->ENGINE_NUMBER}",
//     //             $row->CHASIS_NUMBER === $v->CHASIS_NUMBER
//     //             => "No Rangka {$v->CHASIS_NUMBER}",
//     //             default
//     //             => "No Mesin {$v->ENGINE_NUMBER}",
//     //         };

//     //         if ($unreleased) {
//     //             $errors[] = "Jaminan {$info} masih belum dirilis";
//     //         }

//     //         if ($active) {
//     //             $errors[] = "Jaminan {$info} masih terdaftar pada kredit yang aktif";
//     //         }
//     //     });
//     // }

//     // private function validateCollateral(
//     //     array &$errors,
//     //     ?string $orderNumber,
//     //     iterable $guaranteeVehicles
//     // ): void {

//     //     $vehicles = collect($guaranteeVehicles);

//     //     if ($vehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
//     //         $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
//     //     }

//     //     $valid = $vehicles->filter(
//     //         fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER)
//     //     );

//     //     if ($valid->isEmpty()) return;

//     //     $collaterals = DB::table('cr_collateral as a')
//     //         ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
//     //         ->selectRaw('
//     //         a.CHASIS_NUMBER,
//     //         a.ENGINE_NUMBER,
//     //         a.STATUS,
//     //         b.STATUS_REC
//     //     ')
//     //         ->where(function ($q) use ($valid) {
//     //             $q->whereIn('a.CHASIS_NUMBER', $valid->pluck('CHASIS_NUMBER')->all())
//     //                 ->orWhereIn('a.ENGINE_NUMBER', $valid->pluck('ENGINE_NUMBER')->all());
//     //         })
//     //         ->where('b.ORDER_NUMBER', '!=', $orderNumber)
//     //         ->get();

//     //     if ($collaterals->isEmpty()) return;

//     //     foreach ($valid as $v) {

//     //         $match = $collaterals->first(function ($r) use ($v) {
//     //             return $r->CHASIS_NUMBER === $v->CHASIS_NUMBER
//     //                 || $r->ENGINE_NUMBER === $v->ENGINE_NUMBER;
//     //         });

//     //         if (!$match) continue;

//     //         $info = match (true) {
//     //             $match->CHASIS_NUMBER === $v->CHASIS_NUMBER
//     //                 && $match->ENGINE_NUMBER === $v->ENGINE_NUMBER
//     //             => "No Rangka {$v->CHASIS_NUMBER} dan No Mesin {$v->ENGINE_NUMBER}",

//     //             $match->CHASIS_NUMBER === $v->CHASIS_NUMBER
//     //             => "No Rangka {$v->CHASIS_NUMBER}",

//     //             default
//     //             => "No Mesin {$v->ENGINE_NUMBER}",
//     //         };

//     //         // 🔴 Rule utama
//     //         if ($match->STATUS !== 'RILIS') {
//     //             $errors[] = "Jaminan {$info} belum dirilis";
//     //             continue;
//     //         }

//     //         if ($match->STATUS_REC === 'AC') {
//     //             $errors[] = "Jaminan {$info} masih terdaftar pada kredit aktif";
//     //         }
//     //     }
//     // }
// }
