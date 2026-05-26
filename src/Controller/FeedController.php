<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Follow;
use App\Entity\User;
use App\Entity\UserBookProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class FeedController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/feed', methods: ['GET'])]
    public function feed(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        // Users I follow (accepted)
        $following = $this->em->createQueryBuilder()
            ->select('IDENTITY(f.following)')
            ->from(Follow::class, 'f')
            ->where('f.follower = :me')
            ->andWhere('f.status = :s')
            ->setParameter('me', $me)
            ->setParameter('s', 'accepted')
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($following)) {
            return $this->json([]);
        }

        // Among those, who also follows me back (mutual)
        $mutuals = $this->em->createQueryBuilder()
            ->select('IDENTITY(f.follower)')
            ->from(Follow::class, 'f')
            ->where('f.following = :me')
            ->andWhere('f.status = :s')
            ->andWhere('f.follower IN (:ids)')
            ->setParameter('me', $me)
            ->setParameter('s', 'accepted')
            ->setParameter('ids', $following)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($mutuals)) {
            return $this->json([]);
        }

        // Latest in-progress book per mutual (not completed)
        $progressList = $this->em->createQueryBuilder()
            ->select('p', 'b', 'u')
            ->from(UserBookProgress::class, 'p')
            ->join('p.book', 'b')
            ->join('p.user', 'u')
            ->where('p.user IN (:ids)')
            ->andWhere('p.pageNumber < b.totalPages')
            ->setParameter('ids', $mutuals)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // One entry per user (most recent)
        $seen = [];
        $feed = [];
        foreach ($progressList as $p) {
            $uid = $p->getUser()->getId();
            if (isset($seen[$uid])) continue;
            $seen[$uid] = true;

            $user = $p->getUser();
            $book = $p->getBook();
            $pct = $book->getTotalPages() > 0
                ? round(($p->getPageNumber() / $book->getTotalPages()) * 100)
                : 0;

            $feed[] = [
                'user' => [
                    'id'        => $user->getId(),
                    'name'      => $user->getName(),
                    'avatarUrl' => $user->getAvatarPath()
                        ? '/api/users/' . $user->getId() . '/avatar'
                        : null,
                ],
                'book' => [
                    'id'         => $book->getId(),
                    'title'      => $book->getTitle(),
                    'author'     => $book->getAuthor(),
                    'format'     => $book->getFormat(),
                    'totalPages' => $book->getTotalPages(),
                ],
                'progress' => [
                    'pageNumber' => $p->getPageNumber(),
                    'pct'        => $pct,
                    'updatedAt'  => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ],
            ];
        }

        return $this->json($feed);
    }
}
