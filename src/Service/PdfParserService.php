<?php

declare(strict_types=1);

namespace App\Service;

use Spatie\PdfToText\Pdf;

class PdfParserService
{
    /**
     * Parse a PDF file and return an array of page content strings.
     *
     * @return string[] indexed from 1, where each value is the text content of that page
     */
    public function parse(string $filePath): array
    {
        $pdf = new Pdf($filePath);
        $totalPages = $pdf->getNumberOfPages();

        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
            $text = (new Pdf($filePath))
                ->setPageRange($pageNumber, $pageNumber)
                ->text();

            $pages[$pageNumber] = $this->normalizeText($text);
        }

        return $pages;
    }

    /**
     * Get total page count of a PDF without extracting text.
     */
    public function getPageCount(string $filePath): int
    {
        return (new Pdf($filePath))->getNumberOfPages();
    }

    private function normalizeText(string $text): string
    {
        // Normalize line endings, collapse excessive whitespace
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
