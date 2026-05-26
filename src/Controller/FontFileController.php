<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Font;
use App\Entity\User;
use App\Service\FontService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsController]
class FontFileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FontService $fontService,
    ) {}

    public function __invoke(int $id): BinaryFileResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $font = $this->entityManager->find(Font::class, $id);

        if ($font === null || $font->getUser()->getId() !== $user->getId()) {
            throw new NotFoundHttpException(sprintf('Font %d not found.', $id));
        }

        $absolutePath = $this->fontService->getAbsolutePath($font);

        if (!is_file($absolutePath)) {
            throw new NotFoundHttpException('Font file not found on server.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $this->fontService->getMimeType($font));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $font->getOriginalFilename());

        return $response;
    }
}
