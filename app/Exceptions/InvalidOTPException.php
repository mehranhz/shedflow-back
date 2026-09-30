<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOTPException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): void
    {
        //
    }

    public static function forInvalidCode($code):InvalidOTPException
    {
        return new self("Code is invalid: ".$code);
    }

    public static function forExpiredCode($code):InvalidOTPException
    {
        return new self("Code is expired: ".$code);
    }

    public static function forUsedCode($code): InvalidOTPException
    {
        return new self("Code is already used: ".$code);
    }

    public static function forAlreadySentCode(): InvalidOTPException
    {
        throw new self("Code is already sent");
    }
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
