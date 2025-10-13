<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Repository\R_Tagihan;
use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_TagihanLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class S_Tagihan extends R_Tagihan
{
    protected $repository;

    public function __construct(R_Tagihan $repository)
    {
        $this->repository = $repository;
    }

    // private function createAutoCode($table, $field, $prefix)
    // {
    //     $query = $table::max($field);
    //     $_trans = date("Ymd");

    //     $prefixLength = strlen($prefix);

    //     $startPos = $prefixLength + 11;

    //     $noUrut = !empty($query) ? (int) substr($query, $startPos, 5) : 0;
    //     $noUrut++;

    //     $generateCode = $prefix . '/' . $_trans . '/' . sprintf("%05d", $noUrut);

    //     return $generateCode;
    // }

    public function listTagihanByUserId($request)
    {
        $userId = optional($request->user())->username;

        if (!$userId) {
            throw new \Exception("User ID not found.", 500);
        }

        return $this->repository->getListTagihanByUserUsername($userId);
    }


    public function listTagihanWithCreditSchedule($loanNumber)
    {
        return $this->repository->listTagihanWithCreditSchedule($loanNumber);
    }

    public function listTagihanByBranchId($request)
    {
        $getCurrentBranch = $request->user()->branch_id ?? null;

        return $this->repository->listTagihanByBranchId($getCurrentBranch);
    }

    public function cl_deploy_by_pic($pic)
    {
        $sql = $this->repository->cl_deploy_by_pic($pic);
        return $sql;
    }

    public function getListTagihan($request)
    {
        $sql = $this->repository->getAllListTagihan($request);
        return $sql;
    }

    protected function deleteByUserId($userId)
    {
        return $this->repository->deleteByUserId($userId);
    }

    public function createTagihan($request)
    {
        $savedData = [];

        foreach ($request['list_tagihan'] as $item) {
            $loanNumber = $item['NO KONTRAK'] ?? "";

            if (empty($loanNumber)) {
                throw new Exception("NO KONTRAK is required.");
            }

            $detailData = [
                'USER_ID'       => $request['user_id'],
                'BRANCH_ID'     => $request->user()->branch_id ?? null,
                'LOAN_NUMBER'   => $loanNumber,
                'TGL_JTH_TEMPO' => Carbon::parse($item['JTH TEMPO AWAL'])->format('Y-m-d') ?? null,
                'NAMA_CUST'     => $item['NAMA PELANGGAN'] ?? null,
                'CYCLE_AWAL'    => $item['CYCLE AWAL'] ?? null,
                'N_BOT'         => $item['NBOT'] ?? null,
                'ALAMAT'        => $item['ALAMAT TAGIH'] ?? null,
                'DESA'          => $item['KELURAHAN'] ?? null,
                'KEC'           => $item['KECAMATAN'] ?? null,
                'CREATED_BY'    => $request->user()->id ?? null,
            ];

            if ($loanNumber) {
                $existing = $this->repository->findByLoanNumber($loanNumber);

                if ($existing) {
                    $updated = $this->repository->update($existing->ID, $detailData);
                    $savedData[] = $updated;
                    continue;
                }
            }

            $saved = $this->repository->create($detailData);
            M_TagihanLog::create([
                'LOAN_NUMBER' => $loanNumber,
                'LKP_ID' => $saved->ID ?? "",
                'DESCRIPTION' => 'Tagihan created with LOAN_NUMBER: ' . $loanNumber,
                'STATUS'     => 'CREATE_DEPLOY',
                'CREATED_BY' => $request->user()->id ?? null,
            ]);
            $savedData[] = $saved;
        }

        return $savedData;
    }

    protected function saveTagihanDetail($tagihanId, $creditScheduleData)
    {
        // $result = !empty($loanNumber) ? DB::select('CALL get_credit_schedule(?)', [$loanNumber]) : [];
        foreach ($creditScheduleData as $item) {
            M_TagihanDetail::updateOrCreate(
                [
                    'TAGIHAN_ID'        => $tagihanId,
                    'INSTALLMENT_COUNT' => $item->INSTALLMENT_COUNT ?? null,
                ],
                [
                    'PAYMENT_DATE'            => $item->PAYMENT_DATE ?? null,
                    'PRINCIPAL'               => $item->PRINCIPAL ?? null,
                    'INTEREST'                => $item->INTEREST ?? null,
                    'INSTALLMENT'             => $item->INSTALLMENT ?? null,
                    'PRINCIPAL_REMAINS'       => $item->PRINCIPAL_REMAINS ?? null,
                    'PAYMENT_VALUE_PRINCIPAL' => $item->PAYMENT_VALUE_PRINCIPAL ?? null,
                    'PAYMENT_VALUE_INTEREST'  => $item->PAYMENT_VALUE_INTEREST ?? null,
                    'DISCOUNT_PRINCIPAL'      => $item->DISCOUNT_PRINCIPAL ?? null,
                    'DISCOUNT_INTEREST'       => $item->DISCOUNT_INTEREST ?? null,
                    'INSUFFICIENT_PAYMENT'    => $item->INSUFFICIENT_PAYMENT ?? null,
                    'PAYMENT_VALUE'           => $item->PAYMENT_VALUE ?? null,
                    'PAID_FLAG'               => $item->PAID_FLAG ?? null,
                ]
            );
        }
    }
}
