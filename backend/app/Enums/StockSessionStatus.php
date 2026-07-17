<?php

namespace App\Enums;

enum StockSessionStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
