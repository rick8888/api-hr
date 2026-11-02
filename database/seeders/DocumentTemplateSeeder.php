<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\DocumentTemplate\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ——— PDF ———
            [
                'code' => 'contract_cdi',
                'name' => 'Contrat de travail — CDI',
                'family' => 'contracts',
                'channel' => 'pdf',
                'document_category_code' => 'employment_contract',
                'sort_order' => 10,
                'body_html' => $this->contractCdiBody(),
            ],
            [
                'code' => 'contract_cdd',
                'name' => 'Contrat de travail — CDD',
                'family' => 'contracts',
                'channel' => 'pdf',
                'document_category_code' => 'employment_contract',
                'sort_order' => 20,
                'body_html' => $this->contractCddBody(),
            ],
            [
                'code' => 'warning_letter',
                'name' => 'Lettre d’avertissement',
                'family' => 'discipline',
                'channel' => 'pdf',
                'document_category_code' => 'other',
                'sort_order' => 30,
                'body_html' => $this->warningBody(),
            ],
            [
                'code' => 'termination_letter',
                'name' => 'Notification de fin de contrat',
                'family' => 'termination',
                'channel' => 'pdf',
                'document_category_code' => 'other',
                'sort_order' => 40,
                'body_html' => $this->terminationBody(),
            ],
            [
                'code' => 'employment_certificate',
                'name' => 'Attestation de travail',
                'family' => 'certificates',
                'channel' => 'pdf',
                'document_category_code' => 'other',
                'sort_order' => 50,
                'body_html' => $this->certificateBody(),
            ],

            // ——— E-mails cycle de vie ———
            [
                'code' => 'email_welcome',
                'name' => 'E-mail — Bienvenue / onboarding',
                'family' => 'email',
                'channel' => 'email',
                'subject' => 'Bienvenue chez {{ company.name }}',
                'sort_order' => 100,
                'body_html' => $this->emailWelcome(),
            ],
            [
                'code' => 'email_suspension',
                'name' => 'E-mail — Suspension',
                'family' => 'email',
                'channel' => 'email',
                'subject' => 'Suspension — {{ company.name }}',
                'sort_order' => 110,
                'body_html' => $this->emailSuspension(),
            ],
            [
                'code' => 'email_termination',
                'name' => 'E-mail — Fin de contrat',
                'family' => 'email',
                'channel' => 'email',
                'subject' => 'Fin de contrat — {{ company.name }}',
                'sort_order' => 120,
                'body_html' => $this->emailTermination(),
            ],
            [
                'code' => 'email_payslip_ready',
                'name' => 'E-mail — Bulletin de paie disponible',
                'family' => 'email',
                'channel' => 'email',
                'subject' => 'Votre bulletin de paie est disponible',
                'sort_order' => 130,
                'body_html' => $this->emailPayslip(),
            ],
            [
                'code' => 'email_document_expiring',
                'name' => 'E-mail — Document bientôt expiré',
                'family' => 'email',
                'channel' => 'email',
                'subject' => 'Document à renouveler — {{ company.name }}',
                'sort_order' => 140,
                'body_html' => $this->emailDocumentExpiring(),
            ],

            // ——— Notifications / alertes RH (présence, congés…) ———
            [
                'code' => 'notif_missed_checkin',
                'name' => 'Rappel — Absence de pointage',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Rappel de pointage — {{ today }}',
                'sort_order' => 200,
                'body_html' => $this->notifMissedCheckin(),
            ],
            [
                'code' => 'notif_forgot_checkout',
                'name' => 'Rappel — Oubli de pointage de sortie',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'N’oubliez pas de pointer votre sortie',
                'sort_order' => 210,
                'body_html' => $this->notifForgotCheckout(),
            ],
            [
                'code' => 'notif_late_arrival',
                'name' => 'Alerte — Retard constaté',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Retard enregistré — {{ today }}',
                'sort_order' => 220,
                'body_html' => $this->notifLateArrival(),
            ],
            [
                'code' => 'notif_leave_submitted',
                'name' => 'Notification — Demande de congé reçue',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Nouvelle demande de congé — {{ employee.full_name }}',
                'sort_order' => 230,
                'body_html' => $this->notifLeaveSubmitted(),
            ],
            [
                'code' => 'notif_leave_approved',
                'name' => 'Notification — Congé approuvé',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Votre demande de congé a été approuvée',
                'sort_order' => 240,
                'body_html' => $this->notifLeaveApproved(),
            ],
            [
                'code' => 'notif_leave_rejected',
                'name' => 'Notification — Congé refusé',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Votre demande de congé a été refusée',
                'sort_order' => 250,
                'body_html' => $this->notifLeaveRejected(),
            ],
            [
                'code' => 'notif_birthday',
                'name' => 'Notification — Anniversaire',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Joyeux anniversaire {{ employee.first_name }} !',
                'sort_order' => 260,
                'body_html' => $this->notifBirthday(),
            ],
            [
                'code' => 'notif_work_anniversary',
                'name' => 'Notification — Anniversaire d’ancienneté',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Merci pour votre engagement chez {{ company.name }}',
                'sort_order' => 270,
                'body_html' => $this->notifWorkAnniversary(),
            ],
            [
                'code' => 'notif_manager_missed_checkin',
                'name' => 'Alerte manager — Collaborateur non pointé',
                'family' => 'notification',
                'channel' => 'email',
                'subject' => 'Absence de pointage — {{ employee.full_name }}',
                'sort_order' => 280,
                'body_html' => $this->notifManagerMissedCheckin(),
            ],
        ];

        foreach ($templates as $row) {
            DocumentTemplate::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'code' => $row['code'],
                    'locale' => 'fr',
                ],
                [
                    'name' => $row['name'],
                    'family' => $row['family'],
                    'channel' => $row['channel'],
                    'subject' => $row['subject'] ?? null,
                    'body_html' => $row['body_html'],
                    'document_category_code' => $row['document_category_code'] ?? null,
                    'version' => 1,
                    'status' => 'published',
                    'sort_order' => $row['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                    'published_at' => now(),
                ],
            );
        }
    }

    private function contractCdiBody(): string
    {
        return <<<'HTML'
<h1>CONTRAT DE TRAVAIL À DURÉE INDÉTERMINÉE</h1>
<p class="meta">Établi le {{ today_long }} — {{ company.legal_name }}</p>
<p>Entre la société <strong>{{ company.legal_name }}</strong>, {{ company.address }}, ci-après « l’Employeur »,</p>
<p>Et <strong>{{ employee.full_name }}</strong>, demeurant {{ employee.email }}, ci-après « le Salarié »,</p>
<p>Il est convenu ce qui suit :</p>
<h2>Article 1 — Engagement</h2>
<p>L’Employeur engage le Salarié en contrat à durée indeterminée (CDI) à compter du {{ employee.hired_at_long }}.</p>
<h2>Article 2 — Fonctions</h2>
<p>Le Salarié est employé au poste de <strong>{{ employee.job_title }}</strong>, au sein du département {{ employee.department }}.</p>
<h2>Article 3 — Rémunération</h2>
<p>La rémunération mensuelle brute est fixée à {{ employee.base_salary }} {{ employee.currency }}, sous réserve des dispositions légales et conventionnelles applicables.</p>
<h2>Article 4 — Lieu de travail</h2>
<p>Le lieu principal d’exercice est fixé au siège de l’Employeur, sous réserve des nécessités de service.</p>
<p>Fait en deux exemplaires à {{ company.name }}, le {{ today }}.</p>
<p><br/><br/>Pour l’Employeur&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Le Salarié</p>
HTML;
    }

    private function contractCddBody(): string
    {
        return <<<'HTML'
<h1>CONTRAT DE TRAVAIL À DURÉE DÉTERMINÉE</h1>
<p class="meta">{{ company.legal_name }} — {{ today_long }}</p>
<p>Entre <strong>{{ company.legal_name }}</strong> et <strong>{{ employee.full_name }}</strong>.</p>
<p>Contrat à durée déterminée pour le poste de {{ employee.job_title }}, à compter du {{ employee.hired_at }}.</p>
<p>Rémunération : {{ employee.base_salary }} {{ employee.currency }}.</p>
<p>Fait à {{ company.name }}, le {{ today }}.</p>
HTML;
    }

    private function warningBody(): string
    {
        return <<<'HTML'
<h1>Lettre d’avertissement</h1>
<p>{{ company.name }}<br/>{{ company.address }}<br/>Le {{ today_long }}</p>
<p><strong>Objet :</strong> Avertissement disciplinaire</p>
<p>Madame, Monsieur {{ employee.last_name }},</p>
<p>Nous vous informons par la présente qu’un manquement à vos obligations professionnelles a été constaté.</p>
<p>Vous êtes employé(e) en qualité de {{ employee.job_title }} (matricule {{ employee.matricule }}) depuis le {{ employee.hired_at }}.</p>
<p>Cet avertissement vise à vous rappeler le respect du règlement intérieur et des consignes de l’entreprise.</p>
<p>Nous vous invitons à une vigilance accrue. En cas de récidive, des mesures plus strictes pourraient être envisagées.</p>
<p>Veuillez agréer, Madame, Monsieur, l’expression de nos salutations distinguées.</p>
<p><br/>{{ company.name }}<br/>Service Ressources Humaines</p>
HTML;
    }

    private function terminationBody(): string
    {
        return <<<'HTML'
<h1>Notification de fin de contrat</h1>
<p>{{ company.legal_name }} — {{ today_long }}</p>
<p>Madame, Monsieur {{ employee.full_name }},</p>
<p>Nous vous informons de la rupture de votre contrat de travail, pour motif : <strong>{{ termination.type_label }}</strong>, avec une date d’effet au <strong>{{ termination.date }}</strong>.</p>
<p>Motif complémentaire : {{ termination.reason }}</p>
<p>Matricule : {{ employee.matricule }} — Poste : {{ employee.job_title }}</p>
<p>Les formalités de sortie et de remise des documents légaux vous seront communiquées par le service RH.</p>
<p>Cordialement,<br/>{{ company.name }}</p>
HTML;
    }

    private function certificateBody(): string
    {
        return <<<'HTML'
<h1>ATTESTATION DE TRAVAIL</h1>
<p>Je soussigné(e), représentant(e) la société <strong>{{ company.legal_name }}</strong>, atteste que :</p>
<p><strong>{{ employee.full_name }}</strong>, matricule {{ employee.matricule }}, est (a été) employé(e) au sein de notre entreprise en qualité de {{ employee.job_title }}, depuis le {{ employee.hired_at }}.</p>
<p>Cette attestation est délivrée à l’intéressé(e) pour servir et valoir ce que de droit.</p>
<p>Fait à {{ company.name }}, le {{ today_long }}.</p>
HTML;
    }

    private function emailWelcome(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Nous sommes ravis de vous accueillir au sein de <strong>{{ company.name }}</strong> en qualité de {{ employee.job_title }} à compter du {{ employee.hired_at }}.</p>
<p>Votre matricule : <strong>{{ employee.matricule }}</strong>.</p>
<p>N’hésitez pas à contacter les ressources humaines pour toute question.</p>
<p>Cordialement,<br/>L’équipe RH — {{ company.name }}</p>
HTML;
    }

    private function emailSuspension(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.full_name }},</p>
