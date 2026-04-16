<?php

namespace App\Enums;

enum DosageFormEnum: string
{
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Syrup = 'syrup';
    case Suspension = 'suspension';
    case Injection = 'injection';
    case Ointment = 'ointment';
    case Cream = 'cream';
    case Gel = 'gel';
    case Drops = 'drops';
    case Inhaler = 'inhaler';
    case Patch = 'patch';
    case Suppository = 'suppository';
    case Powder = 'powder';
    case Lotion = 'lotion';
    case Spray = 'spray';
}
