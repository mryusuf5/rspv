<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Book;
use App\Entity\Page;
use App\Entity\User;
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
    public function process(UploadedFile $file, User $user): Book
    {
        $format = $this->detectFormat($file);
        $originalFilename = $file->getClientOriginalName();
        $tempPath = $file->getPathname();

        $existing = $this->entityManager->getRepository(Book::class)->findOneBy([
            'user'             => $user,
            'originalFilename' => $originalFilename,
        ]);

        if ($existing !== null) {
            throw new RuntimeException(sprintf('You have already uploaded "%s".', $originalFilename));
        }

        $book = new Book();
        $book->setUser($user);
        $book->setOriginalFilename($originalFilename);
        $book->setFormat($format);

        if ($format === 'pdf') {
            $this->processPdf($book, $tempPath, $originalFilename);
        } else {
            $this->processEpub($book, $tempPath);
        }

        $this->entityManager->persist($book);
        $this->entityManager->flush();

        return $book;
    }

    private function processPdf(Book $book, string $filePath, string $originalFilename): void
    {
        $book->setTitle(pathinfo($originalFilename, PATHINFO_FILENAME));

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
