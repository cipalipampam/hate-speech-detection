<?php

namespace Tests\Unit;

use App\Models\AnalysisExport;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Memverifikasi kalkulasi format ukuran file manusiawi pada AnalysisExport::readable_size.
     */
    public function test_analysis_export_readable_size_formatting(): void
    {
        $exportBytes = new AnalysisExport(['size_bytes' => 512]);
        $this->assertSame('512 B', $exportBytes->readable_size);

        $exportKb = new AnalysisExport(['size_bytes' => 2048]);
        $this->assertSame('2 KB', $exportKb->readable_size);

        $exportMb = new AnalysisExport(['size_bytes' => 5242880]);
        $this->assertSame('5 MB', $exportMb->readable_size);
    }
}
