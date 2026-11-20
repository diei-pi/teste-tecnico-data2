<?php

namespace App\Enums;

enum BusinessType: string
{
    case Venda = 'Venda';
    case Aluguel = 'Aluguel';
    case Outro = 'Outro';
}
