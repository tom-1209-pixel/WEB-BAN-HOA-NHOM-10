<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Drafting   = 'drafting';
    case Completed  = 'completed';
}
