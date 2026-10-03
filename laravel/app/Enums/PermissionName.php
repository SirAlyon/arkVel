<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Permessi applicativi della Fase 1 (guard `web`).
 *
 * I value seguono la convenzione `<risorsa>.<azione>` e sono l'unica fonte
 * di verità: non usare mai magic string nel codice o nelle Policy.
 */
enum PermissionName: string implements HasLabel
{
    case OrganizationUpdate = 'organization.update';
    case OrganizationManageStaff = 'organization.manage_staff';
    case AnimalView = 'animal.view';
    case AnimalCreate = 'animal.create';
    case AnimalUpdate = 'animal.update';
    case AnimalDelete = 'animal.delete';
    case MedicalRecordView = 'medical_record.view';
    case MedicalRecordCreate = 'medical_record.create';
    case MedicalRecordUpdate = 'medical_record.update';
    case MedicalRecordDelete = 'medical_record.delete';
    case ApplicationView = 'application.view';
    case ApplicationManage = 'application.manage';
    case FosterApplicationView = 'foster_application.view';
    case FosterApplicationManage = 'foster_application.manage';
    case AppointmentView = 'appointment.view';
    case AppointmentManage = 'appointment.manage';
    case CalendarManage = 'calendar.manage';
    case MessageView = 'message.view';
    case MessageManage = 'message.manage';
    case DonationView = 'donation.view';
    case DonationManage = 'donation.manage';

    public function getLabel(): string
    {
        return match ($this) {
            self::OrganizationUpdate => 'Modifica ente',
            self::OrganizationManageStaff => 'Gestisci staff ente',
            self::AnimalView => 'Visualizza animali',
            self::AnimalCreate => 'Crea animali',
            self::AnimalUpdate => 'Modifica animali',
            self::AnimalDelete => 'Elimina animali',
            self::MedicalRecordView => 'Visualizza cartelle sanitarie',
            self::MedicalRecordCreate => 'Crea cartelle sanitarie',
            self::MedicalRecordUpdate => 'Modifica cartelle sanitarie',
            self::MedicalRecordDelete => 'Elimina cartelle sanitarie',
            self::ApplicationView => 'Visualizza candidature',
            self::ApplicationManage => 'Gestisci candidature',
            self::FosterApplicationView => 'Visualizza richieste di affido',
            self::FosterApplicationManage => 'Gestisci richieste di affido',
            self::AppointmentView => 'Visualizza appuntamenti',
            self::AppointmentManage => 'Gestisci appuntamenti',
            self::CalendarManage => 'Gestisci sincronizzazione calendario',
            self::MessageView => 'Visualizza messaggi',
            self::MessageManage => 'Gestisci messaggi',
            self::DonationView => 'Visualizza donazioni',
            self::DonationManage => 'Gestisci donazioni',
        };
    }
}
