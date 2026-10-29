<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    private function text(array $columns, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        Csv::write($stream, $columns, $rows);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function test_writes_bom_header_and_semicolon_separator(): void
    {
        $this->assertSame("\xEF\xBB\xBFA;B\r\n1;2\r\n", $this->text(['A', 'B'], [[1, 2]]));
    }

    public function test_empty_file_still_contains_header(): void
    {
        $this->assertSame("\xEF\xBB\xBFA;B\r\n", $this->text(['A', 'B'], []));
    }

    public function test_quotes_cells_containing_separator_or_quote(): void
    {
        $this->assertSame(
            "\xEF\xBB\xBFA\r\n\"Siti; Sarah\"\r\n\"kata \"\"kutip\"\"\"\r\n",
            $this->text(['A'], [['Siti; Sarah'], ['kata "kutip"']]),
        );
    }

    public function test_prefixes_dangerous_cells_with_quote(): void
    {
        foreach (['=1+1', '+62', '-1', '@SUM(A1)', "\tisi", "\risi"] as $value) {
            $this->assertSame("'".$value, Csv::cell($value));
        }
        $this->assertSame('1500000', Csv::cell(1500000));
    }
}