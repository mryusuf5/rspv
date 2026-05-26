<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Font;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FontService
{
    private const ALLOWED_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2'];

    private const MIME_TYPES = [
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire(env: 'APP_SHARE_DIR')]
        private readonly string $shareDir,
    ) {}

    public function store(UploadedFile $file, User $user): Font
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException(sprintf(
                'Unsupported font format "%s". Allowed formats: %s.',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        $originalFilename = $file->getClientOriginalName();

        $storageDir = sprintf('%s/%s/fonts/%d', $this->projectDir, $this->shareDir, $user->getId());

        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            throw new \RuntimeException(sprintf('Could not create font storage directory: %s', $storageDir));
        }

        $storedFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $file->move($storageDir, $storedFilename);

        $relativePath = sprintf('%s/fonts/%d/%s', $this->shareDir, $user->getId(), $storedFilename);

        $font = new Font();
        $font->setUser($user);
        $font->setOriginalFilename($originalFilename);
        $font->setDisplayName($this->deriveDisplayName($originalFilename));
        $font->setFormat($extension);
        $font->setFilePath($relativePath);

        $this->entityManager->persist($font);
        $this->entityManager->flush();

        return $font;
    }

    public function getAbsolutePath(Font $font): string
    {
        return $this->projectDir . '/' . $font->getFilePath();
    }

    public function getMimeType(Font $font): string
    {
        return self::MIME_TYPES[$font->getFormat()] ?? 'application/octet-stream';
    }

    private function deriveDisplayName(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = str_replace(['-', '_'], ' ', $name);
        return ucwords($name);
    }
}
