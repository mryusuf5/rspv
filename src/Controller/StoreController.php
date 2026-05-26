<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StoreFontItem;
use App\Entity\User;
use App\Entity\UserPurchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class StoreController extends AbstractController
{
    private const ALLOWED_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2'];
    private const MIME_TYPES = [
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire(env: 'APP_SHARE_DIR')]
        private readonly string $shareDir,
    ) {}

    #[Route('/api/store/fonts', methods: ['GET'])]
    public function listFonts(): JsonResponse
    {
        /** @var User $user */
        $user      = $this->getUser();
        $isAdmin   = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $purchased = $this->getPurchasedIds($user, 'font');

        $fonts = $this->em->getRepository(StoreFontItem::class)->findBy([], ['id' => 'ASC']);

        $result = [];
        foreach ($fonts as $f) {
            $isPurchased = in_array((string) $f->getId(), $purchased, true);
            if (!$f->isActive() && !$isAdmin && !$isPurchased) {
                continue;
            }
            $result[] = [
                ...$this->normalizeFont($f),
                'purchased' => $isPurchased,
                'isActive'  => $f->isActive(),
            ];
        }

        return $this->json($result);
    }

    #[Route('/api/store/theme-configs', methods: ['GET'])]
    public function listThemeConfigs(): JsonResponse
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT theme_id, is_active FROM store_theme_configs'
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['theme_id']] = (bool) $row['is_active'];
        }
        return $this->json($map);
    }

    #[Route('/api/admin/store/themes/{themeId}/toggle', methods: ['POST'])]
    public function adminToggleTheme(string $themeId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $conn = $this->em->getConnection();
        $existing = $conn->fetchOne(
            'SELECT is_active FROM store_theme_configs WHERE theme_id = ?',
            [$themeId]
        );

        if ($existing === false) {
            $conn->executeStatement(
                'INSERT INTO store_theme_configs (theme_id, is_active) VALUES (?, 0)',
                [$themeId]
            );
            $newActive = false;
        } else {
            $newActive = !(bool) $existing;
            $conn->executeStatement(
                'UPDATE store_theme_configs SET is_active = ? WHERE theme_id = ?',
                [(int) $newActive, $themeId]
            );
        }

        return $this->json(['themeId' => $themeId, 'isActive' => $newActive]);
    }

    #[Route('/api/store/purchases', methods: ['GET'])]
    public function listPurchases(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchases = $this->em->getRepository(UserPurchase::class)->findBy(['user' => $user]);

        return $this->json(array_map(fn(UserPurchase $p) => [
            'itemType'    => $p->getItemType(),
            'itemId'      => $p->getItemId(),
            'purchasedAt' => $p->getPurchasedAt()->format(\DateTimeInterface::ATOM),
        ], $purchases));
    }

    #[Route('/api/store/purchase', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data     = json_decode($request->getContent(), true) ?? [];
        $itemType = $data['itemType'] ?? null;
        $itemId   = (string) ($data['itemId'] ?? '');

        if (!in_array($itemType, ['theme', 'font'], true) || $itemId === '') {
            throw new BadRequestHttpException('itemType (theme|font) and itemId are required.');
        }

        if ($itemType === 'font') {
            $font = $this->em->find(StoreFontItem::class, (int) $itemId);
            if ($font === null) {
                throw new NotFoundHttpException('Font not found.');
            }
        }

        $existing = $this->em->getRepository(UserPurchase::class)->findOneBy([
            'user'     => $user,
            'itemType' => $itemType,
            'itemId'   => $itemId,
        ]);

        if ($existing !== null) {
            throw new ConflictHttpException('Already purchased.');
        }

        $purchase = new UserPurchase();
        $purchase->setUser($user);
        $purchase->setItemType($itemType);
        $purchase->setItemId($itemId);

        $this->em->persist($purchase);
        $this->em->flush();

        return $this->json([
            'itemType'    => $purchase->getItemType(),
            'itemId'      => $purchase->getItemId(),
            'purchasedAt' => $purchase->getPurchasedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/store/fonts/{id}/file', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function fontFile(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $font = $this->em->find(StoreFontItem::class, $id);
        if ($font === null) {
            throw new NotFoundHttpException('Font not found.');
        }


        $path = $this->projectDir . '/' . $font->getFilePath();
        if (!is_file($path)) {
            throw new NotFoundHttpException('Font file not found on server.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return new Response('Cannot read font file.', 500, ['Content-Type' => 'text/plain']);
        }

        $mime = self::MIME_TYPES[$font->getFormat()] ?? 'application/octet-stream';

        return new Response($content, 200, ['Content-Type' => $mime]);
    }

    #[Route('/api/admin/store/fonts', methods: ['POST'])]
    public function adminUploadFont(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            throw new BadRequestHttpException('No valid file uploaded.');
        }

        $displayName     = trim((string) $request->request->get('displayName', ''));
        $category        = trim((string) $request->request->get('category', ''));
        $originalFilename = $file->getClientOriginalName();

        if ($displayName === '') {
            throw new BadRequestHttpException('displayName is required.');
        }
        if (!in_array($category, ['cartoon', 'techno', 'classic'], true)) {
            throw new BadRequestHttpException('category must be cartoon, techno, or classic.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported format "%s". Allowed: %s.',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        $storageDir = sprintf('%s/%s/store-fonts', $this->projectDir, $this->shareDir);
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            throw new \RuntimeException('Could not create store font directory.');
        }

        $storedFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $file->move($storageDir, $storedFilename);

        $relativePath = sprintf('%s/store-fonts/%s', $this->shareDir, $storedFilename);

        $font = new StoreFontItem();
        $font->setDisplayName($displayName);
        $font->setCategory($category);
        $font->setOriginalFilename($originalFilename);
        $font->setFormat($extension);
        $font->setFilePath($relativePath);

        $this->em->persist($font);
        $this->em->flush();

        return $this->json($this->normalizeFont($font), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/store/fonts/{id}/hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminHideFont(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $font = $this->em->find(StoreFontItem::class, $id);
        if ($font === null) {
            throw new NotFoundHttpException('Font not found.');
        }

        $font->setIsActive(false);
        $this->em->flush();

        return $this->json([...$this->normalizeFont($font), 'isActive' => false]);
    }

    #[Route('/api/admin/store/fonts/{id}/restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function adminRestoreFont(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $font = $this->em->find(StoreFontItem::class, $id);
        if ($font === null) {
            throw new NotFoundHttpException('Font not found.');
        }

        $font->setIsActive(true);
        $this->em->flush();

        return $this->json([...$this->normalizeFont($font), 'isActive' => true]);
    }

    private function normalizeFont(StoreFontItem $font): array
    {
        return [
            'id'               => $font->getId(),
            'displayName'      => $font->getDisplayName(),
            'category'         => $font->getCategory(),
            'originalFilename' => $font->getOriginalFilename(),
            'format'           => $font->getFormat(),
            'uploadedAt'       => $font->getUploadedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function getPurchasedIds(User $user, string $itemType): array
    {
        $rows = $this->em->getRepository(UserPurchase::class)->findBy([
            'user'     => $user,
            'itemType' => $itemType,
        ]);

        return array_map(fn(UserPurchase $p) => $p->getItemId(), $rows);
    }
}
