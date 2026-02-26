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
        $this->validateActiveCredit($errors, $orderNumber, $ktp, $kk, $guaranteeVehicles);
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

            $errors[] = "Atas nama {$blacklist->NAME} teridentifikasi dalam daftar BLACKLIST berdasarkan {$matchedBy}";
        }
    }

    private function validateActiveCredit(
        array    &$errors,
        ?string  $orderNumber,
        ?string  $ktp,
        ?string  $kk,
        iterable $guaranteeVehicles
    ): void {
        $vehicles = collect($guaranteeVehicles)->filter(
            fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER)
        );

        $newChasis  = $vehicles->pluck('CHASIS_NUMBER')->filter()->unique();
        $newEngines = $vehicles->pluck('ENGINE_NUMBER')->filter()->unique();

        $getActiveCreditsWithCollateral = function (string $field, string $value) use ($orderNumber) {
            return DB::table('credit as a')
                ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
                ->leftJoin('cr_collateral as c', 'c.CR_CREDIT_ID', '=', 'a.ID')
                ->select(
                    'a.ORDER_NUMBER',
                    'c.CHASIS_NUMBER',
                    'c.ENGINE_NUMBER',
                    'c.STATUS as COLLATERAL_STATUS'
                )
                ->where('a.STATUS', 'A')
                ->where("b.$field", $value)
                ->where('a.ORDER_NUMBER', '!=', $orderNumber)
                ->get();
        };

        $checkLimit = function (
            string $identifier,
            string $labelPrefix,
            string $field
        ) use (
            $getActiveCreditsWithCollateral,
            $newChasis,
            $newEngines,
            &$errors
        ): void {
            $activeRows       = $getActiveCreditsWithCollateral($field, $identifier);
            $activeOrderCount = $activeRows->pluck('ORDER_NUMBER')->unique()->count();

            if ($activeOrderCount === 0) return;

            // Cek apakah ada jaminan yang belum RILIS
            $hasUnreleased = $activeRows->some(
                fn($r) => !empty($r->CHASIS_NUMBER) || !empty($r->ENGINE_NUMBER)
                    && $r->COLLATERAL_STATUS !== 'RILIS'
            );

            // Cek apakah ada overlap jaminan dengan order baru
            $existingChasis  = $activeRows->pluck('CHASIS_NUMBER')->filter()->unique();
            $existingEngines = $activeRows->pluck('ENGINE_NUMBER')->filter()->unique();

            $hasOverlap = $newChasis->intersect($existingChasis)->isNotEmpty()
                || $newEngines->intersect($existingEngines)->isNotEmpty();

            // Jika ada jaminan sama ATAU belum rilis → max 1
            if ($hasUnreleased || $hasOverlap) {
                if ($activeOrderCount >= 1) {
                    $reason = match (true) {
                        $hasOverlap && $hasUnreleased => "jaminan sama dan belum dirilis",
                        $hasOverlap                  => "jaminan sama",
                        default                      => "jaminan belum dirilis",
                    };
                    $errors[] = "{$labelPrefix} {$identifier} sudah terdaftar pada kredit AKTIF dengan {$reason}";
                }
                return;
            }

            // Jika semua jaminan berbeda dan sudah RILIS → max 2
            if ($activeOrderCount >= 2) {
                $errors[] = "{$labelPrefix} {$identifier} telah melebihi batas maksimal 2 kredit AKTIF";
            }
        };

        if (!empty($ktp)) {
            $checkLimit($ktp, 'No KTP', 'ID_NUMBER');
        }

        if (!empty($kk)) {
            $checkLimit($kk, 'No KK', 'KK_NUMBER');
        }
        }

    // private function validateActiveCredit(
    //     array &$errors,
    //     ?string $orderNumber,
    //     ?string $ktp,
    //     ?string $kk
    // ): void {

    //     $activeCount = fn(string $field, string $value) => DB::table('credit as a')
    //         ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
    //         ->selectRaw('1')
    //         ->where('a.STATUS', 'A')
    //         ->where("b.$field", $value)
    //         ->where('a.ORDER_NUMBER', '!=', $orderNumber)
    //         ->count();

    //     if (!empty($ktp) && $activeCount('ID_NUMBER', $ktp) >= 2) {
    //         $errors[] = "No KTP {$ktp} sudah terdaftar pada kredit yang masih AKTIF";
    //     }

    //     if (!empty($kk) && $activeCount('KK_NUMBER', $kk) >= 2) {
    //         $errors[] = "No KK {$kk} telah melebihi batas maksimal 2 kredit AKTIF";
    //     }
    // }

    // private function validateCollateral(
    //     array &$errors,
    //     ?string $orderNumber,
    //     iterable $guaranteeVehicles
    // ): void {
    //     $vehicles = collect($guaranteeVehicles);

    //     // Cek kendaraan yang kosong
    //     if ($vehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
    //         $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
    //     }

    //     // Ambil kendaraan yang valid
    //     $valid = $vehicles->filter(fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER));

    //     if ($valid->isEmpty()) return;

    //     // Satu query ambil semua collateral yang cocok
    //     $collaterals = DB::table('cr_collateral as a')
    //         ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
    //         ->selectRaw('a.CHASIS_NUMBER, a.ENGINE_NUMBER, a.STATUS, b.STATUS_REC')
    //         ->where(function ($q) use ($valid) {
    //             $q->whereIn('a.CHASIS_NUMBER', $valid->pluck('CHASIS_NUMBER')->all())
    //                 ->orWhereIn('a.ENGINE_NUMBER', $valid->pluck('ENGINE_NUMBER')->all());
    //         })
    //         ->where('b.ORDER_NUMBER', '!=', $orderNumber)
    //         ->get();

    //     if ($collaterals->isEmpty()) return;

    //     $valid->each(function ($v) use ($collaterals, &$errors) {
    //         $matchFn = fn($r) => $r->CHASIS_NUMBER === $v->CHASIS_NUMBER
    //             || $r->ENGINE_NUMBER === $v->ENGINE_NUMBER;

    //         // Cari yang belum dirilis
    //         $unreleased = $collaterals->first(fn($r) => $matchFn($r) && $r->STATUS !== 'RILIS');

    //         // Cari yang masih aktif (tidak peduli STATUS)
    //         $active = $collaterals->first(fn($r) => $matchFn($r) && $r->STATUS_REC === 'AC');

    //         if (!$unreleased && !$active) return;

    //         // Ambil row untuk info keterangan
    //         $row  = $unreleased ?? $active;
    //         $info = match (true) {
    //             $row->CHASIS_NUMBER === $v->CHASIS_NUMBER && $row->ENGINE_NUMBER === $v->ENGINE_NUMBER
    //             => "No Rangka {$v->CHASIS_NUMBER} dan No Mesin {$v->ENGINE_NUMBER}",
    //             $row->CHASIS_NUMBER === $v->CHASIS_NUMBER
    //             => "No Rangka {$v->CHASIS_NUMBER}",
    //             default
    //             => "No Mesin {$v->ENGINE_NUMBER}",
    //         };

    //         if ($unreleased) {
    //             $errors[] = "Jaminan {$info} masih belum dirilis";
    //         }

    //         if ($active) {
    //             $errors[] = "Jaminan {$info} masih terdaftar pada kredit yang aktif";
    //         }
    //     });
    // }

    private function validateCollateral(
        array &$errors,
        ?string $orderNumber,
        iterable $guaranteeVehicles
    ): void {

        $vehicles = collect($guaranteeVehicles);

        if ($vehicles->some(fn($v) => empty($v->CHASIS_NUMBER) && empty($v->ENGINE_NUMBER))) {
            $errors[] = "Jaminan: No Mesin dan No Rangka tidak boleh kosong";
        }

        $valid = $vehicles->filter(
            fn($v) => !empty($v->CHASIS_NUMBER) || !empty($v->ENGINE_NUMBER)
        );

        if ($valid->isEmpty()) return;

        $collaterals = DB::table('cr_collateral as a')
            ->leftJoin('credit as b', 'b.ID', '=', 'a.CR_CREDIT_ID')
            ->selectRaw('
            a.CHASIS_NUMBER,
            a.ENGINE_NUMBER,
            a.STATUS,
            b.STATUS_REC
        ')
            ->where(function ($q) use ($valid) {
                $q->whereIn('a.CHASIS_NUMBER', $valid->pluck('CHASIS_NUMBER')->all())
                    ->orWhereIn('a.ENGINE_NUMBER', $valid->pluck('ENGINE_NUMBER')->all());
            })
            ->where('b.ORDER_NUMBER', '!=', $orderNumber)
            ->get();

        if ($collaterals->isEmpty()) return;

        foreach ($valid as $v) {

            $match = $collaterals->first(function ($r) use ($v) {
                return $r->CHASIS_NUMBER === $v->CHASIS_NUMBER
                    || $r->ENGINE_NUMBER === $v->ENGINE_NUMBER;
            });

            if (!$match) continue;

            $info = match (true) {
                $match->CHASIS_NUMBER === $v->CHASIS_NUMBER
                    && $match->ENGINE_NUMBER === $v->ENGINE_NUMBER
                => "No Rangka {$v->CHASIS_NUMBER} dan No Mesin {$v->ENGINE_NUMBER}",

                $match->CHASIS_NUMBER === $v->CHASIS_NUMBER
                => "No Rangka {$v->CHASIS_NUMBER}",

                default
                => "No Mesin {$v->ENGINE_NUMBER}",
            };

            // 🔴 Rule utama
            if ($match->STATUS !== 'RILIS') {
                $errors[] = "Jaminan {$info} belum dirilis";
                continue;
            }

            if ($match->STATUS_REC === 'AC') {
                $errors[] = "Jaminan {$info} masih terdaftar pada kredit aktif";
            }
        }
    }
}
