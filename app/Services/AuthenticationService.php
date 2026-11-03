<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidOTPException;
use App\Exceptions\UnverifiedUserException;
use App\Models\Organization;
use App\Models\OTP;
use App\Models\User;
use App\Services\Interface\AuthenticationServiceInterface;
use App\Services\Interface\ScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthenticationService implements AuthenticationServiceInterface
{
    protected int $OTPLifetimeInMinutes = 5;

    public function __construct(
        protected JwtTokenService $jwt,
        protected ScheduleServiceInterface $schedules,
    ) {}

    public function register(array $input, array $meta = []): array
    {
        $phone = trim((string) ($input['username'] ?? ''));
        $password = (string) $input['password'];
        $name = isset($input['name']) ? trim((string) $input['name']) : null;
        $timezone = (string) ($input['timezone'] ?? 'UTC');

        if ($phone === '') {
            throw new \Exception('Phone number is required', 400);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            throw new \Exception('Phone is already registered', 409);
        }

        return DB::transaction(function () use ($phone, $password, $name, $timezone, $input, $meta) {
            $user = User::query()->create([
                'phone' => $phone,
                'name' => $name,
                'password' => $password,
                'timezone' => $timezone,
                'phone_verified_at' => now(),
            ]);

            $orgName = trim((string) ($input['organizationName'] ?? ''));
            if ($orgName === '') {
                $orgName = ($name ?: $phone)."'s workspace";
            }

            $organization = Organization::query()->create([
                'name' => $orgName,
                'slug' => $this->uniqueSlug($orgName),
                'timezone' => $timezone,
                'locale' => 'en',
                'currency' => 'USD',
                'platform_plan' => 'FREE',
                'settings' => [],
            ]);

            $user->organizations()->attach($organization->id, [
                'type' => OrganizationRole::Owner->value,
                'status' => MembershipStatus::Active->value,
            ]);

            $this->schedules->createDefaultSchedule($user, $organization->id);

            return $this->jwt->issueSession(
                $user,
                $organization->id,
                OrganizationRole::Owner,
                $meta,
            );
        });
    }

    public function loginWithPassword(string $username, string $password, array $meta = []): array
    {
        $phone = trim($username);
        $user = User::query()->where('phone', $phone)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->phone_verified_at === null) {
            throw UnverifiedUserException::forPhone();
        }

        return $this->jwt->issueSession($user, meta: $meta);
    }

    public function refresh(string $refreshToken, ?string $organizationId = null, array $meta = []): array
    {
        return $this->jwt->rotateRefreshToken($refreshToken, $organizationId, $meta);
    }

    public function logout(User $user, ?string $refreshToken = null): void
    {
        $this->jwt->revokeRefreshToken($refreshToken, $user->id);
    }

    public function requestOTPForUsername(string $username): bool
    {
        $previousCode = OTP::query()
            ->where('username', $username)
            ->where('created_at', '>=', Carbon::now()->subMinutes($this->OTPLifetimeInMinutes))
            ->first();
        if ($previousCode) {
            throw InvalidOTPException::forAlreadySentCode();
        }
        $code = random_int(100000, 999999);
        OTP::query()->create([
            'username' => $username,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes($this->OTPLifetimeInMinutes),
        ]);
        logger("Otp has been sent to $username: $code");

        return true;
    }

    public function verifyOTP(string $username, string $code, array $meta = []): array
    {
        $storedCode = OTP::query()->where('username', $username)->where('code', $code)->first();
        if (! $storedCode) {
            throw InvalidOTPException::forInvalidCode($code);
        }
        if (Carbon::now() > $storedCode->expires_at) {
            throw InvalidOTPException::forExpiredCode($code);
        }
        if ($storedCode->verified_at != null) {
            throw InvalidOTPException::forUsedCode($code);
        }

        $phone = trim($username);

        return DB::transaction(function () use ($storedCode, $phone, $meta) {
            $storedCode->update(['verified_at' => Carbon::now()]);

            $user = User::query()->where('phone', $phone)->first();
            if (! $user) {
                $user = User::query()->create([
                    'phone' => $phone,
                    'phone_verified_at' => Carbon::now(),
                ]);
            } else {
                $user->update(['phone_verified_at' => Carbon::now()]);
            }

            return $this->jwt->issueSession($user->fresh(), meta: $meta);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
