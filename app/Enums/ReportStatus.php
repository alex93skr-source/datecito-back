<?php

namespace App\Enums;

/**
 * Estado de revisión de un reporte.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
