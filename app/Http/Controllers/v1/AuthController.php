<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Http\Requests\v1\LoginWithOTPRequest;
use App\Http\Requests\v1\LoginWithPhoneAndPasswordRequest;
use App\Http\Requests\v1\OTPVerifyRequest;
use App\Http\Requests\v1\RegisterRequest;
use App\Services\Interface\AuthenticationServiceInterface;


class AuthController extends APIController
{
    public function __construct(protected AuthenticationServiceInterface $authenticationService)
    {

    }

    public function loginWithPassword(LoginWithPhoneAndPasswordRequest $request)
    {
        logger("login with phone and password");
        try{
            $token = $this->authenticationService->loginWithPassword($request->input('username'),$request->input('password'));
            logger($token);
            return $this->success(
                data: [
                    "user" => [
                        "id" => "1",
                        "email" => "mail@mail.com"
                    ],
                    "accessToken"=> $token,
                    "refreshToken" => $token
                ]
            );

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
