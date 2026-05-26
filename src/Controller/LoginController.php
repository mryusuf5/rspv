<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/api/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->json(['message' => 'Username and password are required.'], 400);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['name' => $username]);
        if ($user === null) {
            return $this->json(['message' => 'No account found with that username.'], 401);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Incorrect password.'], 401);
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'user' => [
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'isPrivate' => $user->isPrivate(),
                'avatarUrl' => $user->getAvatarPath() ? '/api/users/' . $user->getId() . '/avatar' : null,
                'bio'       => $user->getBio(),
                'font'      => $user->getFont(),
                'theme'     => $user->getTheme(),
                'isAdmin'   => in_array('ROLE_ADMIN', $user->getRoles(), true),
            ],
            'token' => $token,
        ]);
    }
}
