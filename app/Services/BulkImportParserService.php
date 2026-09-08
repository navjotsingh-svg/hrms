<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BulkImportParserService
{
    /** @return array{headers: array<int, string>, rows: array<int, array<string, string|null>>, preview: array<int, array<string, string|null>>} */
    public function parse(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new UnprocessableEntityHttpException('Uploaded file could not be read.');
        }

        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable $exception) {
            throw new UnprocessableEntityHttpException('Unable to read spreadsheet. Upload a valid Excel or CSV file.');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            throw new UnprocessableEntityHttpException('The uploaded file is empty.');
        }

        $headerRow = array_shift($matrix);
        $headers = $this->normalizeHeaders($headerRow);

        if ($headers === []) {
            throw new UnprocessableEntityHttpException('No column headers were found in the first row.');
        }

        $rows = [];

        foreach ($matrix as $cells) {
            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $this->stringValue($cells[$index] ?? null);
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            throw new UnprocessableEntityHttpException('No data rows were found below the header row.');
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'preview' => array_slice($rows, 0, 5),
        ];
    }

    /** @param  array<int, mixed>  $headerRow */
    private function normalizeHeaders(array $headerRow): array
    {
        $headers = [];
        $seen = [];

        foreach ($headerRow as $index => $cell) {
            $label = trim((string) ($cell ?? ''));

            if ($label === '') {
                $label = 'Column '.($index + 1);
            }

            $unique = $label;
            $suffix = 2;

            while (isset($seen[$unique])) {
                $unique = "{$label} ({$suffix})";
                $suffix++;
            }

            $seen[$unique] = true;
            $headers[$index] = $unique;
        }

        return array_values($headers);
    }

    /** @param  array<int, mixed>  $cells */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) ($cell ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value) && ! is_string($value)) {
            $string = (string) $value;

            return str_contains($string, '.') && preg_match('/\.0+$/', $string)
                ? (string) (int) $value
                : $string;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
