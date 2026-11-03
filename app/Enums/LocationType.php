<?php

namespace App\Enums;

enum LocationType: string
{
    case GoogleMeet = 'GOOGLE_MEET';
    case Link = 'LINK';
    case Phone = 'PHONE';
    case InPerson = 'IN_PERSON';
    case Custom = 'CUSTOM';
}
