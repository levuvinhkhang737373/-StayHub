<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function responseJson($status = null, $message = "", $errorCode = null, $data = null, $httpCode = null): JsonResponse
    {
        return response()->json([
            'status'    => $status,
            'errorCode' => $errorCode,
            'message'   => $message,
            'result'    => $data,
        ], $httpCode ?? 200);
    }
}
