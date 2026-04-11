<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\Process;

class PdfParserService
{
    /**
     * Parse a PDF file and return an array of page content strings.
     *
     * @return string[] indexed from 1, where each value is the text content of that page
     */
    public function parse(string $filePath): array
    {
        $totalPages = $this->getPageCount($filePath);
        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $text = (new Pdf())
                ->setPdf($filePath)
                ->setOptions(["-f {$pageNumber}", "-l {$pageNumber}"])
                ->text();

            $pages[$pageNumber] = $this->normalizeText($text);
        }

        return $pages;
    }

    /**
     * Get total page count using pdfinfo (part of poppler-utils, same package as pdftotext).
     */
    public function getPageCount(string $filePath): int
    {
        $process = new Process(['pdfinfo', $filePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('pdfinfo failed: ' . $process->getErrorOutput());
        }

        if (preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $matches)) {
            return (int) $matches[1];
        }

        throw new RuntimeException('Could not determine page count from pdfinfo output.');
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