<p>Nous vous informons d’une suspension à effet du {{ suspension.date }}.</p>
<p>Durée : {{ suspension.duration_days }} jour(s) (fin prévue : {{ suspension.ends_at }}).</p>
<p>Motif : {{ suspension.reason }}</p>
<p>Service RH — {{ company.name }}</p>
HTML;
    }

    private function emailTermination(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.full_name }},</p>
<p>Votre contrat prend fin le {{ termination.date }} ({{ termination.type_label }}).</p>
<p>Dernier jour travaillé : {{ termination.last_working_day }}.</p>
<p>Cordialement,<br/>Les Ressources Humaines — {{ company.name }}</p>
HTML;
    }

    private function emailPayslip(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Votre bulletin de paie est désormais disponible dans votre espace self-service.</p>
<p>Société : {{ company.name }}</p>
<p>Cordialement,<br/>Service Paie</p>
HTML;
    }

    private function emailDocumentExpiring(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.full_name }},</p>
<p>Un de vos documents administratifs arrive bientôt à échéance.</p>
<p>Merci de le renouveler et de le déposer dans votre espace documents.</p>
<p>{{ company.name }} — Service RH</p>
HTML;
    }

    private function notifMissedCheckin(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Nous n’avons pas enregistré de pointage d’entrée pour aujourd’hui ({{ today }}).</p>
<p>Si vous êtes présent(e), merci de pointer dès que possible depuis l’application ou WhatsApp.</p>
<p>En cas d’absence, pensez à prévenir votre manager et à déposer une demande si nécessaire.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifForgotCheckout(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Votre journée du {{ today }} semble toujours ouverte : aucun pointage de sortie n’a été détecté.</p>
<p>Merci de clôturer votre présence afin d’éviter un écart sur votre feuille de temps.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifLateArrival(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.full_name }},</p>
<p>Un retard a été constaté sur votre pointage du {{ today }}.</p>
<p>Si besoin, vous pouvez demander une régularisation auprès de votre manager.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifLeaveSubmitted(): string
    {
        return <<<'HTML'
<p>Bonjour,</p>
<p>Une nouvelle demande de congé a été soumise par <strong>{{ employee.full_name }}</strong> ({{ employee.job_title }}).</p>
<p>Merci de la traiter dans le module Congés.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifLeaveApproved(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Bonne nouvelle : votre demande de congé a été <strong>approuvée</strong>.</p>
<p>Vous pouvez consulter le détail dans votre espace self-service.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifLeaveRejected(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Votre demande de congé a été <strong>refusée</strong>.</p>
<p>Pour plus d’informations, contactez votre manager ou les ressources humaines.</p>
<p>— {{ company.name }}</p>
HTML;
    }

    private function notifBirthday(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Toute l’équipe de <strong>{{ company.name }}</strong> vous souhaite un très joyeux anniversaire !</p>
<p>Belle journée.</p>
HTML;
    }

    private function notifWorkAnniversary(): string
    {
        return <<<'HTML'
<p>Bonjour {{ employee.first_name }},</p>
<p>Nous célébrons aujourd’hui votre anniversaire d’ancienneté chez <strong>{{ company.name }}</strong>.</p>
<p>Merci pour votre engagement depuis le {{ employee.hired_at }}.</p>
HTML;
    }

    private function notifManagerMissedCheckin(): string
    {
        return <<<'HTML'
<p>Bonjour,</p>
<p>Le collaborateur <strong>{{ employee.full_name }}</strong> ({{ employee.department }} / {{ employee.job_title }}) n’a pas pointé ce jour ({{ today }}).</p>
<p>Vous pouvez le contacter ou vérifier une éventuelle absence / télétravail.</p>
<p>— Système de présence {{ company.name }}</p>
HTML;
    }
}
