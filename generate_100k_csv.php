<?php

$filePath = __DIR__ . '/carteira_100k_linhas.csv';
$fp = fopen($filePath, 'w');

fputcsv($fp, [
    'codigo',
    'tipo',
    'bairro',
    'cidade',
    'negocio',
    'status',
    'preco_anunciado',
    'preco_fechado',
    'data_fechamento',
    'inicio_contrato',
    'observacao'
]);

$tipos = ['Apartamento', 'Casa', 'Sala comercial', 'Kitnet', 'Terreno', 'Cobertura', 'Galpão'];
$bairros = ['Centro', 'Pinheiros', 'Moema', 'Jardins', 'Vila Mariana', 'Cambuí', 'Gonzaga', 'Alphaville', 'Perdizes', 'Taquaral'];
$cidades = ['São Paulo', 'sao paulo', 'SP', 'Campinas', 'campinas', 'Santos', 'Ribeirão Preto', 'Sorocaba', 'Barueri', 'Cotia'];
$negocios = ['Venda', 'Aluguel'];
$statuses = ['Disponível', 'Em negociação', 'Vendido', 'Alugado', 'Reservado', 'Suspenso'];

$totalRows = 100000;
echo "Gerando {$totalRows} linhas...\n";

for ($i = 1; $i <= $totalRows; $i++) {
    $code = 'IMV-' . str_pad($i, 6, '0', STR_PAD_LEFT);
    $tipo = $tipos[array_rand($tipos)];
    $bairro = $bairros[array_rand($bairros)];
    $cidade = $cidades[array_rand($cidades)];
    $negocio = $negocios[array_rand($negocios)];
    $status = $statuses[array_rand($statuses)];

    $precoAnunciado = null;
    $precoFechado = null;
    $dataFechamento = '';
    $inicioContrato = '';
    $obs = '';

    if ($negocio === 'Venda') {
        $val = rand(250, 2500) * 1000;
        // Varied formats
        $formatType = rand(1, 4);
        if ($formatType === 1) {
            $precoAnunciado = ($val / 1000) . ' mil';
        } elseif ($formatType === 2) {
            $precoAnunciado = 'R$ ' . number_format($val, 2, ',', '.');
        } elseif ($formatType === 3) {
            $precoAnunciado = number_format($val, 0, '', '.');
        } else {
            $precoAnunciado = (string)$val;
        }

        if ($status === 'Vendido') {
            $fechadoVal = $val * 0.95;
            $precoFechado = 'R$ ' . number_format($fechadoVal, 2, ',', '.');
            // Random date between 01/10/2026 and 30/11/2026
            $day = rand(1, 28);
            $month = rand(10, 11);
            $dataFechamento = str_pad($day, 2, '0', STR_PAD_LEFT) . '/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/2026';
        } elseif ($status === 'Reservado') {
            $obs = 'sinal pago - escritura dia ' . rand(1, 28) . '/12';
        } elseif ($status === 'Em negociação') {
            if (rand(1, 3) === 1) {
                $obs = 'cliente fez proposta de ' . ($val * 0.9) . ' mil';
            }
        }
    } else {
        // Aluguel
        $val = rand(15, 80) * 100;
        $formatType = rand(1, 3);
        if ($formatType === 1) {
            $precoAnunciado = 'R$' . number_format($val, 0, '', '.') . '/mês';
        } elseif ($formatType === 2) {
            $precoAnunciado = number_format($val, 2, ',', '.');
        } else {
            $precoAnunciado = (string)$val;
        }

        if ($status === 'Alugado') {
            $day = rand(1, 28);
            $month = rand(1, 11);
            $year = rand(2025, 2026);
            $inicioContrato = str_pad($day, 2, '0', STR_PAD_LEFT) . '/' . str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $year;
            if (rand(1, 15) === 1) {
                $obs = 'inquilino atrasou o aluguel de novembro';
            }
        }
    }

    fputcsv($fp, [
        $code,
        $tipo,
        $bairro,
        $cidade,
        $negocio,
        $status,
        $precoAnunciado,
        $precoFechado,
        $dataFechamento,
        $inicioContrato,
        $obs
    ]);
}

fclose($fp);
echo "Arquivo gerado com sucesso: {$filePath}\nTamanho: " . round(filesize($filePath) / 1024 / 1024, 2) . " MB\n";
