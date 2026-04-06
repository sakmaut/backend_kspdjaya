<?php

namespace App\Http\Credit\Tagihan\Service;

use App\Http\Controllers\API\OrderStatus;
use App\Http\Credit\Tagihan\Model\M_Tagihan;
use App\Http\Credit\Tagihan\Repository\R_Tagihan;
use App\Http\Credit\TagihanDetail\Model\M_TagihanDetail;
use App\Models\M_Branch;
use App\Models\M_Lkp;
use App\Models\M_LkpDetail;
use App\Models\M_TagihanLog;
use App\Models\User;
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

    private function createAutoCode($request, $table, $field, $prefix)
    {
        $_trans = date("ym");

        $branchId = $request->user()->branch_id;
        $branch = M_Branch::find($branchId);

        $codeStart = $prefix . $branch->CODE_NUMBER . $_trans;

        $lastCode = $table::where($field, 'like', $codeStart . '%')->max($field);

        $noUrut = 1;

        if (!empty($lastCode)) {
            $lastNumber = (int) substr($lastCode, -5);
            $noUrut = $lastNumber + 1;
        }

        $generateCode = $codeStart . sprintf("%05d", $noUrut);

        return $generateCode;
    }

    private function createAutoCodeMonthlyReset($request, $table, $field, $prefix)
    {
        $yearMonth    = date("ym");   
        $today        = date("ymd");

        $branchId = $request->user()->branch_id;
        $branch   = M_Branch::find($branchId);

        $searchPrefix = $prefix . $branch->CODE_NUMBER . $yearMonth;

        $lastNumber = $table::where($field, 'like', $searchPrefix . '%')
            ->selectRaw("MAX(CAST(RIGHT($field,3) AS UNSIGNED)) as max_counter")
            ->value('max_counter');

        $noUrut = $lastNumber ? $lastNumber + 1 : 1;

        $finalPrefix = $prefix . $branch->CODE_NUMBER . $today;

        return $finalPrefix . sprintf("%03d", $noUrut);
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
        $user = $request->user();

        $currentBranch = $user->branch_id ?? null;
        $currentPosition = strtoupper($user->position ?? '');
        
        $branchId = ($currentPosition === 'HO') ? null : $currentBranch;

        return $this->repository->listTagihan($branchId);
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
                'NO_SURAT'      => $this->createAutoCode($request, M_Tagihan::class, 'NO_SURAT', 'SRT'),
                'USER_ID'       => $request['user_id'],
                'BRANCH_ID'     => $request->user()->branch_id ?? null,
                'CREDIT_ID'     => $item['CREDIT_ID'] ?? null,
                'LOAN_NUMBER'   => $loanNumber,
                'CUST_CODE'     => $item['CUST_CODE'] ?? null,
                'TGL_JTH_TEMPO' => Carbon::parse($item['JTH TEMPO AWAL'])->format('Y-m-d') ?? null,
                'CYCLE_AWAL'    => $item['CYCLE AWAL'] ?? null,
                'CYCLE_AKHIR'   => $item['CYCLE_AKHIR'] ?? null,
                'N_BOT'         => $item['NBOT'] ?? null,
                'TENOR'         => $item['PERIOD'] ?? null,
                'CATT_SURVEY'   => $item['CATT_SURVEY'] ?? null,
                'MCF'           => $item['SURVEYOR'] ?? null,
                'ANGSURAN_KE'   => $item['ANGS KE'] ?? 0,
                'ANGSURAN'      => $item['ANGSURAN'] ?? 0,
                'AMBC_TOTAL_AWAL' => $item['AMBC TOTAL AWAL'] ?? 0,
                'STATUS'        => "Aktif",
                'CREATED_BY'    => $request->user()->id ?? null,
                'CREATED_AT' => Carbon::now('Asia/Jakarta'),
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
            // M_TagihanLog::create([
            //     'LOAN_NUMBER' => $loanNumber,
            //     'LKP_ID' => $saved->ID ?? "",
            //     'DESCRIPTION' => 'Tagihan created with LOAN_NUMBER: ' . $loanNumber,
            //     'STATUS'     => 'CREATE_DEPLOY',
            //     'CREATED_BY' => $request->user()->id ?? null,
            // ]);
            $savedData[] = $saved;
        }

        return $savedData;
    }

    public function createLkp($request)
    {
        $list_lkp = is_array($request['list_lkp']) ? $request['list_lkp'] : [];
        $countNoa = count($list_lkp);

        $branchId = $request->user()->branch_id;

        // jika posisi HO
        if ($request->user()->position === 'HO' && !empty($request['user_id'])) {
            $targetUser = User::select('branch_id')
                ->where('username', $request['user_id'])
                ->first();

            if ($targetUser) {
                $branchId = $targetUser->branch_id;
            }
        }

        $detailData = [
            'LKP_NUMBER' => $this->createAutoCodeMonthlyReset($request, M_Lkp::class, 'LKP_NUMBER', 'LKP'),
            'USER_ID'    => $request['user_id'] ?? null,
            'BRANCH_ID'  => $branchId,
            'NOA'        => $countNoa,
            'STATUS' => $request->IsDraf ? 'Draft' : 'Active',
            'CREATED_BY' => $request->user()->id ?? null,
            'CREATED_AT' => Carbon::now('Asia/Jakarta'),
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

            // M_TagihanLog::create([
            //     'LOAN_NUMBER' => $loanNumber,
            //     'LKP_ID'      => $saved->ID ?? null,
            //     'DESCRIPTION' => 'LKP created with LOAN_NUMBER: ' . $loanNumber,
            //     'STATUS'      => 'CREATE_LKP',
            //     'CREATED_BY'  => $request->user()->id ?? null,
            // ]);
        }

        return $saved;
    }

    public function updateLkp($request)
    {
        $list_lkp = is_array($request['list_lkp']) ? $request['list_lkp'] : [];
        $countNoa = count($list_lkp);

        $lkp = M_Lkp::where('ID', $request->LkpId)->first();

        if (!$lkp) {
            throw new Exception("LKP tidak ditemukan.");
        }

        $lkp->update([
            'USER_ID'    => $request['user_id'] ?? null,
            'BRANCH_ID'  => $request->user()->branch_id ?? null,
            'NOA'        => $countNoa,
            'STATUS'     => $request->IsDraf ? 'Draft' : 'Active',
            'UPDATED_BY' => $request->user()->id ?? null,
            'UPDATED_AT' => Carbon::now('Asia/Jakarta'),
        ]);

        M_LkpDetail::where('LKP_ID', $lkp->ID)->delete();

        foreach ($list_lkp as $item) {

            $loanNumber = $item['no_kontrak'] ?? "";

            if (empty($loanNumber)) {
                throw new Exception("NO KONTRAK is required.");
            }

            M_LkpDetail::create([
                'NO_SURAT'    => $item['no_surat'] ?? null,
                'LKP_ID'      => $lkp->ID,
                'LOAN_NUMBER' => $loanNumber,
                'LOAN_HOLDER' => $item['nama_customer'] ?? null,
                'ADDRESS'     => $item['alamat'] ?? null,
                'DESA'        => $item['desa'] ?? null,
                'KEC'         => $item['kec'] ?? null,
                'CYCLE'       => $item['cycle_awal'] ?? null,
                'CREATED_BY'  => $request->user()->id ?? null,
                'DUE_DATE'    => $item['tgl_jatuh_tempo'] ?? null,
                'INSTALLMENT' => $item['angsuran'] ?? 0,
                'INST_COUNT'  => $item['angsuran_ke'] ?? 0,
            ]);
        }

        return $lkp;
    }
}
