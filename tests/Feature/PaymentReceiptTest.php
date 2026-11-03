<?php

use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentReceiptStatus;
use App\Models\Booking;
use App\Models\BookingPaymentReceipt;
use App\Models\Customer;
use App\Models\EventType;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function receiptOrgFixture(): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $user->organizations()->attach($organization->id, [
        'type' => OrganizationRole::Owner->value,
        'status' => MembershipStatus::Active->value,
    ]);

    $schedule = Schedule::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'timezone' => 'UTC',
        'is_default' => true,
    ]);

    $eventType = EventType::factory()->create([
        'organization_id' => $organization->id,
        'host_user_id' => $user->id,
        'schedule_id' => $schedule->id,
        'requires_payment_receipt' => true,
    ]);

    $customer = Customer::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $booking = Booking::factory()->create([
        'organization_id' => $organization->id,
        'event_type_id' => $eventType->id,
        'host_user_id' => $user->id,
        'customer_id' => $customer->id,
        'status' => BookingStatus::PendingPayment,
    ]);

    $token = 'Bearer '.app(JwtTokenService::class)->signAccessToken(
        $user,
        $organization->id,
        OrganizationRole::Owner,
    );

    return compact('user', 'organization', 'booking', 'token');
}

test('staff can upload payment receipt and download via signed url', function () {
    Storage::fake('local');
    ['organization' => $organization, 'booking' => $booking, 'token' => $token] = receiptOrgFixture();

    $file = UploadedFile::fake()->image('receipt.jpg', 400, 300)->size(200);

    $upload = $this->withHeader('Authorization', $token)
        ->post(
            "/api/v1/organizations/{$organization->id}/bookings/{$booking->id}/payment-receipt",
            ['file' => $file],
        );

    $upload->assertCreated()
        ->assertJsonPath('status', PaymentReceiptStatus::Uploaded->value)
        ->assertJsonPath('originalFilename', 'receipt.jpg')
        ->assertJsonPath('mimeType', 'image/jpeg')
        ->assertJsonStructure(['id', 'downloadUrl', 'sizeBytes']);

    $receiptId = $upload->json('id');
    $downloadUrl = $upload->json('downloadUrl');
    expect($downloadUrl)->toContain('signature=');

    $this->assertDatabaseHas('booking_payment_receipts', [
        'id' => $receiptId,
        'booking_id' => $booking->id,
        'organization_id' => $organization->id,
        'status' => PaymentReceiptStatus::Uploaded->value,
    ]);

    $path = BookingPaymentReceipt::query()->findOrFail($receiptId)->path;
    Storage::disk('local')->assertExists($path);

    $show = $this->withHeader('Authorization', $token)
        ->getJson("/api/v1/organizations/{$organization->id}/bookings/{$booking->id}/payment-receipt");

    $show->assertOk()
        ->assertJsonPath('id', $receiptId)
        ->assertJsonStructure(['downloadUrl']);

    $download = $this->get($downloadUrl);
    $download->assertOk();
});

test('disallowed mime and oversized receipt uploads are rejected', function () {
    Storage::fake('local');
    ['organization' => $organization, 'booking' => $booking, 'token' => $token] = receiptOrgFixture();

    $text = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
    $this->withHeader('Authorization', $token)
        ->post(
            "/api/v1/organizations/{$organization->id}/bookings/{$booking->id}/payment-receipt",
            ['file' => $text],
        )
        ->assertUnprocessable();

    $huge = UploadedFile::fake()->image('huge.jpg')->size(6 * 1024);
    $this->withHeader('Authorization', $token)
        ->post(
            "/api/v1/organizations/{$organization->id}/bookings/{$booking->id}/payment-receipt",
            ['file' => $huge],
        )
        ->assertUnprocessable();
});

test('staff from another organization cannot fetch payment receipt', function () {
    Storage::fake('local');
    ['organization' => $orgA, 'booking' => $bookingA, 'token' => $tokenA] = receiptOrgFixture();

    $file = UploadedFile::fake()->image('receipt.png')->size(100);
    $this->withHeader('Authorization', $tokenA)
        ->post(
            "/api/v1/organizations/{$orgA->id}/bookings/{$bookingA->id}/payment-receipt",
            ['file' => $file],
        )
        ->assertCreated();

    ['organization' => $orgB, 'token' => $tokenB] = receiptOrgFixture();

    $this->withHeader('Authorization', $tokenB)
        ->getJson("/api/v1/organizations/{$orgA->id}/bookings/{$bookingA->id}/payment-receipt")
        ->assertForbidden();

    $this->withHeader('Authorization', $tokenB)
        ->getJson("/api/v1/organizations/{$orgB->id}/bookings/{$bookingA->id}/payment-receipt")
        ->assertNotFound();

    // Unsigned / forged download for org A receipt must fail signature check.
    $forged = url("/api/v1/organizations/{$orgA->id}/bookings/{$bookingA->id}/payment-receipt/".Str::uuid().'/download');
    $this->get($forged)->assertForbidden();
});
