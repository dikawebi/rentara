<?php

namespace App;

enum IdentityDocumentType: string
{
    case Ktp = 'ktp';
    case Passport = 'passport';
    case StudentCard = 'student_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Passport => 'Paspor',
            self::StudentCard => 'Kartu pelajar/mahasiswa',
            self::Other => 'Dokumen identitas lain',
        };
    }
}
