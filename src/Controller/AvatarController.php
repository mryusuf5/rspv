<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AvatarController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire(env: 'APP_SHARE_DIR')]
        private readonly string $shareDir,
    ) {}

    #[Route('/me/avatar', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            throw new BadRequestHttpException('No valid file uploaded.');
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed, true)) {
            throw new BadRequestHttpException('Unsupported image format.');
        }

        $dir = sprintf('%s/%s/avatars/%d', $this->projectDir, $this->shareDir, $user->getId());
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create avatar directory.');
        }

        if ($user->getAvatarPath()) {
            $old = $this->projectDir . '/' . $user->getAvatarPath();
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $filename = sprintf('avatar.%s', $ext);
        $file->move($dir, $filename);

        $relativePath = sprintf('%s/avatars/%d/%s', $this->shareDir, $user->getId(), $filename);
        $user->setAvatarPath($relativePath);
        $this->em->flush();

        return $this->json($this->normalize($user));
    }

    #[Route('/me/avatar', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getAvatarPath()) {
            $path = $this->projectDir . '/' . $user->getAvatarPath();
            if (file_exists($path)) {
                unlink($path);
            }
            $user->setAvatarPath(null);
            $this->em->flush();
        }

        return $this->json($this->normalize($user));
    }

    private function normalize(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'name'      => $user->getName(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'isPrivate' => $user->isPrivate(),
            'avatarUrl' => $user->getAvatarPath() ? '/api/users/' . $user->getId() . '/avatar' : null,
        ];
    }
}
