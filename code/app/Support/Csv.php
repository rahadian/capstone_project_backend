<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    public static function cell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }

    public static function write($stream, array $columns, iterable $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fwrite($stream, self::encodeRow($columns)."\r\n");

        foreach ($rows as $row) {
            fwrite($stream, self::encodeRow($row)."\r\n");
        }
    }

    private static function encodeRow(iterable $cells): string
    {
        $text = [];

        foreach ($cells as $value) {
            $content = self::cell($value);
            $text[] = preg_match('/[";\r\n]/', $content) === 1
                ? '"'.str_replace('"', '""', $content).'"'
                : $content;
        }

        return implode(';', $text);
    }

    public static function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $stream = fopen('php://output', 'w');
            self::write($stream, $columns, $rows);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}