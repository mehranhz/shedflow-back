<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{

    protected function ok(?string $message=null): JsonResponse
    {
        return $this->success(message: $message,status:  200);
    }
    protected function success(?array $data =null,?string $message=null, ?int $status = 200): JsonResponse
    {
        $response = [
            "success"=>true,
            "status"=>$status,
        ];
        if($data !== null){
            $response['data'] = $data;
        }
        if($message !== null){
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    protected function error(?string $message=null, ?int $status= 500): JsonResponse
    {
        $response = [
            "success"=>false,
            "status"=>$status,
            ...($message!=null ? ["message"=>$message] : []),
        ];

        if($status == 500 && $message == null){
            $response['message'] = "Internal Server Error!";
        }
        return response()->json($response, $status);
    }
}
