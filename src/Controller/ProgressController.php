<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\UserBookProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
class ProgressController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(int $bookId, Request $request): UserBookProgress
    {
        /** @var User $user */
        $user = $this->getUser();

        $book = $this->entityManager->find(Book::class, $bookId);
        if ($book === null) {
            throw new NotFoundHttpException(sprintf('Book %d not found.', $bookId));
        }

        $progressRepo = $this->entityManager->getRepository(UserBookProgress::class);

        if ($request->isMethod('GET')) {
            $progress = $progressRepo->findOneBy(['user' => $user, 'book' => $book]);

            if ($progress === null) {
                // Return a default (unsaved) progress so the client gets a consistent shape
                $progress = new UserBookProgress();
                $progress->setUser($user);
                $progress->setBook($book);
            }

            return $progress;
        }

        // PUT — upsert progress
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        if (!isset($data['pageNumber'], $data['wordIndex'])) {
            throw new BadRequestHttpException('Both "pageNumber" and "wordIndex" fields are required.');
        }

        $pageNumber = (int) $data['pageNumber'];
        $wordIndex  = (int) $data['wordIndex'];

        if ($pageNumber < 1) {
            throw new BadRequestHttpException('"pageNumber" must be at least 1.');
        }

        if ($wordIndex < 0) {
            throw new BadRequestHttpException('"wordIndex" must be 0 or greater.');
        }

        $progress = $progressRepo->findOneBy(['user' => $user, 'book' => $book]);

        if ($progress === null) {
            $progress = new UserBookProgress();
            $progress->setUser($user);
            $progress->setBook($book);
            $this->entityManager->persist($progress);
        }

        $progress->setPageNumber($pageNumber);
        $progress->setWordIndex($wordIndex);
        $progress->touch();

        $this->entityManager->flush();

        return $progress;
    }
}
