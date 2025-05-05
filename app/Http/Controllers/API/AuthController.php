<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Component\ExceptionHandling;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

            $messageError = 'Validation failed';

            $credentials = $request->only('username', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json(['error' => $messageError], 401);
            }

            $user = $request->user();

            if (strtolower($user->status) !== 'active') {
                return response()->json(['error' => $messageError], 401);
            }

            $token = $this->generateToken($user);

            return response()->json(['token' => $token], 200);
        } catch (ValidationException $e) {
            return $this->log->logError($e, $request);
        }
    }

    private function generateToken(User $user)
    {
        $user->tokens()->delete();
        $token = $user->createToken($user->id)->plainTextToken;
        $tokenInstance = $user->tokens()->latest()->first();
        $tokenInstance->update(['expires_at' => now()->addDay()]);
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
            return $this->log->logError($e, $request);
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
