<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
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

            $credentials = $request->only('username', 'password');

            if (!Auth::attempt($credentials)) {
                $this->logLoginActivity($request, 'Invalid Credential', 401);
                return response()->json(['message' => 'Invalid Credential', 'status' => 401], 401);
            }

            $user = $request->user();

            if (strtolower($user->status) !== 'active') {
                $this->logLoginActivity($request, 'User status is not active', 401);
                return response()->json(['message' => 'Invalid Credential'], 401);
            }

            $menu = M_MasterUserAccessMenu::where(['users_id'=>$user->id])->first();

            if (!$menu) {
                $this->logLoginActivity($request, 'Menu Not Found', 401);
                return response()->json(['message' => 'Invalid Credential'], 401);
            }

            $token = $this->generateToken($user);

            $this->logLoginActivity($request, 'Success', 200);
            return response()->json(['token' => $token], 200);

        } catch (\Exception $e) {
            $this->logLoginActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    private function generateToken(User $user)
    {
        // $user->tokens()->delete();
        return $user->createToken($user->id)->plainTextToken;
    }

    private function logLoginActivity(Request $request, string $message, int $statusCode)
    {
        ActivityLogger::logActivityLogin($request, $this->login, $message, $statusCode);
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
