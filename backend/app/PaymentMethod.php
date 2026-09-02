<?php

namespace App;

enum PaymentMethod: string
{
    case Virement = 'virement';
    case Especes = 'especes';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Virement => 'Virement bancaire',
            self::Especes => 'Espèces',
            self::Cheque => 'Chèque',
        };
    }
}
