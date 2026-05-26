<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Follow;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class FollowController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** Follow a user (or send a request if their profile is private). */
    #[Route('/follow/{userId}', methods: ['POST'], requirements: ['userId' => '\d+'])]
    public function follow(int $userId): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        if ($me->getId() === $userId) {
            return $this->json(['error' => 'Cannot follow yourself'], 400);
        }

        $target = $this->em->find(User::class, $userId);
        if (!$target) {
            throw new NotFoundHttpException('User not found.');
        }

        $existing = $this->em->getRepository(Follow::class)->findOneBy([
            'follower' => $me,
            'following' => $target,
        ]);

        if ($existing) {
            return $this->json(['status' => $existing->getStatus(), 'followId' => $existing->getId()]);
        }

        $follow = new Follow();
        $follow->setFollower($me);
        $follow->setFollowing($target);
        $follow->setStatus($target->isPrivate() ? 'pending' : 'accepted');

        $this->em->persist($follow);
        $this->em->flush();

        $notif = new Notification();
        $notif->setRecipient($target);
        $notif->setActor($me);
        $notif->setType($target->isPrivate() ? 'follow_request' : 'new_follower');
        $notif->setReferenceId($follow->getId());
        $this->em->persist($notif);
        $this->em->flush();

        return $this->json(['status' => $follow->getStatus(), 'followId' => $follow->getId()], 201);
    }

    /** Unfollow a user (also cancels a pending request). */
    #[Route('/follow/{userId}', methods: ['DELETE'], requirements: ['userId' => '\d+'])]
    public function unfollow(int $userId): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $target = $this->em->find(User::class, $userId);
        if (!$target) {
            throw new NotFoundHttpException('User not found.');
        }

        $follow = $this->em->getRepository(Follow::class)->findOneBy([
            'follower' => $me,
            'following' => $target,
        ]);

        if (!$follow) {
            return $this->json(['status' => 'not_following']);
        }

        $this->em->remove($follow);
        $this->em->flush();

        return $this->json(['status' => 'unfollowed']);
    }

    /** Accept a pending follow request. */
    #[Route('/follows/{id}/accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accept(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $follow = $this->em->find(Follow::class, $id);
        if (!$follow || $follow->getFollowing()->getId() !== $me->getId()) {
            throw new NotFoundHttpException('Follow request not found.');
        }

        if ($follow->getStatus() !== 'pending') {
            return $this->json(['error' => 'Not a pending request'], 400);
        }

        $follow->setStatus('accepted');

        // Dismiss the follow_request notification
        $reqNotif = $this->em->getRepository(Notification::class)->findOneBy([
            'recipient' => $me,
            'type' => 'follow_request',
            'referenceId' => $id,
        ]);
        if ($reqNotif && !$reqNotif->getDismissedAt()) {
            $reqNotif->setDismissedAt(new \DateTimeImmutable());
        }

        // Notify the follower that their request was accepted
        $acceptedNotif = new Notification();
        $acceptedNotif->setRecipient($follow->getFollower());
        $acceptedNotif->setActor($me);
        $acceptedNotif->setType('follow_accepted');
        $acceptedNotif->setReferenceId($id);
        $this->em->persist($acceptedNotif);
        $this->em->flush();

        return $this->json(['status' => 'accepted']);
    }

    /** Deny a pending follow request. */
    #[Route('/follows/{id}/deny', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deny(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $follow = $this->em->find(Follow::class, $id);
        if (!$follow || $follow->getFollowing()->getId() !== $me->getId()) {
            throw new NotFoundHttpException('Follow request not found.');
        }

        // Dismiss associated notification
        $reqNotif = $this->em->getRepository(Notification::class)->findOneBy([
            'recipient' => $me,
            'type' => 'follow_request',
            'referenceId' => $id,
        ]);
        if ($reqNotif) {
            $reqNotif->setDismissedAt(new \DateTimeImmutable());
        }

        $this->em->remove($follow);
        $this->em->flush();

        return $this->json(['status' => 'denied']);
    }

    /** My follow status with a specific user. */
    #[Route('/follow/{userId}/status', methods: ['GET'], requirements: ['userId' => '\d+'])]
    public function status(int $userId): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $target = $this->em->find(User::class, $userId);
        if (!$target) {
            throw new NotFoundHttpException('User not found.');
        }

        $iFollow = $this->em->getRepository(Follow::class)->findOneBy([
            'follower' => $me,
            'following' => $target,
        ]);

        $theyFollow = $this->em->getRepository(Follow::class)->findOneBy([
            'follower' => $target,
            'following' => $me,
            'status' => 'accepted',
        ]);

        return $this->json([
            'following' => $iFollow?->getStatus() ?? null,
            'followId' => $iFollow?->getId() ?? null,
            'followedBy' => $theyFollow !== null,
        ]);
    }

    /** Paginated list of people who follow me (accepted). */
    #[Route('/me/followers', methods: ['GET'])]
    public function myFollowers(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $follows = $this->em->createQueryBuilder()
            ->select('f', 'u')
            ->from(Follow::class, 'f')
            ->join('f.follower', 'u')
            ->where('f.following = :me')
            ->andWhere('f.status = :status')
            ->setParameter('me', $me)
            ->setParameter('status', 'accepted')
            ->orderBy('u.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(Follow $f) => [
            'id'          => $f->getFollower()->getId(),
            'name'        => $f->getFollower()->getName(),
            'isPrivate'   => $f->getFollower()->isPrivate(),
            'avatarUrl'   => $f->getFollower()->getAvatarPath() ? '/api/users/' . $f->getFollower()->getId() . '/avatar' : null,
            'followedSince' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $follows));
    }

    /** Paginated list of people I follow (accepted). */
    #[Route('/me/following', methods: ['GET'])]
    public function myFollowing(\Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $follows = $this->em->createQueryBuilder()
            ->select('f', 'u')
            ->from(Follow::class, 'f')
            ->join('f.following', 'u')
            ->where('f.follower = :me')
            ->andWhere('f.status = :status')
            ->setParameter('me', $me)
            ->setParameter('status', 'accepted')
            ->orderBy('u.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(Follow $f) => [
            'id'          => $f->getFollowing()->getId(),
            'name'        => $f->getFollowing()->getName(),
            'isPrivate'   => $f->getFollowing()->isPrivate(),
            'avatarUrl'   => $f->getFollowing()->getAvatarPath() ? '/api/users/' . $f->getFollowing()->getId() . '/avatar' : null,
            'followedSince' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $follows));
    }

    /** People I follow (accepted), first 50. */
    #[Route('/friends', methods: ['GET'])]
    public function friends(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();

        $follows = $this->em->createQueryBuilder()
            ->select('f', 'u')
            ->from(Follow::class, 'f')
            ->join('f.following', 'u')
            ->where('f.follower = :me')
            ->andWhere('f.status = :status')
            ->setParameter('me', $me)
            ->setParameter('status', 'accepted')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(Follow $f) => [
            'id'           => $f->getFollowing()->getId(),
            'name'         => $f->getFollowing()->getName(),
            'createdAt'    => $f->getFollowing()->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isPrivate'    => $f->getFollowing()->isPrivate(),
            'followedSince' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $follows));
    }
}
