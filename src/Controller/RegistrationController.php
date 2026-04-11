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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
            throw new BadRequestHttpException('Invalid JSON body.');
        }

        $name = trim($data['name'] ?? '');
        $plainPassword = $data['password'] ?? '';

        if ($name === '' || $plainPassword === '') {
            throw new BadRequestHttpException('Both "name" and "password" fields are required.');
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['name' => $name]);
        if ($existing !== null) {
            throw new ConflictHttpException('This name is already taken.');
        }

        $user = new User();
        $user->setName($name);
        $user->setPlainPassword($plainPassword);

        $errors = $this->validator->validate($user, null, ['user:write']);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
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
