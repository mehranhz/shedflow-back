<?php

namespace App\Listeners;

use App\Entities\Contact;
use App\Events\JoinToOrganizationInvitationCreated;
use App\Notifications\JoinToOrganizationInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        $contact = new Contact($event->invitation->phone);
        $contact->notify(new JoinToOrganizationInvitation($event->invitation));
    }
}
