<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body.'], 400);
        }

        $name = trim($data['name'] ?? '');
        $plainPassword = $data['password'] ?? '';

        if ($name === '' || $plainPassword === '') {
            return $this->json(['message' => 'Username and password are required.'], 400);
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['name' => $name]);
        if ($existing !== null) {
            return $this->json(['message' => 'That username is already taken.'], 409);
        }

        $user = new User();
        $user->setName($name);
        $user->setPlainPassword($plainPassword);

        $errors = $this->validator->validate($user, null, ['user:write']);
        if (count($errors) > 0) {
            return $this->json(['message' => $errors[0]->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );
        $user->eraseCredentials();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'user' => [
                'id'        => $user->getId(),
                'name'      => $user->getName(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            'token' => $token,
        ], Response::HTTP_CREATED);
    }
}
