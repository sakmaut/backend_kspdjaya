<?php

namespace App\Http\Controllers\Component;

use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class ExceptionHandling
{

    function logError($e, $request)
    {
        $errorUuid = Uuid::uuid7()->toString();

        Log::error($errorUuid, [
            'exception' => $e->getMessage(),
            'url' => $request->fullUrl(),
            'user' => $request->user()->username,
            'fullname' => $request->user()->fullname,
            'position' => $request->user()->position
        ]);

        return response()->json(['message' => $e->getMessage()], 500);

        // return response()->json(['message' => "Internal Server Error: $errorUuid"], 500);
    }
}
