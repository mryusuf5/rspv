<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Book;
use App\Entity\Font;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class CurrentUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addUserFilter($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addUserFilter($queryBuilder, $resourceClass);
    }

    private function addUserFilter(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if ($resourceClass === Book::class) {
            // Show admin-uploaded books to everyone, plus the user's own books
            $queryBuilder
                ->join(sprintf('%s.user', $rootAlias), 'book_owner')
                ->andWhere('book_owner.roles LIKE :admin_role OR ' . sprintf('%s.user = :current_user', $rootAlias))
                ->setParameter('admin_role', '%ROLE_ADMIN%')
                ->setParameter('current_user', $user);
            return;
        }

        if ($resourceClass === Font::class) {
            $queryBuilder
                ->andWhere(sprintf('%s.user = :current_user', $rootAlias))
                ->setParameter('current_user', $user);
        }
    }
}
