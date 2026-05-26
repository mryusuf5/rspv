<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\GlobalBook;
use App\Entity\GlobalPage;
use App\Entity\Page;
use App\Entity\User;
use App\Service\BookProcessorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

class GlobalBookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookProcessorService $bookProcessor,
    ) {}

    #[Route('/api/admin/global-books', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded.');
        }
        if (!$file->isValid()) {
            throw new BadRequestHttpException('File upload error: ' . $file->getErrorMessage());
        }

        try {
            $data = $this->bookProcessor->parseOnly($file);
        } catch (\RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $globalBook = new GlobalBook();
        $globalBook->setTitle($data['title']);
        $globalBook->setAuthor($data['author']);
        $globalBook->setFormat($data['format']);
        $globalBook->setOriginalFilename($data['originalFilename']);

        foreach ($data['pages'] as $pageData) {
            $gp = new GlobalPage();
            $gp->setPageNumber($pageData['pageNumber']);
            $gp->setContent($pageData['content']);
            $gp->setChapterTitle($pageData['chapterTitle']);
            $globalBook->addPage($gp);
        }

        $globalBook->setTotalPages(count($data['pages']));
        $globalBook->setTotalWords(array_sum(array_map(
            fn(GlobalPage $p) => $p->getWordCount(),
            $globalBook->getPages()->toArray()
        )));

        $this->em->persist($globalBook);
        $this->em->flush();

        return $this->json($this->normalize($globalBook), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/push-book', methods: ['POST'])]
    public function pushToAll(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded.');
        }
        if (!$file->isValid()) {
            throw new BadRequestHttpException('File upload error: ' . $file->getErrorMessage());
        }

        /** @var User $admin */
        $admin = $this->getUser();

        try {
            $data = $this->bookProcessor->parseOnly($file);
        } catch (\RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        // Pre-compute word count once (same for every user copy)
        $totalWords = 0;
        foreach ($data['pages'] as $pageData) {
            $totalWords += str_word_count($pageData['content']);
        }

        // Find user IDs that already have this file — exclude them from the push
        $alreadyHaveIds = array_column(
            $this->em->getConnection()->fetchAllAssociative(
                'SELECT user_id FROM books WHERE original_filename = :fn',
                ['fn' => $data['originalFilename']]
            ),
            'user_id'
        );
        $alreadyHaveIds[] = $admin->getId();

        $recipients = array_filter(
            $this->em->getRepository(User::class)->findAll(),
            fn(User $u) => !in_array($u->getId(), $alreadyHaveIds, true)
        );

        $pushed = 0;
        foreach ($recipients as $recipient) {
            $userBook = new Book();
            $userBook->setUser($recipient);
            $userBook->setTitle($data['title']);
            $userBook->setAuthor($data['author']);
            $userBook->setFormat($data['format']);
            $userBook->setOriginalFilename($data['originalFilename']);
            $userBook->setTotalPages(count($data['pages']));
            $userBook->setTotalWords($totalWords);

            foreach ($data['pages'] as $pageData) {
                $p = new Page();
                $p->setPageNumber($pageData['pageNumber']);
                $p->setContent($pageData['content']);
                $p->setChapterTitle($pageData['chapterTitle']);
                $userBook->addPage($p);
            }

            $this->em->persist($userBook);
            $pushed++;
        }

        $this->em->flush();

        return $this->json([
            'title'         => $data['title'],
            'author'        => $data['author'],
            'pushedToUsers' => $pushed,
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/global-books/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $globalBook = $this->em->getRepository(GlobalBook::class)->find($id);
        if ($globalBook === null) {
            throw new NotFoundHttpException('Global book not found.');
        }

        $this->em->remove($globalBook);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/global-books', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $books = $this->em->getRepository(GlobalBook::class)->findBy([], ['id' => 'DESC']);

        return $this->json(array_map(fn(GlobalBook $b) => $this->normalize($b), $books));
    }

    #[Route('/api/global-books/{id}/claim', methods: ['POST'])]
    public function claim(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $globalBook = $this->em->getRepository(GlobalBook::class)->find($id);
        if ($globalBook === null) {
            throw new NotFoundHttpException('Global book not found.');
        }

        $existing = $this->em->getRepository(Book::class)->findOneBy([
            'user'             => $user,
            'originalFilename' => $globalBook->getOriginalFilename(),
        ]);
        if ($existing !== null) {
            throw new ConflictHttpException('You have already added this book to your library.');
        }

        $book = new Book();
        $book->setUser($user);
        $book->setTitle($globalBook->getTitle());
        $book->setAuthor($globalBook->getAuthor());
        $book->setFormat($globalBook->getFormat());
        $book->setTotalPages($globalBook->getTotalPages());
        $book->setTotalWords($globalBook->getTotalWords());
        $book->setOriginalFilename($globalBook->getOriginalFilename());

        foreach ($globalBook->getPages() as $gp) {
            $page = new Page();
            $page->setPageNumber($gp->getPageNumber());
            $page->setContent($gp->getContent());
            $page->setChapterTitle($gp->getChapterTitle());
            $book->addPage($page);
        }

        $this->em->persist($book);
        $this->em->flush();

        return $this->json([
            'id'               => $book->getId(),
            'title'            => $book->getTitle(),
            'author'           => $book->getAuthor(),
            'format'           => $book->getFormat(),
            'originalFilename' => $book->getOriginalFilename(),
            'totalPages'       => $book->getTotalPages(),
            'uploadedAt'       => $book->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    private function normalize(GlobalBook $book): array
    {
        return [
            'id'               => $book->getId(),
            'title'            => $book->getTitle(),
            'author'           => $book->getAuthor(),
            'format'           => $book->getFormat(),
            'totalPages'       => $book->getTotalPages(),
            'totalWords'       => $book->getTotalWords(),
            'originalFilename' => $book->getOriginalFilename(),
            'uploadedAt'       => $book->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
