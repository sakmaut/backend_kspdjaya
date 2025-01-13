<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    private $login = "Login";
    private $logout = "Logout";

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255',
                'password' => 'required|string',
                'device_info' => 'required|string|max:500'
            ], [
                'username.required' => 'Username is required',
                'password.required' => 'Password is required',
                'device_info.required' => 'Device information is required'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            
            $credentials = $request->only('username', 'password');

            if (!Auth::attempt($credentials)) {
                $this->logLoginActivity($request, 'Invalid Credential', 401);
                throw new AuthenticationException('Invalid credentials');
            }

            $user = $request->user();

            if (strtolower($user->status) !== 'active') {
                $this->logLoginActivity($request, 'User status is not active', 401);
                throw new AuthenticationException('Invalid credentials');
            }

            $menu = M_MasterUserAccessMenu::where(['users_id'=>$user->id])->first();

            if (!$menu) {
                $this->logLoginActivity($request, 'Menu Not Found', 401);
                throw new AuthenticationException('Invalid credentials');
            }

            $token = $this->generateToken($user);

            $this->logLoginActivity($request, 'Success '.$token, 200);
            return response()->json(['token' => $token], 200);

        } catch (\Exception $e) {
            $this->logLoginActivity($request, $e->getMessage(), 500);
            return response()->json([
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    private function generateToken(User $user)
    {
        $user->tokens()->delete();
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
