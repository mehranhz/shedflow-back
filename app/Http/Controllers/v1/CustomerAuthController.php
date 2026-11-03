<?php

namespace App\Http\Controllers\v1;

use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\APIController;
use App\Http\Requests\v1\CustomerClaimConfirmRequest;
use App\Http\Requests\v1\CustomerClaimRequest;
use App\Http\Requests\v1\CustomerLoginRequest;
use App\Http\Requests\v1\CustomerRegisterRequest;
use App\Services\CustomerAuthService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAuthController extends APIController
{
    public function __construct(protected CustomerAuthService $customerAuth) {}

    public function register(CustomerRegisterRequest $request)
    {
        try {
            $session = $this->customerAuth->register($request->validated(), $this->meta($request));

            return $this->success(data: $session, status: 201);
        } catch (HttpException $exception) {
            return $this->error(message: $exception->getMessage(), status: $exception->getStatusCode());
        }
    }

    public function login(CustomerLoginRequest $request)
    {
        try {
            $session = $this->customerAuth->loginWithPassword(
                $request->input('email'),
                $request->input('password'),
                $this->meta($request),
            );

            return $this->success(data: $session);
        } catch (InvalidCredentialsException) {
            return $this->error(message: 'Invalid credentials', status: 401);
        } catch (HttpException $exception) {
            return $this->error(message: $exception->getMessage(), status: $exception->getStatusCode());
        }
    }

    public function claim(CustomerClaimRequest $request)
    {
        $this->customerAuth->requestClaim($request->input('email'));

        return response()->noContent();
    }

    public function confirmClaim(CustomerClaimConfirmRequest $request)
    {
        try {
            $session = $this->customerAuth->confirmClaim(
                $request->input('token'),
                $this->meta($request),
            );

            return $this->success(data: $session);
        } catch (HttpException $exception) {
            return $this->error(message: $exception->getMessage(), status: $exception->getStatusCode());
        }
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
