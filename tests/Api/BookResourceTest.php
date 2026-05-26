<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Book;
use App\Entity\User;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Epic;
use Qameta\Allure\Attribute\Feature;
use Qameta\Allure\Attribute\Severity;

#[Epic('RSVP Reading API')]
#[Feature('Books')]
final class BookResourceTest extends AbstractApiTestCase
{
    #[DisplayName('Listing books requires authentication')]
    #[Severity(Severity::CRITICAL)]
    public function testListRequiresAuth(): void
    {
        static::createClient()->request('GET', '/api/books');
        self::assertResponseStatusCodeSame(401);
    }

    #[DisplayName('List shows only my books plus admin-uploaded books')]
    #[Severity(Severity::CRITICAL)]
    public function testListReturnsOnlyOwnAndAdminBooks(): void
    {
        $owner = $this->createUser('yusuf');
        $other = $this->createUser('max');
        $admin = $this->createUser('root', ['ROLE_ADMIN']);

        $this->persistBook($owner, 'yusuf book');
        $this->persistBook($other, 'max book');
        $this->persistBook($admin, 'Admin book');

        $response = $this->authenticatedClient($owner)
            ->request('GET', '/api/books')
            ->toArray();

        $titles = array_map(static fn (array $b) => $b['title'], $response['member']);
        sort($titles);
        self::assertSame(['Admin book', 'yusuf book'], $titles);
    }

    #[DisplayName('GET /books/{id} returns metadata for the owner')]
    #[Severity(Severity::NORMAL)]
    public function testGetOwnBookReturnsMetadata(): void
    {
        $owner = $this->createUser('yusuf');
        $book = $this->persistBook($owner, 'Mine', 'pdf', 7);

        $response = $this->authenticatedClient($owner)
            ->request('GET', '/api/books/'.$book->getId())
            ->toArray();

        self::assertSame('Mine', $response['title']);
        self::assertSame('pdf', $response['format']);
        self::assertSame(7, $response['totalPages']);
        // Pages are not embedded — they live behind /books/{id}/pages.
    }

    #[DisplayName('Cannot read another user\'s book (404)')]
    #[Severity(Severity::CRITICAL)]
    public function testCannotGetOtherUsersBook(): void
    {
        $owner = $this->createUser('yusuf');
        $other = $this->createUser('max');
        $book = $this->persistBook($owner, 'Secret');

        $this->authenticatedClient($other)->request('GET', '/api/books/'.$book->getId());

        // CurrentUserExtension filters it out → 404 (not visible)
        self::assertResponseStatusCodeSame(404);
    }

    #[DisplayName('Owner can delete their book')]
    #[Severity(Severity::CRITICAL)]
    public function testDeleteOwnBook(): void
    {
        $owner = $this->createUser('yusuf');
        $book = $this->persistBook($owner, 'Goodbye');
        $id = $book->getId();

        $this->authenticatedClient($owner)->request('DELETE', '/api/books/'.$id);
        self::assertResponseStatusCodeSame(204);

        $this->em->clear();
        self::assertNull($this->em->find(Book::class, $id));
    }

    #[DisplayName('Cannot delete another user\'s book')]
    #[Severity(Severity::CRITICAL)]
    public function testCannotDeleteOtherUsersBook(): void
    {
        $owner = $this->createUser('yusuf');
        $other = $this->createUser('max');
        $book = $this->persistBook($owner, 'Mine');

        $this->authenticatedClient($other)->request('DELETE', '/api/books/'.$book->getId());

        // Not visible to other user → 404 (CurrentUserExtension filters before security runs)
        self::assertResponseStatusCodeSame(404);
    }

    private function persistBook(User $user, string $title, string $format = 'pdf', int $totalPages = 0): Book
    {
        $book = (new Book())
            ->setUser($user)
            ->setTitle($title)
            ->setOriginalFilename(strtolower($title).'.'.$format)
            ->setFormat($format)
            ->setTotalPages($totalPages);

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    private function persistPage(Book $book, int $number, string $content): void
    {
        $page = new \App\Entity\Page();
        $page->setBook($book);
        $page->setPageNumber($number);
        $page->setContent($content);

        $this->em->persist($page);
        $book->setTotalPages(max($book->getTotalPages(), $number));
        $this->em->flush();
    }
}
