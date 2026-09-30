<?php

namespace App\Services\Interface;

interface AuthenticationServiceInterface
{
    public function register(string $username,string $name ,string $password): bool;
    public function loginWithPassword(string $username,string $password): string;
    public function requestOTPForUsername(string $username):bool;
    public function verifyOTP(string $username,string $code): string;
}
