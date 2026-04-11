<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookProcessorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AsController]
class BookUploadController extends AbstractController
{
    public function __construct(
        private readonly BookProcessorService $bookProcessor,
    ) {}

    public function __invoke(Request $request): mixed
    {
        $file = $request->files->get('file');

        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded. Please provide a "file" field in the multipart form data.');
        }

        if (!$file->isValid()) {
            throw new BadRequestHttpException(sprintf('File upload failed: %s', $file->getErrorMessage()));
        }

        try {
            return $this->bookProcessor->process($file);
        } catch (\RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}
