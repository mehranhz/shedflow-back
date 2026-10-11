<?php

namespace App\Listeners;

use App\DTOs\ContactDTO;
use App\Events\JoinToOrganizationInvitationCreated;
use App\Notifications\JoinToOrganizationInvitation;

class SendJoinToOrganizationInvitationSms
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JoinToOrganizationInvitationCreated $event): void
    {
        logger("inside handler");
        $contact = new ContactDTO($event->invitation->phone);
        $contact->notify(new JoinToOrganizationInvitation($event->invitation));
    }
}
