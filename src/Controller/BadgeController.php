<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserBadge;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/badges')]
class BadgeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('', methods: ['POST'])]
    public function award(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $badgeId = trim((string) ($data['badgeId'] ?? ''));

        if ($badgeId === '') {
            throw new BadRequestHttpException('"badgeId" is required.');
        }

        $repo = $this->entityManager->getRepository(UserBadge::class);
        $existing = $repo->findOneBy(['user' => $user, 'badgeId' => $badgeId]);

        if ($existing !== null) {
            return $this->json(['badgeId' => $existing->getBadgeId(), 'earnedAt' => $existing->getEarnedAt()->format(\DateTimeInterface::ATOM)]);
        }

        $badge = new UserBadge();
        $badge->setUser($user);
        $badge->setBadgeId($badgeId);

        if (isset($data['earnedAt'])) {
            try {
                $badge->setEarnedAt(new \DateTimeImmutable($data['earnedAt']));
            } catch (\Exception) {}
        }

        $this->entityManager->persist($badge);
        $this->entityManager->flush();

        return $this->json(
            ['badgeId' => $badge->getBadgeId(), 'earnedAt' => $badge->getEarnedAt()->format(\DateTimeInterface::ATOM)],
            Response::HTTP_CREATED,
        );
    }
}
