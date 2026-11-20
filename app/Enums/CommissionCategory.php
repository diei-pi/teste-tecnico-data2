<?php

namespace App\Enums;

enum CommissionCategory: string
{
    case VendaFechada = 'venda_fechada';
    case AluguelNovo = 'aluguel_novo';
    case AluguelRecorrente = 'aluguel_recorrente';
    case Pipeline = 'pipeline';
    case Pendente = 'pendente';
    case Passado = 'passado';
    case Futuro = 'futuro';
    case Reservado = 'reservado';
    case Suspenso = 'suspenso';
    case Estoque = 'estoque';

    public function badgeClass(): string
    {
        return match ($this) {
            self::VendaFechada => 'bg-emerald-100 text-emerald-800',
            self::AluguelNovo => 'bg-blue-100 text-blue-800',
            self::AluguelRecorrente => 'bg-teal-100 text-teal-800',
            self::Pipeline => 'bg-amber-100 text-amber-800',
            self::Pendente => 'bg-rose-100 text-rose-800',
            self::Reservado => 'bg-purple-100 text-purple-800',
            self::Suspenso => 'bg-red-50 text-red-600',
            self::Futuro => 'bg-blue-50 text-blue-600',
            self::Passado, self::Estoque => 'bg-gray-100 text-gray-500',
        };
    }
}
