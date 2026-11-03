<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\APIController;
use App\Http\Requests\v1\LoginWithOTPRequest;
use App\Http\Requests\v1\LoginWithPhoneAndPasswordRequest;
use App\Http\Requests\v1\OTPVerifyRequest;
use App\Http\Requests\v1\RegisterRequest;
use App\Services\Interface\AuthenticationServiceInterface;
use Illuminate\Http\Request;

class AuthController extends APIController
{
    public function __construct(protected AuthenticationServiceInterface $authenticationService) {}

    public function loginWithPassword(LoginWithPhoneAndPasswordRequest $request)
    {
        try {
            $session = $this->authenticationService->loginWithPassword(
                $request->input('username'),
                $request->input('password'),
                $this->meta($request),
            );

            return $this->success(data: $session);
        } catch (\Exception $exception) {
            return $this->error(message: $exception->getMessage(), status: 401);
        }
    }

    public function loginWithOTP(LoginWithOTPRequest $request)
    {
        try {
            $this->authenticationService->requestOTPForUsername($request->input('username'));

            return $this->ok(message: 'OTP sent');
        } catch (\Exception $exception) {
            return $this->error(message: $exception->getMessage(), status: 400);
        }
    }

    public function register(RegisterRequest $request)
    {
        try {
            $session = $this->authenticationService->register($request->validated(), $this->meta($request));

            return $this->success(data: $session, status: 201);
        } catch (\Exception $exception) {
            return $this->error(message: $exception->getMessage(), status: 400);
        }
    }

    public function verifyOTPForUsername(OTPVerifyRequest $request)
    {
        try {
            $session = $this->authenticationService->verifyOTP(
                $request->input('username'),
                $request->input('code'),
                $this->meta($request),
            );

            return $this->success(data: $session);
        } catch (\Exception $exception) {
            return $this->error(message: $exception->getMessage(), status: 400);
        }
    }

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'refreshToken' => ['required', 'string'],
            'organizationId' => ['nullable', 'uuid'],
        ]);

        $tokens = $this->authenticationService->refresh(
            $data['refreshToken'],
            $data['organizationId'] ?? null,
            $this->meta($request),
        );

        // Nest-compatible raw body expected by apps/client/lib/refresh-session.ts
        return response()->json($tokens);
    }

    public function logout(Request $request)
    {
        $this->authenticationService->logout(
            $request->user(),
            $request->input('refreshToken') ?? $request->header('x-refresh-token'),
        );

        return response()->noContent();
    }

    /**
     * @return array{userAgent:?string,ip:?string}
     */
    private function meta(Request $request): array
    {
        return [
            'userAgent' => $request->userAgent(),
            'ip' => $request->ip(),
        ];
    }
}
