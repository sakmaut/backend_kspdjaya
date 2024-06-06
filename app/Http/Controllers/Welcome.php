<?php

namespace App\Http\Controllers;

use App\Models\M_CrPersonal;
use App\Models\M_CrProspect;
use App\Models\M_DeuteronomyTransactionLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Image;
use Illuminate\Support\Facades\URL;

class Welcome extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $source = $_FILES["image"]['tmp_name'];
        $destination = '../public/storage/Cr_Prospect/' . $_FILES["image"]["name"];
        $push = self::compress($source,$destination,2);

        return response()->json(['message' => 'OK',"status" => 200,'response' => $push], 200);
    }

    function compress($source, $destination, $quality) {

        $info = getimagesize($source);
    
        if ($info['mime'] == 'image/jpeg') 
            $image = imagecreatefromjpeg($source);
    
        elseif ($info['mime'] == 'image/gif') 
            $image = imagecreatefromgif($source);
    
        elseif ($info['mime'] == 'image/png') 
            $image = imagecreatefrompng($source);
    
        imagejpeg($image, $destination, $quality);
    
        return $destination;
    }

//    public function compareData(){
//     $dataOLD = M_CrProspect::where('id','55')->first();

//     $dataold = [
//         "visit_date" => $dataOLD->visit_date,
//         "mother_name" => $dataOLD->mother_name,
//         "category" => $dataOLD->category,
//         "tin_number" => $dataOLD->tin_number,
//         "title" => $dataOLD->title,
//         "work_period" => $dataOLD->work_period,
//         "dependants" => $dataOLD->dependants,
//         "income_personal" => strval($dataOLD->income_personal),
//         "income_spouse" => strval($dataOLD->income_spouse),
//         "income_other" => strval($dataOLD->income_other),
//         "expenses" => strval($dataOLD->expenses),
//         "cust_code_ref" => $dataOLD->cust_code_ref,
//         "tujuan_kredit" => $dataOLD->tujuan_kredit,
//         "jenis_produk" => $dataOLD->jenis_produk,
//         "plafond" => strval($dataOLD->plafond),
//         "tenor" => strval($dataOLD->tenor),
//         "nama" => $dataOLD->nama,
//         "ktp" => $dataOLD->ktp,
//         "kk" => $dataOLD->kk,
//         "tgl_lahir" => $dataOLD->tgl_lahir,
//         "alamat" => $dataOLD->alamat,
//         "rt" => $dataOLD->rt,
//         "rw" => $dataOLD->rw,
//         "province" => $dataOLD->province,
//         "city" => $dataOLD->city,
//         "kelurahan" => $dataOLD->kelurahan,
//         "kecamatan" => $dataOLD->kecamatan,
//         "zip_code" => $dataOLD->zip_code,
//         "hp" => $dataOLD->hp,
//         "usaha" => $dataOLD->usaha,
//         "sector" => $dataOLD->sector,
//         "coordinate" => $dataOLD->coordinate,
//         "accurate" => $dataOLD->accurate,
//         "survey_note" => $dataOLD->survey_note,
//         "payment_reference" => $dataOLD->payment_reference
//     ];

//     $datanew=[
//         'visit_date' => isset($request->data_survey['tgl_survey']) && !empty($request->data_survey['tgl_survey'])?$request->data_survey['tgl_survey']:null,
//         'tujuan_kredit' => $request->order['tujuan_kredit']?? null,
//         'plafond' => $request->order['plafond']?? null,
//         'tenor' => $request->order['tenor']?? null,
//         'category' => $request->order['category']?? null,
//         'nama' => $request->data_nasabah['nama']?? null,
//         'tgl_lahir' => $request->data_nasabah['tgl_lahir']?? null,
//         'ktp' => $request->data_nasabah['no_ktp']?? null,
//         'hp' => $request->data_nasabah['no_hp']?? null,
//         'alamat' => $request->data_nasabah['data_alamat']['alamat']?? null,
//         'rt' => $request->data_nasabah['data_alamat']['rt']?? null,
//         'rw' => $request->data_nasabah['data_alamat']['rw']?? null,
//         'province' => $request->data_nasabah['data_alamat']['provinsi']?? null,
//         'city' => $request->data_nasabah['data_alamat']['kota']?? null,
//         'kecamatan' => $request->data_nasabah['data_alamat']['kecamatan']?? null,
//         'kelurahan' => $request->data_nasabah['data_alamat']['kelurahan']?? null,
//         "work_period" => $request->data_survey['lama_bekerja']?? null,
//         "income_personal" => $request->data_survey['penghasilan']['pribadi']?? null,
//         "income_spouse" =>  $request->data_survey['penghasilan']['pasangan']?? null,
//         "income_other" =>  $request->data_survey['penghasilan']['lainnya']?? null,
//         'usaha' => $request->data_survey['usaha']?? null,
//         'sector' => $request->data_survey['sektor']?? null,
//         "expenses" => $request->data_survey['pengeluaran']?? null,
//         'survey_note' => $request->data_survey['catatan_survey']?? null,
//         'coordinate' => $request->lokasi['coordinate']?? null,
//         'accurate' => $request->lokasi['accurate']?? null,
//     ];

//     $differingData = [];

//     foreach ($datanew as $key => $value) {
//         if (array_key_exists($key, $dataold) && $dataold[$key] !== $value) {
//             $differingData[$key] = $value;
//         }
//     }

//     foreach ($differingData as $key => $value) {
//         $dataLog = [
//             'id' =>Uuid::uuid4()->toString(),
//             'table_name' => 'cr_prospect',
//             'table_id' => $dataOLD->id,
//             'field_name' => $key,
//             'old_value' =>$dataOLD->$key,
//             'new_value' => $value,
//             'altered_by' =>  $request->user()->id??0,
//             'altered_time' => Carbon::now()->format('Y-m-d H:i:s')
//         ];

//         M_DeuteronomyTransactionLog::create($dataLog);
//     }
//    }

    public function uploadImage(Request $req)
    {
        try {
            $this->validate($req, [
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg'
            ]);

            $image_path = $req->file('image')->store('public/testing');
            $image_path = str_replace('public/', '', $image_path);

            $url= URL::to('/') . '/storage/' . $image_path;
            
            return response()->json(['message' => 'Image upload successfully',"status" => 200,'response' => $url], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }
}
