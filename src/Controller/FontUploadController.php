<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Font;
use App\Entity\User;
use App\Service\FontService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AsController]
class FontUploadController extends AbstractController
{
    public function __construct(
        private readonly FontService $fontService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded. Please provide a "file" field in the multipart form data.');
        }

        if (!$file->isValid()) {
            throw new BadRequestHttpException(sprintf('File upload failed: %s', $file->getErrorMessage()));
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $font = $this->fontService->store($file, $user);
        } catch (\RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json($this->normalize($font), Response::HTTP_CREATED);
    }

    private function normalize(Font $font): array
    {
        return [
            'id'               => $font->getId(),
            'displayName'      => $font->getDisplayName(),
            'originalFilename' => $font->getOriginalFilename(),
            'format'           => $font->getFormat(),
            'uploadedAt'       => $font->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
