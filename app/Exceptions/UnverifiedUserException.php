<?php
namespace App\Exceptions;

use \Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnverifiedUserException extends Exception{

    protected $message = "Unverified Username";
    public static function forPhone(): self
    {
        return new self("Phone number is not verified");
    }

    public static function forEmail(): self{
        return new self("Email address is not verified");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            "success" => false,
        ],400);
    }
}
