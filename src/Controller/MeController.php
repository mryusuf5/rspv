<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Follow;
use App\Entity\User;
use App\Entity\UserBookProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class MeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->normalizeMe($user));
    }

    private function normalizeMe(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'name'      => $user->getName(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isPrivate' => $user->isPrivate(),
            'avatarUrl' => $user->getAvatarPath() ? '/api/users/' . $user->getId() . '/avatar' : null,
            'bio'       => $user->getBio(),
            'font'      => $user->getFont(),
            'theme'     => $user->getTheme(),
        ];
    }

    #[Route('/api/me/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $followersCount = $this->em->createQueryBuilder()
            ->select('COUNT(f)')
            ->from(Follow::class, 'f')
            ->where('f.following = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        $followingCount = $this->em->createQueryBuilder()
            ->select('COUNT(f)')
            ->from(Follow::class, 'f')
            ->where('f.follower = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        $booksCount = $this->em->createQueryBuilder()
            ->select('COUNT(b)')
            ->from(Book::class, 'b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $completedCount = $this->em->createQueryBuilder()
            ->select('COUNT(p)')
            ->from(UserBookProgress::class, 'p')
            ->join('p.book', 'b')
            ->where('p.user = :user')
            ->andWhere('p.pageNumber >= b.totalPages')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $readToday = $this->em->createQueryBuilder()
            ->select('COUNT(p)')
            ->from(UserBookProgress::class, 'p')
            ->where('p.user = :user')
            ->andWhere('p.updatedAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable($today))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'followersCount' => (int) $followersCount,
            'followingCount' => (int) $followingCount,
            'booksCount'     => (int) $booksCount,
            'completedCount' => (int) $completedCount,
            'readToday'      => (int) $readToday > 0,
        ]);
    }

    #[Route('/api/me/preferences', methods: ['PATCH'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];
        if (array_key_exists('font', $data)) {
            $user->setFont($data['font'] ?: null);
        }
        if (array_key_exists('theme', $data)) {
            $user->setTheme($data['theme'] ?: null);
        }
        $this->em->flush();

        return $this->json($this->normalizeMe($user));
    }

    #[Route('/api/me/bio', methods: ['PATCH'])]
    public function updateBio(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $bio = isset($data['bio']) ? (trim($data['bio']) ?: null) : $user->getBio();

        $user->setBio($bio);
        $this->em->flush();

        return $this->json($this->normalizeMe($user));
    }

    #[Route('/api/me/privacy', methods: ['PATCH'])]
    public function updatePrivacy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !array_key_exists('isPrivate', $data)) {
            return $this->json(['error' => 'isPrivate field required'], 400);
        }

        $user->setIsPrivate((bool) $data['isPrivate']);
        $this->em->flush();

        return $this->json($this->normalizeMe($user));
    }

}
