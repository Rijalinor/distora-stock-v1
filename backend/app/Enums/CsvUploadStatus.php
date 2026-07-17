<?php

namespace App\Enums;

enum CsvUploadStatus: string
{
    case Pending = 'pending';
    case Previewed = 'previewed';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
