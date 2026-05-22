<?php

declare(strict_types=1);

namespace Roostar\Modules\RosterData\Services;

final class RosterDataCsvService
{
    public function body(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('CSV kon niet worden aangemaakt.');
        }

        fputcsv($stream, $headers, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn (string $header): string => (string) ($row[$header] ?? ''), $headers), ';', '"', '');
        }

        rewind($stream);
        $body = stream_get_contents($stream);
        fclose($stream);

        return $body === false ? '' : $body;
    }

    public function uploadedRows(string $field): array
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Kies een CSV bestand.');
        }

        $path = (string) ($file['tmp_name'] ?? '');
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException('CSV bestand kon niet worden gelezen.');
        }

        $delimiter = $this->detectDelimiter($handle);
        $headers = null;
        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($headers === null) {
                $headers = array_map([$this, 'normalizeHeader'], $data);
                continue;
            }

            if ($data === [null] || array_filter($data, static fn (mixed $value): bool => trim((string) $value) !== '') === []) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($data[$index] ?? ''));
                }
            }
            $rows[] = $row;
        }
        fclose($handle);

        if ($headers === null) {
            throw new \InvalidArgumentException('CSV bestand heeft geen header.');
        }

        return $rows;
    }

    public function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }

            $normalizedKey = $this->normalizeHeader($key);
            if (isset($row[$normalizedKey]) && trim((string) $row[$normalizedKey]) !== '') {
                return trim((string) $row[$normalizedKey]);
            }
        }

        return '';
    }

    public function list(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[;,|]/', $value) ?: []), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param resource $handle
     */
    private function detectDelimiter($handle): string
    {
        $sample = fgets($handle);
        rewind($handle);

        if (!is_string($sample)) {
            return ',';
        }

        $delimiters = [
            ',' => substr_count($sample, ','),
            ';' => substr_count($sample, ';'),
            "\t" => substr_count($sample, "\t"),
        ];
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);

        return ($delimiters[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return preg_replace('/[^a-z0-9_]/', '', $header) ?? '';
    }
}
