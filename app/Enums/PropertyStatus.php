<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Vendido = 'Vendido';
    case Alugado = 'Alugado';
    case EmNegociacao = 'Em negociação';
    case Reservado = 'Reservado';
    case Disponivel = 'Disponível';
    case Suspenso = 'Suspenso';
    case Outro = 'Outro';
}
