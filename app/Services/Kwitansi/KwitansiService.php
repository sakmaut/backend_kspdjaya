<?php

namespace App\Services\Kwitansi;

use App\Http\Controllers\Enum\UserPosition\UserPositionEnum;
use App\Repository\Payment\KwitansiRepository;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class KwitansiService
{
    protected $kwitansiRepository;
    protected $userPositionEnum;
    protected $uuid;

    function __construct(
        KwitansiRepository $kwitansiRepository,
        UserPositionEnum $userPositionEnum
    ) {
        $this->kwitansiRepository = $kwitansiRepository;
        $this->userPositionEnum = $userPositionEnum;
        $this->uuid = Uuid::uuid7()->toString();
    }

    public function getKwitansiPayment($request)
    {
        $user = $request->user();

        if ($user->position === $this->userPositionEnum::HO) {
            return $this->kwitansiRepository->getPendingForHO();
        }

        $filters = [
            ['PAYMENT_TYPE', $request->query('tipe') === 'pelunasan' ? '=' : '!=', 'pelunasan'],
            ['NO_TRANSAKSI', '=', $request->query('notrx')],
            ['NAMA', 'like', '%' . $request->query('nama') . '%'],
            ['LOAN_NUMBER', '=', $request->query('no_kontrak')],
        ];

        $dari = $request->query('dari');
        $dateFilter = null;

        if ($dari && $dari !== 'null') {
            $dateFilter = Carbon::parse($dari)->toDateString();
        } elseif (
            blank($request->query('notrx')) &&
            blank($request->query('nama')) &&
            blank($request->query('no_kontrak'))
        ) {
            $dateFilter = Carbon::today()->toDateString();
        }

        return $this->kwitansiRepository->getFilteredForBranch($user->branch_id, $filters, $dateFilter);
    }

    public function create($request)
    {
        $this->kwitansiRepository->create();
    }
}
