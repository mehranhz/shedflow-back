<?php
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCredentialsException extends Exception{
    protected $message = "Invalid Credentials";
    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return  response()->json([
            "success"=>false,
            "message"=>$this->getMessage(),
        ]);
    }
}
