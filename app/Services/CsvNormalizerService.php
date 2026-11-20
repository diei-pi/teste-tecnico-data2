<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class CsvNormalizerService
{
    /**
     * Parse and normalize the given CSV file path.
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Arquivo CSV não encontrado: {$filePath}");
        }
        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rawRows = array_map(fn($line) => str_getcsv($line, $delimiter, '"', '\\'), $lines);
        $header = array_shift($rawRows);
        $header = array_map(fn($col) => trim(strtolower($col)), $header);

        $normalized = [];
        foreach ($rawRows as $row) {
            if (empty($row) || (count($row) === 1 && trim($row[0] ?? '') === '')) {
                continue;
            }
            $data = [];
            foreach ($header as $index => $colName) {
                $data[$colName] = isset($row[$index]) ? trim($row[$index]) : null;
            }
            $normalized[] = $this->normalizeRow($data);
        }
        return $normalized;
    }

    /**
     * Normalize a single row data.
     */
    private function normalizeRow(array $row): array
    {
        $precoAnunciado = $this->parseMoney($row['preco_anunciado'] ?? null);
        $precoFechado = $this->parseMoney($row['preco_fechado'] ?? null);

        $dataFechamento = $this->parseDate($row['data_fechamento'] ?? null);
        $inicioContrato = $this->parseDate($row['inicio_contrato'] ?? null);

        $cidade = $this->normalizeCity($row['cidade'] ?? '');
        $status = $this->normalizeStatus($row['status'] ?? '');
        $negocio = $this->normalizeNegocio($row['negocio'] ?? '');

        return [
            'codigo' => $row['codigo'] ?? '',
            'tipo' => $row['tipo'] ?? '',
            'bairro' => $row['bairro'] ?? '',
            'cidade' => $cidade,
            'cidade_original' => $row['cidade'] ?? '',
            'negocio' => $negocio,
            'status' => $status,
            'status_original' => $row['status'] ?? '',
            'preco_anunciado' => $precoAnunciado,
            'preco_fechado' => $precoFechado,
            'data_fechamento' => $dataFechamento ? $dataFechamento->format('Y-m-d') : null,
            'data_fechamento_raw' => $row['data_fechamento'] ?? null,
            'inicio_contrato' => $inicioContrato ? $inicioContrato->format('Y-m-d') : null,
            'inicio_contrato_raw' => $row['inicio_contrato'] ?? null,
            'observacao' => $row['observacao'] ?? '',
        ];
    }

    /**
     * Parse messy currency strings into float numbers.
     * Examples: "720 mil" -> 720000, "R$3.500/mês" -> 3500, "1.150.000" -> 1150000, "4.500,00" -> 4500
     */
    public function parseMoney(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $str = trim($value);

        // Check for "mil" notation (e.g. "720 mil", "410 mil")
        if (preg_match('/([\d\.,]+)\s*mil/i', $str, $matches)) {
            $num = str_replace(',', '.', str_replace('.', '', $matches[1]));
            return (float) $num * 1000;
        }

        // Clean out noise: "R$", "/mês", "/mes", spaces
        $str = preg_replace('/[R\$\s\/mês\/mes]/iu', '', $str);

        // Check pattern: Brazilian format with comma decimal (e.g. "4.500,00", "980.000,00", "1.900,00")
        if (preg_match('/^(\d{1,3}(\.\d{3})*),(\d{2})$/', $str, $matches)) {
            $cleaned = str_replace('.', '', $matches[1]) . '.' . $matches[3];
            return (float) $cleaned;
        }

        // Comma as decimal without thousand dot (e.g. "4500,00")
        if (preg_match('/^(\d+),(\d{2})$/', $str, $matches)) {
            return (float) ($matches[1] . '.' . $matches[2]);
        }

        // Multiple dots as thousand separators without comma (e.g. "1.150.000")
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $str)) {
            return (float) str_replace('.', '', $str);
        }

        // Single dot with 3 digits (e.g. "2.800" -> 2800)
        if (preg_match('/^(\d+)\.(\d{3})$/', $str, $matches)) {
            return (float) ($matches[1] . $matches[2]);
        }

        // Standard dot decimal or plain integer (e.g. "1450000", "1600")
        $cleaned = preg_replace('/[^\d\.]/', '', $str);
        return $cleaned !== '' ? (float) $cleaned : null;
    }

    /**
     * Clean and format city name using title case without hardcoded lists.
     */
    public function normalizeCity(string $city): string
    {
        $trimmed = trim($city);
        if ($trimmed === '') {
            return '';
        }

        return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Standardize business type (Venda / Aluguel).
     */
    public function normalizeNegocio(string $negocio): string
    {
        $lower = mb_strtolower(trim($negocio), 'UTF-8');
        if (str_contains($lower, 'venda')) return 'Venda';
        if (str_contains($lower, 'aluguel')) return 'Aluguel';
        return ucfirst(trim($negocio));
    }

    /**
     * Standardize property status.
     */
    public function normalizeStatus(string $status): string
    {
        $lower = mb_strtolower(trim($status), 'UTF-8');
        if (str_contains($lower, 'vendido')) return 'Vendido';
        if (str_contains($lower, 'alugado')) return 'Alugado';
        if (str_contains($lower, 'negocia')) return 'Em negociação';
        if (str_contains($lower, 'reserv')) return 'Reservado';
        if (str_contains($lower, 'dispon')) return 'Disponível';
        if (str_contains($lower, 'suspens')) return 'Suspenso';
        return ucfirst(trim($status));
    }

    /**
     * Parse date string into Carbon instance.
     */
    public function parseDate(?string $dateStr): ?Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        $str = trim($dateStr);
        // Formats: d/m/Y, Y-m-d, d-m-Y
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'];
        foreach ($formats as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $str);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }
}
