<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Book;
use App\Entity\Page;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BookProcessorService
{
    public function __construct(
        private readonly PdfParserService $pdfParser,
        private readonly EpubParserService $epubParser,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Process an uploaded file: detect format, parse pages, persist to database.
     */
    public function process(UploadedFile $file): Book
    {
        $format = $this->detectFormat($file);
        $tempPath = $file->getPathname();

        $book = new Book();
        $book->setFormat($format);

        if ($format === 'pdf') {
            $this->processPdf($book, $tempPath);
        } else {
            $this->processEpub($book, $tempPath);
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $book;
    }

    private function processPdf(Book $book, string $filePath): void
    {
        // Extract title from filename (no reliable metadata extraction for plain PDFs via pdftotext)
        $book->setTitle(pathinfo($filePath, PATHINFO_FILENAME));

        $pages = $this->pdfParser->parse($filePath);

        foreach ($pages as $pageNumber => $content) {
            $page = new Page();
            $page->setPageNumber($pageNumber);
            $page->setContent($content);
            $book->addPage($page);
        }

        $book->setTotalPages(count($pages));
    }

    private function processEpub(Book $book, string $filePath): void
    {
        $metadata = $this->epubParser->extractMetadata($filePath);
        $book->setTitle($metadata['title']);
        $book->setAuthor($metadata['author']);

        $pages = $this->epubParser->parse($filePath);

        foreach ($pages as $pageNumber => $content) {
            $page = new Page();
            $page->setPageNumber($pageNumber);
            $page->setContent($content);
            $book->addPage($page);
        }

        $book->setTotalPages(count($pages));
    }

    private function detectFormat(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($mimeType === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($mimeType, ['application/epub+zip', 'application/epub'], true) || $extension === 'epub') {
            return 'epub';
        }

        throw new RuntimeException(
            sprintf('Unsupported file format. Got MIME type "%s" (extension: "%s"). Only PDF and EPUB are accepted.', $mimeType, $extension)
        );
    }
}
