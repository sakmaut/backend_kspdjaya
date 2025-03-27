<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\M_MasterUserAccessMenu;
use App\Models\User;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    private $login = "Login";
    private $logout = "Logout";
    protected $log;

    public function __construct(ExceptionHandling $log)
    {
        $this->log = $log;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|regex:/^[a-zA-Z0-9]*$/',
                'password' => 'required|string|regex:/^[a-zA-Z0-9]*$/'
            ]);

            $credentials = $request->only('username', 'password');

            if (!Auth::attempt($credentials)) {
                throw new ValidationException('Validation failed', 401);
            }

            $user = $request->user();

            if (strtolower($user->status) !== 'active') {
                throw new ValidationException('Validation failed', 401);
            }

            $token = $this->generateToken($user);

            return response()->json(['token' => $token], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 401);
        }
    }

    private function generateToken(User $user)
    {
        $user->tokens()->delete();
        $token = $user->createToken($user->id)->plainTextToken;
        $tokenInstance = $user->tokens->last();
        $tokenInstance->update(['expires_at' => now()->startOfDay()]);
        return $token;
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->tokens()->delete();
                ActivityLogger::logActivityLogout($request, $this->logout, "Success", 200, $user->username);
                return response()->json([
                    'message' => 'Logout successful',
                    'status' => 200,
                    "response" => [
                        "token_deleted" => $request->bearerToken()
                    ]
                ], 200);
            } else {
                ActivityLogger::logActivityLogout($request, $this->logout, "Token not defined", 404, $user->username);
                return response()->json([
                    'message' => 'Token not defined',
                    'status' => 404
                ], 404);
            }
        } catch (\Exception $e) {
            ActivityLogger::logActivityLogout($request, $this->logout, $e, 500, $user->username);
            return response()->json([
                'message' => 'An error occurred',
                'status' => 500
            ], 500);
        }
    }

    public function tokenCheckValidation(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->tokens()->delete();
                return response()->json([
                    'message' => 'Logout successful',
                    'status' => 200,
                    "response" => [
                        "token_deleted" => $request->bearerToken()
                    ]
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Token not defined',
                    'status' => 404
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'status' => 500
            ], 500);
        }
    }
}
