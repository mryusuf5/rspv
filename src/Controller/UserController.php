<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Follow;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserBookProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    #[Route('', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $name = trim((string) $request->query->get('name', ''));

        if ($name === '') {
            return $this->json([]);
        }

        $users = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('LOWER(u.name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(User $u) => $this->normalizeUser($u), $users));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $user = $this->entityManager->find(User::class, $id);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf('User %d not found.', $id));
        }

        $iFollow = $this->entityManager->getRepository(Follow::class)->findOneBy([
            'follower' => $me,
            'following' => $user,
        ]);
        $theyFollow = $this->entityManager->getRepository(Follow::class)->findOneBy([
            'follower' => $user,
            'following' => $me,
            'status' => 'accepted',
        ]);

        return $this->json([
            ...$this->normalizeUser($user),
            'followStatus' => [
                'following'  => $iFollow?->getStatus() ?? null,
                'followId'   => $iFollow?->getId() ?? null,
                'followedBy' => $theyFollow !== null,
            ],
        ]);
    }

    #[Route('/{id}/books', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function books(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $user = $this->entityManager->find(User::class, $id);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf('User %d not found.', $id));
        }

        if (!$this->canViewPrivateData($me, $user)) {
            throw new AccessDeniedHttpException('This profile is private.');
        }

        $books = $this->entityManager->getRepository(Book::class)
            ->findBy(['user' => $user], ['uploadedAt' => 'DESC']);

        return $this->json(array_map(fn(Book $b) => [
            'id'         => $b->getId(),
            'title'      => $b->getTitle(),
            'author'     => $b->getAuthor(),
            'format'     => $b->getFormat(),
            'totalPages' => $b->getTotalPages(),
            'totalWords' => $b->getTotalWords(),
            'uploadedAt' => $b->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ], $books));
    }

    #[Route('/{id}/progress/{bookId}', methods: ['GET'], requirements: ['id' => '\d+', 'bookId' => '\d+'])]
    public function progress(int $id, int $bookId): JsonResponse
    {
        $user = $this->entityManager->find(User::class, $id);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf('User %d not found.', $id));
        }

        $book = $this->entityManager->find(Book::class, $bookId);
        if ($book === null) {
            throw new NotFoundHttpException(sprintf('Book %d not found.', $bookId));
        }

        $progress = $this->entityManager->getRepository(UserBookProgress::class)
            ->findOneBy(['user' => $user, 'book' => $book]);

        if ($progress === null) {
            return $this->json(['id' => null, 'pageNumber' => 1, 'wordIndex' => 0]);
        }

        return $this->json([
            'id'         => $progress->getId(),
            'pageNumber' => $progress->getPageNumber(),
            'wordIndex'  => $progress->getWordIndex(),
            'updatedAt'  => $progress->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}/badges', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function badges(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $user = $this->entityManager->find(User::class, $id);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf('User %d not found.', $id));
        }

        if (!$this->canViewPrivateData($me, $user)) {
            throw new AccessDeniedHttpException('This profile is private.');
        }

        $badges = $this->entityManager->getRepository(UserBadge::class)
            ->findBy(['user' => $user], ['earnedAt' => 'ASC']);

        return $this->json(array_map(fn(UserBadge $b) => [
            'badgeId'  => $b->getBadgeId(),
            'earnedAt' => $b->getEarnedAt()->format(\DateTimeInterface::ATOM),
        ], $badges));
    }

    #[Route('/{id}/avatar', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function avatar(int $id): Response
    {
        $user = $this->entityManager->find(User::class, $id);
        if ($user === null || $user->getAvatarPath() === null) {
            throw new NotFoundHttpException('No avatar.');
        }

        $path = $this->projectDir . '/' . $user->getAvatarPath();
        if (!file_exists($path)) {
            throw new NotFoundHttpException('Avatar file not found.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return new Response('Cannot read file: ' . $path, 500, ['Content-Type' => 'text/plain']);
        }
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        return new Response($content, 200, ['Content-Type' => $mimeType]);
    }

    private function normalizeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'name'      => $user->getName(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isPrivate' => $user->isPrivate(),
            'avatarUrl' => $user->getAvatarPath() ? '/api/users/' . $user->getId() . '/avatar' : null,
            'bio'       => $user->getBio(),
            'font'      => $user->getFont() ?? 'inter',
            'theme'     => $user->getTheme() ?? 'basic',
        ];
    }

    private function canViewPrivateData(User $me, User $target): bool
    {
        if ($me->getId() === $target->getId()) return true;
        if (in_array('ROLE_ADMIN', $me->getRoles(), true)) return true;
        if (!$target->isPrivate()) return true;

        $follow = $this->entityManager->getRepository(Follow::class)->findOneBy([
            'follower' => $me,
            'following' => $target,
            'status' => 'accepted',
        ]);

        return $follow !== null;
    }
}
