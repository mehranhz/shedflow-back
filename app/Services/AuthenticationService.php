<?php

namespace App\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidOTPException;
use App\Exceptions\UnverifiedUserException;
use App\Models\OTP;
use App\Models\User;
use App\Services\Interface\AuthenticationServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticationService implements AuthenticationServiceInterface
{
    protected int $OTPLifetimeInMinutes = 5;
    public function register(string $username, string $name,string $password): bool
    {
        $user = User::where("phone",$username)->first();
        if($user){
            throw new \Exception("User already exists");
        }
        $hashedPassword = Hash::make($password);
        User::create([
            "phone"=>$username,
            "name"=>$name,
            "password"=>$hashedPassword
        ]);
        $this->requestOTPForUsername($username);
        return true;
    }
    public function requestOTPForUsername(string $username):bool{
        $previousCode = OTP::where("username",$username)->where("created_at", ">=",Carbon::now()->subMinutes($this
        ->OTPLifetimeInMinutes))->first();
        if($previousCode){
            throw InvalidOTPException::forAlreadySentCode();
        }
        $code = random_int(100000,999999);
        OTP::create([
            "username" => $username,
            "code" => $code,
            "expires_at"=>Carbon::now()->addMinutes($this->OTPLifetimeInMinutes),
        ]);
        logger("Otp has been sent to $username: $code");
        return true;
    }
    public function loginWithPassword(string $username, string $password): string{
        $user = User::where("phone",$username)->firstOrFail();

        if(!Hash::check($password, $user->password)){
            throw new InvalidCredentialsException();
        }
        if ($user->phone_verified_at == null) {
            throw UnverifiedUserException::forPhone();
        }

        return $this->getToken($user);
    }

    protected function getToken(User $user): string
    {
        return $user->createToken("accessToken")->plainTextToken;
    }

    public function verifyOTP($username,$code): string
    {
        $user = User::where("phone",$username)->first();
        if(!$user){
            throw new InvalidCredentialsException();
        }
        $storedCode = OTP::where("username",$username)->where("code",$code)->first();
        if(!$storedCode){
            throw InvalidOTPException::forInvalidCode($code);
        }
        if (Carbon::now() >  $storedCode->expires_at ) {
            throw InvalidOTPException::forExpiredCode($code);
        }
        if($storedCode->verified_at != null){
            throw InvalidOTPException::forUsedCode($code);
        }
        if ($user->verified_at == null) {
            $user->update([
                "verified_at"=>Carbon::now()
            ]);
        }

        $storedCode->update([
            "verified_at" => Carbon::now()
        ]);
        if($user->phone_verified_at == null){
            $user->update([
                "phone_verified_at"=>Carbon::now()
            ]);
        }
        return $this->getToken($user);
    }
}
