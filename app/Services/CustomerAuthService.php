<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\MembershipStatus;
use App\Exceptions\InvalidCredentialsException;
use App\Mail\CustomerClaimMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Models\CustomerClaimToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAuthService
{
    private const CLAIM_TOKEN_TTL_HOURS = 24;

    public function __construct(
        protected JwtTokenService $jwt,
        protected CustomerLinkService $customerLinks,
    ) {}

    /**
     * @param  array{email:string,password:string,name?:?string,timezone?:?string,locale?:?string}  $input
     * @param  array{userAgent:?string,ip:?string}  $meta
     * @return array<string, mixed>
     */
    public function register(array $input, array $meta = []): array
    {
        $email = $this->normalizeEmail($input['email']);
        $password = (string) $input['password'];

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw new HttpException(409, 'Email is already registered');
        }

        return DB::transaction(function () use ($email, $password, $input, $meta) {
            $user = User::query()->create([
                'email' => $email,
                'phone' => $this->placeholderPhoneForEmail($email),
                'name' => isset($input['name']) ? trim((string) $input['name']) : null,
                'password' => $password,
                'timezone' => (string) ($input['timezone'] ?? 'UTC'),
                'locale' => (string) ($input['locale'] ?? 'en'),
                'email_verified_at' => now(),
            ]);

            $this->customerLinks->linkCustomersByEmail($user, $email, $user);

            Mail::to($email)->send(new CustomerWelcomeMail($user));

            return $this->customerSessionPayload($user, $meta);
        });
    }

    /**
     * @param  array{userAgent:?string,ip:?string}  $meta
     * @return array<string, mixed>
     */
    public function loginWithPassword(string $email, string $password, array $meta = []): array
    {
        $normalized = $this->normalizeEmail($email);
        $user = User::query()->whereRaw('LOWER(email) = ?', [$normalized])->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->email_verified_at === null) {
            throw new HttpException(403, 'Email is not verified');
        }

        if ($this->jwt->resolveActorType($user) === ActorType::Staff
            && ! $user->customers()->exists()) {
            throw new InvalidCredentialsException;
        }

        return $this->customerSessionPayload($user, $meta);
    }

    public function requestClaim(string $email): void
    {
        $normalized = $this->normalizeEmail($email);

        $hasCustomers = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->exists();

        if (! $hasCustomers) {
            return;
        }

        $raw = Str::random(48);
        CustomerClaimToken::query()->create([
            'email' => $normalized,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addHours(self::CLAIM_TOKEN_TTL_HOURS),
            'created_at' => now(),
        ]);

        $claimUrl = rtrim((string) config('services.frontend.url'), '/')
            .'/portal/claim?token='.urlencode($raw);

        Mail::to($normalized)->send(new CustomerClaimMail($claimUrl));
    }

    /**
     * @param  array{userAgent:?string,ip:?string}  $meta
     * @return array<string, mixed>
     */
    public function confirmClaim(string $token, array $meta = []): array
    {
        if ($token === '') {
            throw new HttpException(401, 'Invalid or expired claim token');
        }

        $hash = hash('sha256', $token);
        $stored = CustomerClaimToken::query()->where('token_hash', $hash)->first();

        if (! $stored || $stored->expires_at->isPast() || $stored->used_at !== null) {
            throw new HttpException(401, 'Invalid or expired claim token');
        }

        return DB::transaction(function () use ($stored, $meta) {
            $stored->update(['used_at' => now()]);

            $email = $stored->email;
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user) {
                $user = User::query()->create([
                    'email' => $email,
                    'phone' => $this->placeholderPhoneForEmail($email),
                    'email_verified_at' => now(),
                    'timezone' => 'UTC',
                    'locale' => 'en',
                ]);
            } elseif ($user->email_verified_at === null) {
                $user->update(['email_verified_at' => now()]);
            }

            $this->customerLinks->linkCustomersByEmail($user, $email, $user);

            return $this->customerSessionPayload($user->fresh(), $meta);
        });
    }

    /**
     * @param  array{userAgent:?string,ip:?string}  $meta
     * @return array<string, mixed>
     */
    private function customerSessionPayload(User $user, array $meta): array
    {
        $session = $this->jwt->issueSession($user, meta: $meta, forceActorType: ActorType::Customer);
        $session['businesses'] = $this->linkedBusinesses($user);

        return $session;
    }

    /**
     * @return list<array{organizationId:string,slug:string,name:string,customerId:string}>
     */
    public function linkedBusinesses(User $user): array
    {
        return Customer::query()
            ->where('user_id', $user->id)
            ->with('organization:id,slug,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Customer $customer) => [
                'organizationId' => $customer->organization_id,
                'slug' => (string) $customer->organization?->slug,
                'name' => (string) $customer->organization?->name,
                'customerId' => $customer->id,
            ])
            ->values()
            ->all();
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function placeholderPhoneForEmail(string $email): string
    {
        return 'c-'.substr(hash('sha256', $this->normalizeEmail($email)), 0, 20);
    }
}
