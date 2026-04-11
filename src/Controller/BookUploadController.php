<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Service\BookProcessorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AsController]
class BookUploadController extends AbstractController
{
    public function __construct(
        private readonly BookProcessorService $bookProcessor,
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
            $book = $this->bookProcessor->process($file, $user);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'already uploaded')) {
                throw new ConflictHttpException($e->getMessage(), $e);
            }

            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json($this->normalize($book), Response::HTTP_CREATED);
    }

    private function normalize(Book $book): array
    {
        return [
            'id'               => $book->getId(),
            'title'            => $book->getTitle(),
            'author'           => $book->getAuthor(),
            'format'           => $book->getFormat(),
            'originalFilename' => $book->getOriginalFilename(),
            'totalPages'       => $book->getTotalPages(),
            'uploadedAt'       => $book->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
