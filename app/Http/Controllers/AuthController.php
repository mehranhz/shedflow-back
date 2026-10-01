<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginWithOTPRequest;
use App\Http\Requests\LoginWithPhoneAndPasswordRequest;
use App\Http\Requests\OTPVerifyRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\Interface\AuthenticationServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthenticationServiceInterface $authenticationService)
    {

    }

    public function loginWithPassword(LoginWithPhoneAndPasswordRequest $request)
    {
        try{
            $token = $this->authenticationService->loginWithPassword($request->input('username'),$request->input('password'));
            return response()->json([
                "success"=> true,
                "token"=> $token,
            ],200);
        }catch (\Exception $exception){
            return response()->json([
                "success"=>false,
                "message"=>$exception->getMessage()
            ],401);
        }
    }

    public function loginWithOTP(LoginWithOTPRequest $request)
    {
        try {
            $this->authenticationService->requestOTPForUsername($request->input('username'));
        }catch (\Exception $exception){
            return response()->json([
                "success" => false,
                "message" => $exception->getMessage()
            ],400);
        }
    }
    public function register(RegisterRequest $request)
    {

        try{
            $this->authenticationService->register($request->input('username'),$request->input('name'),$request->input('password'));
        }catch (\Exception $exception){

            return response()->json([
                "success"=>false,
                "message"=>$exception->getMessage(),
                "code"=>$exception->getCode()
            ],400);
        }

        return response()->json([
            "success" => true,
            "message" => "Registration successful, verify your username to login",
        ],201);
    }

    public function verifyOTPForUsername(OTPVerifyRequest $request){
        try{
            $token = $this->authenticationService->verifyOTP($request->input('username'),$request->input('code'));
            return response()->json([
                "success"=>true,
                "token"=>$token,
            ],200);
        }catch (\Exception $exception){
            return response()->json([
                "success"=>false,
                "message"=>$exception->getMessage(),
            ],400);
        }
    }
}
