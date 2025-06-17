<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_BpkbList;
use App\Models\M_BpkbApproval;
use App\Models\M_BpkbDetail;
use App\Models\M_BpkbTransaction;
use App\Models\M_CrCollateral;
use App\Models\M_CrCollateralSertification;
use App\Models\M_LocationStatus;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid as Uuid;

class BpkbTransactionController extends Controller
{
    protected $request;
    protected $locationStatus;
    protected $log;

    public function __construct(Request $request, LocationStatus $locationStatus, ExceptionHandling $log)
    {
        $this->locationStatus = $locationStatus;
        $this->request = $request;
        $this->log = $log;
    }

    public function index()
    {
        try {
            $request = $this->request;

            $user = $request->user();
            $branchId = $user->branch_id ?? null;

            $no_transaksi = $request->query('no_transaksi');
            $status = $request->query('status');
            $tgl = $request->query('tgl');

            $data = M_BpkbTransaction::leftJoin('users as b', 'b.id', '=', 'bpkb_transaction.CREATED_BY')
                ->where('b.branch_id', '=', $branchId)
                ->select('bpkb_transaction.*', 'b.branch_id');

            if ($no_transaksi) {
                $data->where('bpkb_transaction.TRX_CODE', '=', $no_transaksi);
            }

            if ($status && strtoupper($status) != "SEMUA") {

                if (strtoupper($status) == 'PENDING') {
                    $statuses = ['SENDING', 'REQUEST'];
                    $data->whereIn('bpkb_transaction.STATUS', $statuses);
                } else {
                    $data->where('bpkb_transaction.STATUS', '=', strtoupper($status));
                }
            }

            if ($tgl) {
                $data->whereDate('bpkb_transaction.CREATED_AT', Carbon::parse($tgl)->toDateString());
            } else {
                $data->whereDate('bpkb_transaction.CREATED_AT', Carbon::parse(now())->toDateString());
            }

            $result = $data->orderBy('bpkb_transaction.TRX_CODE', 'desc')->get();

            $jsonData = R_BpkbList::collection($result);

            return response()->json($jsonData, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function listApproval()
    {
        try {
            $request = $this->request;

            $user = $request->user();
            $branch = $user->branch_id ?? null;

            $no_transaksi = $request->query('no_transaksi');
            $status = $request->query('status');
            $tgl = $request->query('tgl');

            $data = M_BpkbTransaction::where('TO_BRANCH', $branch);

            if ($no_transaksi) {
                $data->where('TRX_CODE', '=', $no_transaksi);
            }

            if ($status && strtoupper($status) != "SEMUA") {

                if (strtoupper($status) == 'PENDING') {
                    $statuses = ['SENDING', 'REQUEST'];
                    $data->whereIn('STATUS', $statuses);
                } else {
                    $data->where('STATUS', '=', strtoupper($status));
                }
            }

            if ($tgl) {
                $data->whereDate('CREATED_AT', Carbon::parse($tgl)->toDateString());
            } else {
                $data->whereDate('CREATED_AT', Carbon::parse(now())->toDateString());
            }

            $result = $data->orderBy('TRX_CODE', 'desc')->get();

            $dto = R_BpkbList::collection($result);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function store()
    {
        DB::beginTransaction();
        try {
            $request = $this->request;

            if (!isset($request->bpkb) || empty($request->bpkb)) {
                throw new Exception("bpkb not found!!!");
            }

            $user = $request->user();
            $branch = $user->branch_id ?? null;

            if ($request->type == 'send') {
                $data = [
                    'TRX_CODE' => generateCodeJaminan($request, 'bpkb_transaction', 'TRX_CODE', 'JMN'),
                    'FROM_BRANCH' => $branch,
                    'TO_BRANCH' => $request->tujuan,
                    'CATEGORY' => $request->type ?? null,
                    'NOTE' => $request->catatan,
                    'STATUS' => 'SENDING',
                    'COURIER' => $request->kurir ?? null,
                    'CREATED_BY' => $user->id
                ];
            } else {

                $data = [
                    'TRX_CODE' => generateCodeJaminan($request, 'bpkb_transaction', 'TRX_CODE', 'JMN'),
                    'FROM_BRANCH' => '',
                    'TO_BRANCH' => $branch,
                    'CATEGORY' => $request->type ?? null,
                    'NOTE' => $request->catatan,
                    'STATUS' => 'REQUEST',
                    'COURIER' => $request->kurir ?? null,
                    'CREATED_BY' => $user->id
                ];
            }

            $transaction = M_BpkbTransaction::create($data);
            $id = $transaction->ID;

            $data_approval = [
                'BPKB_TRANSACTION_ID' => $id,
                'ONCHARGE_APPRVL' => 'sending',
                'ONCHARGE_PERSON' => $user->id,
                'ONCHARGE_TIME' => Carbon::now(),
                'ONCHARGE_DESCR' => $request->catatan,
                'APPROVAL_RESULT' => 'SENDING'
            ];

            M_BpkbApproval::create($data_approval);

            if (!empty($request->bpkb) && is_array($request->bpkb)) {

                $details = [];
                $collateralIds = [];

                foreach ($request->bpkb as $res) {

                    if (empty($res['ID'])) {
                        throw new Exception("ID Not Found", 404);
                    }

                    $details[] = [
                        'ID' => Uuid::uuid7()->toString(),
                        'BPKB_TRANSACTION_ID' => $transaction->ID,
                        'COLLATERAL_ID' => $res['ID'],
                        'STATUS' => 'SENDING'
                    ];
                    $collateralIds[] = $res['ID'];

                    $checkCollateralId = M_CrCollateral::where('ID', $res['ID'])->first();

                    if ($checkCollateralId) {
                        $checkCollateralId->update(['STATUS' => 'SENDING']);
                    }
                }

                M_BpkbDetail::insert($details);
            }

            DB::commit();
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function update_status(Request $request)
    {
        DB::beginTransaction();
        try {

            if (!isset($request->collateral_id) || empty($request->collateral_id) || !is_array($request->collateral_id)) {
                throw new Exception("collateral id not found!!!");
            }

            $user = $request->user();

            foreach ($request->collateral_id as $list) {
                $check = M_BpkbDetail::where('COLLATERAL_ID', $list)->first();

                if ($check) {
                    $check->update(['STATUS' => strtoupper($request->status)]);

                    $data_approval = [
                        'BPKB_TRANSACTION_ID' => $check->BPKB_TRANSACTION_ID ?? '',
                        'ONCHARGE_APPRVL' => strtoupper($request->status),
                        'ONCHARGE_PERSON' => $user->id,
                        'ONCHARGE_TIME' => Carbon::now(),
                        'ONCHARGE_DESCR' => $request->catatan,
                        'APPROVAL_RESULT' => strtoupper($request->status)
                    ];

                    M_BpkbApproval::create($data_approval);
                }
            }

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function approval()
    {
        try {
            $request = $this->request;
            $user = $request->user();

            $request->validate([
                'no_surat' => 'required|string',
                'flag' => 'required|string',
            ]);

            $check = M_BpkbTransaction::where('TRX_CODE', $request->no_surat)->first();

            if (!$check) {
                throw new Exception("no surat is not found.", 404);
            }

            $flag = $request->flag;

            if ($flag == 'yes') {

                if ($check->CATEGORY == 'request') {

                    $jaminan = $request->jaminan;
                    if (!empty($jaminan) && is_array($jaminan)) {

                        $transactionId = $check->ID;

                        M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)->whereIn('ID', $jaminan)
                            ->update([
                                'STATUS' => 'NORMAL',
                                'UPDATED_BY' => $request->user()->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);

                        M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)
                            ->whereNotIn('ID', $jaminan)
                            ->update([
                                'STATUS' => 'REJECTED',
                                'UPDATED_BY' => $request->user()->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);

                        $data = [
                            'TRX_CODE' => generateCodeJaminan($request, 'bpkb_transaction', 'TRX_CODE', 'JMN'),
                            'FROM_BRANCH' => $check->TO_BRANCH ?? '',
                            'TO_BRANCH' => $check->FROM_BRANCH,
                            'CATEGORY' => "send",
                            'NOTE' => $request->catatan ?? '',
                            'STATUS' => 'SENDING',
                            'COURIER' => $request->kurir ?? "'",
                            'CREATED_BY' => $user->id
                        ];

                        $transaction = M_BpkbTransaction::create($data);

                        $id = $transaction->ID;

                        $data_approval = [
                            'BPKB_TRANSACTION_ID' => $id,
                            'ONCHARGE_APPRVL' => 'sending',
                            'ONCHARGE_PERSON' => $user->id,
                            'ONCHARGE_TIME' => Carbon::now(),
                            'ONCHARGE_DESCR' => $request->catatan,
                            'APPROVAL_RESULT' => 'SENDING'
                        ];

                        M_BpkbApproval::create($data_approval);

                        $details = [];
                        $collateralIds = [];

                        $getDetail = M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)->where('STATUS', 'NORMAL')->get();

                        $getbpkbDetailsRejected = M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)->where('STATUS', 'REJECTED');
                        $collateralIdsRejected = $getbpkbDetailsRejected->pluck('COLLATERAL_ID')->toArray();

                        if (!empty($collateralIdsRejected)) {
                            M_CrCollateral::whereIn('ID', $collateralIdsRejected)
                                ->update([
                                    'STATUS' => 'NORMAL'
                                ]);
                        }

                        foreach ($getDetail as $res) {
                            $details[] = [
                                'ID' => Uuid::uuid7()->toString(),
                                'BPKB_TRANSACTION_ID' => $transaction->ID,
                                'COLLATERAL_ID' => $res['COLLATERAL_ID'],
                                'STATUS' => 'SENDING'
                            ];
                            $collateralIds[] = $res['ID'];

                            $checkCollateralId = M_CrCollateral::where('ID', $res['COLLATERAL_ID'])->first();

                            if ($checkCollateralId) {
                                $checkCollateralId->update(['STATUS' => 'SENDING']);
                            }
                        }

                        M_BpkbDetail::insert($details);
                    }
                } else {

                    $jaminan = $request->jaminan;
                    if (!empty($jaminan) && is_array($jaminan)) {

                        $transactionId = $check->ID;

                        M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)->whereIn('ID', $jaminan)
                            ->update([
                                'STATUS' => 'NORMAL',
                                'UPDATED_BY' => $request->user()->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);

                        M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)
                            ->whereNotIn('ID', $jaminan)
                            ->update([
                                'STATUS' => 'REJECTED',
                                'UPDATED_BY' => $request->user()->id,
                                'UPDATED_AT' => Carbon::now()
                            ]);

                        $bpkbDetails = M_BpkbDetail::whereIn('ID', $jaminan)->where('STATUS', 'NORMAL')->select('ID', 'COLLATERAL_ID')->get();

                        $collateralIds = $bpkbDetails->pluck('COLLATERAL_ID')->toArray();

                        if (!empty($collateralIds)) {
                            M_CrCollateral::whereIn('ID', $collateralIds)
                                ->update([
                                    'LOCATION_BRANCH' => $request->user()->branch_id ?? '',
                                    'STATUS' => 'NORMAL'
                                ]);
                        }

                        $getbpkbDetailsRejected = M_BpkbDetail::where('BPKB_TRANSACTION_ID', $transactionId)->where('STATUS', 'REJECTED');
                        $collateralIdsRejected = $getbpkbDetailsRejected->pluck('COLLATERAL_ID')->toArray();

                        if (!empty($collateralIdsRejected)) {
                            M_CrCollateral::whereIn('ID', $collateralIdsRejected)
                                ->update([
                                    'STATUS' => 'NORMAL'
                                ]);
                        }

                        foreach ($jaminan as $list) {
                            $this->locationStatus->createLocationStatusLog($list, $request->user()->branch_id, 'SEND TO HO');
                        }
                    }

                    $approvalDataMap = [
                        'yes' => ['code' => 'APHO', 'result' => 'disetujui ho'],
                        'revisi' => ['code' => 'REORHO', 'result' => 'ada revisi ho'],
                        'no' => ['code' => 'CLHO', 'result' => 'dibatalkan ho'],
                    ];

                    $approvalData = $approvalDataMap[$flag] ?? $approvalDataMap['no'];

                    $data_log = [
                        'ID' => Uuid::uuid7()->toString(),
                        'BPKB_TRANSACTION_ID' => $check->ID,
                        'ONCHARGE_APPRVL' => $approvalData['code'],
                        'ONCHARGE_PERSON' => $request->user()->id,
                        'ONCHARGE_TIME' => Carbon::now(),
                        'ONCHARGE_DESCR' => $request->keterangan ?? '',
                        'APPROVAL_RESULT' => $approvalData['result']
                    ];

                    M_BpkbApproval::create($data_log);
                }

                $check->update([
                    'STATUS' => 'SELESAI',
                ]);
            }

            return response()->json(['message' => 'Successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function jaminan_transaction_permintaan(Request $request)
    {
        DB::beginTransaction();
        try {

            $getCollateralId = $request->collateral_id;
            $user = $request->user();
            $status = "REQUEST";

            if (!is_array($getCollateralId) || empty($getCollateralId)) {
                throw new Exception("collateral not found!!!");
            }

            $result = [];
            foreach ($getCollateralId as $item) {
                $type = $item['type'] ?? 'kendaraan';
                $result[$type][] = $item['ID'];
            }

            $combinedCollaterals = [];
            if (isset($result['kendaraan'])) {
                $collaterals = M_CrCollateral::whereIn('ID', $result['kendaraan'])->get();

                foreach ($collaterals as $collateral) {
                    $collateral = $collateral->toArray();
                    $collateral['TYPE'] = 'kendaraan';
                    $combinedCollaterals[] = $collateral;
                }

                M_CrCollateral::whereIn('ID', $result['kendaraan'])
                    ->update([
                        'STATUS' => 'REQUEST'
                    ]);
            }

            $transactions = [];
            $approvals = [];
            $details = [];
            $groupedByBranch = [];

            // First, group collaterals by branch
            foreach ($combinedCollaterals as $key => $list) {
                $branch = $list['LOCATION_BRANCH'] ?? $list['LOCATION'] ?? '';
                $groupedByBranch[$branch][] = $list;
            }

            // Then create transactions for each group
            foreach ($groupedByBranch as $fromBranch => $collaterals) {
                $uuid = Uuid::uuid7()->toString();

                // Create single transaction for this branch
                $transactions[] = [
                    'ID' => $uuid,
                    'TRX_CODE' => generateCodeJaminan($request, 'bpkb_transaction', 'TRX_CODE', 'JMN'),
                    'FROM_BRANCH' => $user->branch_id ?? '',
                    'TO_BRANCH' => $fromBranch ?? '',
                    'CATEGORY' => strtolower($status),
                    'NOTE' => $request->catatan ?? '',
                    'STATUS' => $status,
                    'COURIER' => "",
                    'CREATED_BY' => $user->id
                ];

                // Create single approval for this branch
                $approvals[] = [
                    'ID' => Uuid::uuid7()->toString(),
                    'BPKB_TRANSACTION_ID' => $uuid,
                    'ONCHARGE_APPRVL' => strtolower($status),
                    'ONCHARGE_PERSON' => $user->id,
                    'ONCHARGE_TIME' => Carbon::now(),
                    'ONCHARGE_DESCR' => $request->catatan,
                    'APPROVAL_RESULT' => $status
                ];

                // Create details for each collateral in this branch
                foreach ($collaterals as $list) {
                    $details[] = [
                        'ID' => Uuid::uuid7()->toString(),
                        'BPKB_TRANSACTION_ID' => $uuid,
                        'COLLATERAL_ID' => $list['ID'],
                        'STATUS' => $status
                    ];
                }
            }

            M_BpkbTransaction::insert($transactions);
            M_BpkbApproval::insert($approvals);
            M_BpkbDetail::insert($details);

            DB::commit();
            ActivityLogger::logActivity($request, "Success", 200);
            return response()->json(['message' => 'created successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}
