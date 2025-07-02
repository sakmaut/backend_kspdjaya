<?php

namespace App\Http\Controllers\Service\Kwitansi;

use App\Http\Controllers\Enum\UserPosition\UserPositionEnum;
use App\Models\M_Kwitansi;
use Carbon\Carbon;

class KwitansiService
{
    protected $model;
    protected $userPositionEnum;

    function __construct(M_Kwitansi $model, UserPositionEnum $userPositionEnum)
    {
        $this->model = $model;
        $this->userPositionEnum = $userPositionEnum;
    }

    public function getKwitansiPayment($request)
    {
        $user = $request->user();
        $query = $this->model::orderBy('CREATED_AT', 'DESC');

        if ($user->position === $this->userPositionEnum::HO) {
            $results = $query->where('STTS_PAYMENT', 'PENDING')
                ->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('METODE_PEMBAYARAN', '!=', 'cash')
                            ->whereIn('PAYMENT_TYPE', ['angsuran', 'pokok_sebagian']);
                    })->orWhere(function ($sub) {
                        $sub->where('METODE_PEMBAYARAN', 'cash')
                            ->where('PAYMENT_TYPE', 'pelunasan');
                    });
                })->get();

            return $results;
        }

        $query->where('BRANCH_CODE', $user->branch_id);

        $filters = [
            ['PAYMENT_TYPE', $request->query('tipe') === 'pelunasan' ? '=' : '!=', 'pelunasan'],
            ['NO_TRANSAKSI', '=', $request->query('notrx')],
            ['NAMA', 'like', '%' . $request->query('nama') . '%'],
            ['LOAN_NUMBER', '=', $request->query('no_kontrak')],
        ];

        foreach ($filters as [$column, $operator, $value]) {
            if ($value && $value !== '%') {
                $query->where($column, $operator, $value);
            }
        }

        $dari = $request->query('dari');

        if ($dari && $dari !== 'null') {
            $query->whereDate('CREATED_AT', Carbon::parse($dari)->toDateString());
        } elseif (
            blank($request->query('notrx')) &&
            blank($request->query('nama')) &&
            blank($request->query('no_kontrak'))
        ) {
            $query->whereDate('CREATED_AT', Carbon::today()->toDateString());
        }

        return $query->get();
    }

    public function create()
    {
    }
}
