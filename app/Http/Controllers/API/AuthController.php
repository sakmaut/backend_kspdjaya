<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    private $login = "Login";
    private $logout = "Logout";

    public function _validate($request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
            'device_info' => 'required'
        ]);

        return $validator;
    }

    public function login(Request $request)
    {
        try {
            $this->_validate($request);

            $message = 'Invalid Credential';
    
            $credentials = $request->only('username', 'password');

            if (Auth::attempt($credentials)) {

                $user = $request->user();

                if ($user->status == 'Active') {

                    $user->tokens()->delete();

                    $token = $user->createToken($request->user()->id)->plainTextToken;
        
                    ActivityLogger::logActivityLogin($request,$this->login,"Success",200);
                    return response()->json([
                        'message' => true,
                        'status' => 200,
                        'response' => [
                            'token' => $token
                        ],
                    ], 200);

                } else {
                    ActivityLogger::logActivityLogin($request,$this->login, 'User status is not active'.' ( user = '.$request->username. ' & pass = '.$request->password.')', 403);
                    return response()->json([
                        'message' => 'User status is not active',
                        "status" => 403
                    ], 403);
                }
            }
    
            ActivityLogger::logActivityLogin($request,$this->login,$message.' ( user = '.$request->username. ' & pass = '.$request->password.')',401);

            return response()->json([
                'message' => $message,
                "status" => 401
            ], 401);

        } catch (\Exception $e) {
            ActivityLogger::logActivityLogin($request,$this->login,$e,500);
            return response()->json([
                'message' => 'An error occurred',
                'status' => 500
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->tokens()->delete();
                ActivityLogger::logActivityLogout($request, $this->logout, "Success", 200,$user->username);
                return response()->json([
                    'message' => 'Logout successful',
                    'status' => 200,
                    "response" => [
                        "token_deleted" => $request->bearerToken()
                    ]
                ], 200);
            } else {
                ActivityLogger::logActivityLogout($request, $this->logout, "Token not defined", 404,$user->username);
                return response()->json([
                    'message' => 'Token not defined',
                    'status' => 404
                ], 404);
            }
    
        } catch (\Exception $e) {
            ActivityLogger::logActivityLogout($request,$this->logout,$e,500,$user->username);
            return response()->json([
                'message' => 'An error occurred',
                'status' => 500
            ], 500);
        }
    }
}
