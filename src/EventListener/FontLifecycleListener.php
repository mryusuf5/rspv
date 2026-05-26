<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Font;
use App\Service\FontService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preRemove)]
class FontLifecycleListener
{
    public function __construct(
        private readonly FontService $fontService,
    ) {}

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Font) {
            return;
        }

        $absolutePath = $this->fontService->getAbsolutePath($entity);

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }
}
