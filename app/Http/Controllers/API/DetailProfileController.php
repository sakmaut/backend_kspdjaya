<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Http\Resources\R_DetailProfile;
use App\Models\M_HrEmployee;
use App\Models\M_HrEmployeeDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Ramsey\Uuid\Uuid;

class DetailProfileController extends Controller
{
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function index(Request $request)
    {
        try {
            $getEmployeeId = $request->user()->employee_id;

            $results = M_HrEmployee::with(['hr_rolling' => function ($query) {
                $query->where('USE_FLAG', 'Active');
            }, 'hr_rolling.hr_position'])->where('ID', $getEmployeeId)->first();

            $dto = new R_DetailProfile($results);

            return response()->json(['response' => $dto], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }

    public function uploadImage(Request $request)
    {
        DB::beginTransaction();
        try {

            $this->validate($request, [
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg',
                'type' => 'required|string'
            ]);

            $image_path = $request->file('image')->store('public/Employee_Image');
            $image_path = str_replace('public/', '', $image_path);

            $url = URL::to('/') . '/storage/' . $image_path;

            $data_array_attachment = [
                'ID' => Uuid::uuid4()->toString(),
                'USERS_ID' => $request->user()->id,
                'TYPE' => $request->type,
                'PATH' => $url ?? ''
            ];

            M_HrEmployeeDocument::create($data_array_attachment);

            DB::commit();
            return response()->json(['message' => 'Image upload successfully'], 200);
        } catch (\Exception $e) {
            return $this->log->logError($e, $request);
        }
    }
}
