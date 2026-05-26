<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Page;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
class BookContentStartController extends AbstractController
{
    /**
     * Chapter title substrings that indicate front-matter / boilerplate.
     * Case-insensitive substring match.
     */
    private const BOILERPLATE_KEYWORDS = [
        'content',       // Table of Contents, Contents
        'copyright',
        'dedication',
        'acknowledge',   // Acknowledgements / Acknowledgments
        'foreword',
        'preface',
        'about',         // About the Author, About This Book
        'title page',
        'half title',
        'front matter',
        'epigraph',
        'also by',
        'permissions',
        'publisher',
        'introduction',
        'note from',
        'author\'s note',
        'a note',
    ];

    /**
     * Minimum word count for a page to be considered "real content".
     * EPUB pages are split at 300 words, so anything below ~200 is a short
     * front-matter section that didn't fill an entire virtual page.
     */
    private const MIN_CONTENT_WORDS = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $book = $this->entityManager->find(Book::class, $id);

        if ($book === null) {
            throw new NotFoundHttpException(sprintf('Book %d not found.', $id));
        }

        $bookOwner = $book->getUser();
        $canAccess = $bookOwner->getId() === $user->getId()
            || in_array('ROLE_ADMIN', $bookOwner->getRoles(), true);

        if (!$canAccess) {
            throw new NotFoundHttpException(sprintf('Book %d not found.', $id));
        }

        // Only makes sense for EPUBs (PDFs use filenames as titles and have no front-matter structure)
        if ($book->getFormat() !== 'epub') {
            return $this->json(['pageNumber' => null]);
        }

        /** @var array<array{pageNumber: int, wordCount: int, chapterTitle: string|null}> $rows */
        $rows = $this->entityManager
            ->createQuery(
                'SELECT p.pageNumber, p.wordCount, p.chapterTitle
                 FROM App\Entity\Page p
                 WHERE p.book = :book
                 ORDER BY p.pageNumber ASC'
            )
            ->setParameter('book', $book)
            ->getArrayResult();

        foreach ($rows as $row) {
            // Already on the first page — nothing to skip
            if ($row['pageNumber'] <= 1) {
                if ($this->isContentPage($row)) {
                    return $this->json(['pageNumber' => null]);
                }
                continue;
            }

            if ($this->isContentPage($row)) {
                return $this->json(['pageNumber' => $row['pageNumber']]);
            }
        }

        return $this->json(['pageNumber' => null]);
    }

    /**
     * @param array{pageNumber: int, wordCount: int, chapterTitle: string|null} $row
     */
    private function isContentPage(array $row): bool
    {
        if ($row['wordCount'] < self::MIN_CONTENT_WORDS) {
            return false;
        }

        $title = strtolower(trim($row['chapterTitle'] ?? ''));

        if ($title === '') {
            return true; // no title → no boilerplate signal → trust word count
        }

        foreach (self::BOILERPLATE_KEYWORDS as $kw) {
            if (str_contains($title, $kw)) {
                return false;
            }
        }

        return true;
    }
}
