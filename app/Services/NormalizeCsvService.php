<?php

namespace App\Services;

use App\Enums\BusinessType;
use App\Enums\PropertyStatus;
use App\Helpers\DateHelper;
use App\Helpers\MoneyHelper;
use Generator;
use InvalidArgumentException;

class NormalizeCsvService
{
    /**
     * Parse and stream normalized rows from CSV file using a Generator.
     * Memory consumption is O(1) (~few KB), making it ideal for large datasets (100k+ rows).
     */
    public function parseStream(string $filePath): Generator
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Arquivo CSV não encontrado: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Não foi possível abrir o arquivo CSV: {$filePath}");
        }

        try {
            // Read first line to detect delimiter and BOM
            $firstLine = fgets($handle);
            if ($firstLine === false || trim($firstLine) === '') {
                return;
            }

            // Strip UTF-8 BOM if present
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

            $header = str_getcsv(trim($firstLine), $delimiter, '"', '\\');
            $header = array_map(fn($col) => trim(strtolower($col)), $header);

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if (empty($row) || (count($row) === 1 && trim($row[0] ?? '') === '')) {
                    continue;
                }

                // Combine header with row values
                $data = [];
                foreach ($header as $index => $colName) {
                    $data[$colName] = isset($row[$index]) ? trim($row[$index]) : null;
                }

                yield $this->normalizeRow($data);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse CSV file and return all rows in an array.
     * Convenient for smaller datasets and tests.
     */
    public function parse(string $filePath): array
    {
        return iterator_to_array($this->parseStream($filePath));
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
