<?php

namespace App\Enums;

enum EditorialStatusEnum: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case MedicallyReviewed = 'medically_reviewed';
    case Published = 'published';
    case Retired = 'retired';
}
