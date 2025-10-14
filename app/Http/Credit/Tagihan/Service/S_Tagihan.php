<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Repository\R_Tagihan;
use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_Lkp;
use App\Models\M_LkpDetail;
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

    private function createAutoCode($table, $field, $prefix)
    {
        $query = $table::max($field);
        $_trans = date("Ymd");

        $prefixLength = strlen($prefix);

        $startPos = $prefixLength + 11;

        $noUrut = !empty($query) ? (int) substr($query, $startPos, 5) : 0;
        $noUrut++;

        $generateCode = $prefix . '-' . $_trans . '-' . sprintf("%05d", $noUrut);

        return $generateCode;
    }

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
                'NO_SURAT'      => $this->createAutoCode(M_Tagihan::class, 'NO_SURAT', 'SRT'),
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
                'MCF'           => $item['SURVEYOR'] ?? null,
                'ANGSURAN_KE'   => $item['ANGS KE'] ?? 0,
                'ANGSURAN'      => $item['ANGSURAN'] ?? 0,
                'BAYAR'         => $item['AC TOTAL'] ?? 0,
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

    public function createLkp($request)
    {
        $list_lkp = is_array($request['list_lkp']) ? $request['list_lkp'] : [];
        $countNoa = count($list_lkp);

        $detailData = [
            'LKP_NUMBER' => $this->createAutoCode(M_Lkp::class, 'LKP_NUMBER', 'LKP'),
            'USER_ID'    => $request['user_id'] ?? null,
            'BRANCH_ID'  => $request->user()->branch_id ?? null,
            'NOA'        => $countNoa,
            'CREATED_BY' => $request->user()->id ?? null,
        ];

        $saved = M_Lkp::create($detailData);

        foreach ($list_lkp as $item) {
            $loanNumber = $item['no_kontrak'] ?? "";

            if (empty($loanNumber)) {
                throw new Exception("NO KONTRAK is required.");
            }

            M_LkpDetail::create([
                'NO_SURAT'      => $item['no_surat'] ?? null,
                'LKP_ID'      => $saved->ID ?? null,
                'LOAN_NUMBER' => $loanNumber,
                'LOAN_HOLDER' => $item['nama_customer'] ?? null,
                'ADDRESS'    => $item['alamat'] ?? null,
                'DESA'          => $item['desa'] ?? null,
                'KEC'       => $item['kec'] ?? null,
                'CYCLE'      => $item['cycle_awal'] ?? null,
                'CREATED_BY' => $request->user()->id ?? null,
                'DUE_DATE'   => $item['tgl_jatuh_tempo'] ?? null,
                'INSTALLMENT'  => $item['angsuran'] ?? 0,
                'INST_COUNT' => $item['angsuran_ke'] ?? 0,
            ]);

            M_TagihanLog::create([
                'LOAN_NUMBER' => $loanNumber,
                'LKP_ID'      => $saved->ID ?? null,
                'DESCRIPTION' => 'LKP created with LOAN_NUMBER: ' . $loanNumber,
                'STATUS'      => 'CREATE_LKP',
                'CREATED_BY'  => $request->user()->id ?? null,
            ]);
        }

        return $saved;
    }

    // public function createLkp($request)
    // {
    //     $savedData = [];

    //     $list_lkp = is_array($request['list_lkp']) ? $request['list_lkp'] : [];
    //     $countNoa = count($list_lkp);

    //     $detailData = [
    //         'LKP_NUMBER' => $this->createAutoCode(M_Lkp::class, 'LKP_NUMBER', 'LKP'),
    //         'USER_ID'    => $request['user_id'] ?? null,
    //         'BRANCH_ID'  => $request->user()->branch_id ?? null,
    //         'NOA'        => $countNoa,
    //         'CREATED_BY' => $request->user()->id ?? null,
    //     ];

    //     $saved = M_Lkp::create($detailData);

    //     foreach ($list_lkp as $item) {
    //         $loanNumber = $item['no_kontrak'] ?? "";

    //         if (empty($loanNumber)) {
    //             throw new Exception("NO KONTRAK is required.");
    //         }

    //         $result = DB::select('CALL get_credit_schedule(?)', [$loanNumber]);

    //         if (!empty($result)) {
    //             foreach ($result as $res) {
    //                 M_LkpDetail::create([
    //                     'LKP_ID'      => $saved->ID ?? null,
    //                     'LOAN_NUMBER' => $loanNumber,
    //                     'LOAN_HOLDER' => $item['nama_customer'] ?? null,
    //                     'ADDRESS'    => $item['alamat'] ?? null,
    //                     'CYCLE'      => $item['cycle_awal'] ?? null,
    //                     'CREATED_BY' => $request->user()->id ?? null,
    //                     'DUE_DATE'   => $res->PAYMENT_DATE ?? null,
    //                     'PRINCIPAL'  => $res->PRINCIPAL ?? null,
    //                     'INTEREST'   => $res->INTEREST ?? null,
    //                     'INST_COUNT' => $res->INSTALLMENT_COUNT ?? null,
    //                 ]);
    //             }
    //         }

    //         M_TagihanLog::create([
    //             'LOAN_NUMBER' => $loanNumber,
    //             'LKP_ID'      => $saved->ID ?? null,
    //             'DESCRIPTION' => 'LKP created with LOAN_NUMBER: ' . $loanNumber,
    //             'STATUS'      => 'CREATE_LKP',
    //             'CREATED_BY'  => $request->user()->id ?? null,
    //         ]);
    //     }

    //     return $saved;
    // }
}
