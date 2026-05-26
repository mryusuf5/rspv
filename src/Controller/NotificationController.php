<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $notifications = $this->em->createQueryBuilder()
            ->select('n', 'a')
            ->from(Notification::class, 'n')
            ->join('n.actor', 'a')
            ->where('n.recipient = :me')
            ->andWhere('n.dismissedAt IS NULL')
            ->setParameter('me', $me)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(Notification $n) => [
            'id'          => $n->getId(),
            'type'        => $n->getType(),
            'actor'       => ['id' => $n->getActor()->getId(), 'name' => $n->getActor()->getName()],
            'referenceId' => $n->getReferenceId(),
            'createdAt'   => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $notifications));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function dismiss(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $notif = $this->em->find(Notification::class, $id);
        if (!$notif || $notif->getRecipient()->getId() !== $me->getId()) {
            throw new NotFoundHttpException('Notification not found.');
        }

        $notif->setDismissedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['status' => 'dismissed']);
    }
}
