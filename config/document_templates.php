<?php

declare(strict_types=1);

return [
    /*
    | Événements métier → code du modèle e-mail / notification publié.
    |
    | Les clés contiennent un point (ex. employee.welcome). Ne pas les lire via
    | config('document_templates.events.employee.welcome') : Laravel le prend
    | pour un tableau imbriqué. Utiliser config('document_templates.events')[$key].
    */
    'events' => [
        'employee.welcome' => 'email_welcome',
        'employee.suspended' => 'email_suspension',
        'employee.terminated' => 'email_termination',
        'attendance.missed_checkin' => 'notif_missed_checkin',
        'attendance.forgot_checkout' => 'notif_forgot_checkout',
        'attendance.late' => 'notif_late_arrival',
        'attendance.missed_checkin.manager' => 'notif_manager_missed_checkin',
        'leave.submitted' => 'notif_leave_submitted',
        'leave.approved' => 'notif_leave_approved',
        'leave.rejected' => 'notif_leave_rejected',
        'employee.birthday' => 'notif_birthday',
        'employee.work_anniversary' => 'notif_work_anniversary',
        'payroll.payslip_ready' => 'email_payslip_ready',
        'document.expiring' => 'email_document_expiring',
    ],

    /*
    | PDF généré et archivé en GED lors de l’événement (si demandé côté UI).
    */
    'documents' => [
        'employee.terminated' => 'termination_letter',
    ],

    'default_locale' => 'fr',
];
