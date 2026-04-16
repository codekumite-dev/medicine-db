<?php

namespace App\Enums;

enum ApprovalStatusEnum: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Published = 'published';
    case Archived = 'archived';
}
