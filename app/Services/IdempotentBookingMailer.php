<?php

namespace App\Services;

use App\Enums\EmailTemplate;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Mail\BookingTemplateMail;
use App\Models\NotificationLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IdempotentBookingMailer
{
    /**
     * @param  array{filename:string,content:string,contentType:string}|null  $icsAttachment
     */
    public function deliver(
        ?string $organizationId,
        ?string $bookingId,
        EmailTemplate $template,
        string $to,
        ?string $replyTo,
        string $subject,
        string $html,
        string $text,
        string $idempotencyKey,
        ?array $icsAttachment = null,
    ): void {
        $to = strtolower(trim($to));
        if ($to === '') {
            return;
        }

        if ($bookingId && $this->alreadySent($bookingId, $template)) {
            return;
        }

        try {
            $providerId = $this->sendMail($to, $replyTo, $subject, $html, $text, $idempotencyKey, $icsAttachment);
            $this->insertLog(
                organizationId: $organizationId,
                bookingId: $bookingId,
                template: $template,
                to: $to,
                status: NotificationStatus::Sent,
                providerId: $providerId,
                sentAt: now(),
            );
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                Log::debug("notification_logs unique hit template={$template->value} booking={$bookingId}");

                return;
            }

            throw $exception;
        }
    }

    private function alreadySent(string $bookingId, EmailTemplate $template): bool
    {
        return NotificationLog::query()
            ->where('booking_id', $bookingId)
            ->where('template', $template->value)
            ->where('channel', NotificationChannel::Email->value)
            ->where('status', NotificationStatus::Sent->value)
            ->exists();
    }

    /**
     * @param  array{filename:string,content:string,contentType:string}|null  $icsAttachment
     */
    private function sendMail(
        string $to,
        ?string $replyTo,
        string $subject,
        string $html,
        string $text,
        string $idempotencyKey,
        ?array $icsAttachment,
    ): ?string {
        $mailable = new BookingTemplateMail(
            subjectLine: $subject,
            htmlBody: $html,
            textBody: $text,
            replyToAddress: $replyTo,
            idempotencyKey: $idempotencyKey,
            icsAttachment: $icsAttachment,
        );

        Mail::to($to)->send($mailable);

        if (config('mail.default') === 'log' || config('mail.default') === 'array') {
            return 'log:'.$idempotencyKey;
        }

        return null;
    }

    private function insertLog(
        ?string $organizationId,
        ?string $bookingId,
        EmailTemplate $template,
        string $to,
        NotificationStatus $status,
        ?string $providerId = null,
        ?string $error = null,
        ?\DateTimeInterface $sentAt = null,
    ): void {
        try {
            NotificationLog::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'booking_id' => $bookingId,
                'channel' => NotificationChannel::Email,
                'template' => $template->value,
                'to_address' => $to,
                'provider_id' => $providerId,
                'status' => $status,
                'error' => $error,
                'sent_at' => $sentAt,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                Log::debug("notification_logs unique hit template={$template->value} booking={$bookingId}");

                return;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) ($exception->errorInfo[0] ?? '');
        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique');
    }
}
