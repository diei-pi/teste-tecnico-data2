<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Enums\PropertyStatus;
use App\Helpers\DateHelper;
use App\Helpers\MoneyHelper;
use InvalidArgumentException;

class NormalizeCsvService
{
    /**
     * Parse and normalize the given CSV file path.
     * Public entrypoint of the service.
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Arquivo CSV não encontrado: {$filePath}");
        }

        $content = file_get_contents($filePath);
        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        // Detect delimiter: comma or semicolon
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

            // Combine header with row values
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
        $precoAnunciado = MoneyHelper::parseMoneyFromString($row['preco_anunciado'] ?? null);
        $precoFechado = MoneyHelper::parseMoneyFromString($row['preco_fechado'] ?? null);

        $dataFechamento = DateHelper::parseDateFromString($row['data_fechamento'] ?? null);
        $inicioContrato = DateHelper::parseDateFromString($row['inicio_contrato'] ?? null);

        $cidade = $this->normalizeCity($row['cidade'] ?? '');
        $statusEnum = $this->normalizeStatus($row['status'] ?? '');
        $businessTypeEnum = $this->normalizeBusinessType($row['negocio'] ?? '');

        return [
            'codigo' => $row['codigo'] ?? '',
            'tipo' => $row['tipo'] ?? '',
            'bairro' => $row['bairro'] ?? '',
            'cidade' => $cidade,
            'cidade_original' => $row['cidade'] ?? '',
            'negocio' => $businessTypeEnum->value,
            'negocio_enum' => $businessTypeEnum,
            'status' => $statusEnum->value,
            'status_enum' => $statusEnum,
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
     * Clean and format city name using title case without hardcoded lists.
     */
    private function normalizeCity(string $city): string
    {
        $trimmed = trim($city);
        if ($trimmed === '') {
            return '';
        }

        return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normalize business type to BusinessType enum.
     */
    private function normalizeBusinessType(string $negocio): BusinessType
    {
        $lower = mb_strtolower(trim($negocio), 'UTF-8');
        if (str_contains($lower, 'venda')) {
            return BusinessType::Venda;
        }
        if (str_contains($lower, 'aluguel')) {
            return BusinessType::Aluguel;
        }

        return BusinessType::Outro;
    }

    /**
     * Normalize property status to PropertyStatus enum.
     */
    private function normalizeStatus(string $status): PropertyStatus
    {
        $lower = mb_strtolower(trim($status), 'UTF-8');
        if (str_contains($lower, 'vendido')) {
            return PropertyStatus::Vendido;
        }
        if (str_contains($lower, 'alugado')) {
            return PropertyStatus::Alugado;
        }
        if (str_contains($lower, 'negocia')) {
            return PropertyStatus::EmNegociacao;
        }
        if (str_contains($lower, 'reserv')) {
            return PropertyStatus::Reservado;
        }
        if (str_contains($lower, 'dispon')) {
            return PropertyStatus::Disponivel;
        }
        if (str_contains($lower, 'suspens')) {
            return PropertyStatus::Suspenso;
        }

        return PropertyStatus::Outro;
    }
}
