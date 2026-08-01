<?php

namespace App\Enums;

enum StatementImportStatus: string
{
    case Processing = 'processing';
    case Extracted = 'extracted';
    case Failed = 'failed';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processando...',
            self::Extracted => 'Lido, aguardando revisão',
            self::Failed => 'Falha na leitura',
            self::Imported => 'Importado',
        };
    }
}
