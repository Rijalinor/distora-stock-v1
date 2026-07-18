<?php

namespace App\Enums;

enum StockSessionItemStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Mismatched = 'mismatched';
    case Missing = 'missing';
}
