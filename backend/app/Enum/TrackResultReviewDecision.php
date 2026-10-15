<?php

namespace App\Enum;

enum TrackResultReviewDecision: string
{
    case Approve = 'approve';
    case RequestRevision = 'request_revision';
}
