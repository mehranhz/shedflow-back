<?php

namespace App\Enums;

enum PaymentReceiptStatus: string
{
    case Uploaded = 'UPLOADED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
