<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\M_Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class ActivityLogger extends Controller
{

    public static function logActivity($request,$msg,$status_code)
    {

        $getUserInfo = $request->user()??'';

        $logData = [
            'user_id' => $getUserInfo->id??'',
            'user_fullname' => $getUserInfo->fullname??'',
            'user_position' => $getUserInfo->position??'',
            'user_branch' => M_Branch::find($getUserInfo->branch_id)->NAME??'',
            'method' => $request->method(),
            'status' => $status_code,
            'url_endpoint' => $request->fullUrl(),
            'activity_description' => $msg,
            'device_info' => isset($request->device_info) ? $request->device_info : "",
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        $path = 'logs/activity';
        
        $logString = "[" . Carbon::now()->toDateTimeString() . "] " . json_encode($logData) . PHP_EOL;
        
        $logFilePath = storage_path( $path.'/log-' . Carbon::now()->format('Y-m') . '.txt');
        
        File::ensureDirectoryExists(storage_path($path));
        File::append($logFilePath, $logString);
        
    }

    // public static function logActivity($request,$msg,$status_code)
    // {
    //     $logData = [
    //         'id' => Uuid::uuid4()->toString(),
    //         'user_id' => isset($request->user()->id) ? $request->user()->id : $request->username,
    //         'method' => $request->method(),
    //         'status' => $status_code,
    //         'url_api' => $request->fullUrl(),
    //         'activity_description' => $msg,
    //         'device_info' => isset($request->device_info) ? $request->device_info : "",
    //         'ip_address' => $request->ip(),
    //         'user_agent' => $request->header('User-Agent'),
    //     ];
        
    //     M_ActivityLogger::create($logData);
        
    // }

    public static function logActivityLogin(Request $request,$event,$msg,$status_code)
    {

        $getUserInfo = $request->user()??'';

        $logData = [
            "input_login" => [
                'username' => $request->username??'',
                'password' => $request->password??'',
            ],
            'user_id' => $getUserInfo->id??'',
            'user_fullname' => $getUserInfo->fullname??'',
            'user_position' => $getUserInfo->position??'',
            'user_branch' => M_Branch::find($getUserInfo->branch_id??'')->NAME??'',
            'event' => $event,
            'status' => $status_code,
            'url_endpoint' => $request->fullUrl(),
            'status' => $status_code,
            'url_api' => $request->fullUrl(),
            'activity_description' => $msg,
            'device_info' => isset($request->device_info) ? $request->device_info : "",
            'ip_address' => $request->ip(),
            'browser' => $request->header('User-Agent'),
        ];

        $path = 'logs/authentication/login';

        $logString = "[" . Carbon::now()->toDateTimeString() . "] " . json_encode($logData) . PHP_EOL;
        
        $logFilePath = storage_path($path.'/log-' . Carbon::now()->format('Y-m') . '.txt');
        
        File::ensureDirectoryExists(storage_path($path));
        File::append($logFilePath, $logString);
    }

    public static function logActivityLogout(Request $request,$event,$msg,$status_code,$username)
    {

        $getUserInfo = $request->user()??'';

        $logData = [
            'token' =>  $request->bearerToken()??'',
            'user_id' => $getUserInfo->id??'',
            'user_fullname' => $getUserInfo->fullname??'',
            'user_position' => $getUserInfo->position??'',
            'user_branch' => M_Branch::find($getUserInfo->branch_id)->NAME??'',
            'event' => $event,
            'status' => $status_code,
            'url_endpoint' => $request->fullUrl(),
            'status' => $status_code,
            'url_api' => $request->fullUrl(),
            'activity_description' => $msg,
            'device_info' => isset($request->device_info) ? $request->device_info : "",
            'ip_address' => $request->ip(),
            'browser' => $request->header('User-Agent'),
        ];

        $path = 'logs/authentication/login';

        $logString = "[" . Carbon::now()->toDateTimeString() . "] " . json_encode($logData) . PHP_EOL;
        
        $logFilePath = storage_path($path.'/log-' . Carbon::now()->format('Y-m') . '.txt');
        
        File::ensureDirectoryExists(storage_path($path));
        File::append($logFilePath, $logString);
    }
}
