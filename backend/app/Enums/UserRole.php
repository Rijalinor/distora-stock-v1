<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case StockOfficer = 'stock_officer';
}
