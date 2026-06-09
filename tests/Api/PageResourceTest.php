<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Book;
use App\Entity\Page;
use App\Entity\User;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Epic;
use Qameta\Allure\Attribute\Feature;
use Qameta\Allure\Attribute\Severity;

#[Epic('RSVP Reading API')]
#[Feature('Pages')]
final class PageResourceTest extends AbstractApiTestCase
{
    #[DisplayName('GET /books/{id}/pages returns all pages in order')]
    #[Severity(Severity::CRITICAL)]
    public function testListPagesForBook(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);
        $this->persistPage($book, 1, 'first');
        $this->persistPage($book, 2, 'second');
        $this->persistPage($book, 3, 'third');

        $response = $this->authenticatedClient($user)
            ->request('GET', '/api/books/'.$book->getId().'/pages')
            ->toArray();

        self::assertCount(3, $response['member']);

        $numbers = array_map(static fn (array $p) => $p['pageNumber'], $response['member']);
        sort($numbers);
        self::assertSame([1, 2, 3], $numbers);

        // wordCount should reflect setContent() computation
        foreach ($response['member'] as $p) {
            self::assertSame(1, $p['wordCount']);
        }
    }

    #[DisplayName('GET /books/{id}/pages/{pageNumber} returns the right page')]
    #[Severity(Severity::CRITICAL)]
    public function testGetSinglePageByPageNumber(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);
        $this->persistPage($book, 1, 'cover words');
        $this->persistPage($book, 67, '67676767');

        $response = $this->authenticatedClient($user)
            ->request('GET', '/api/books/'.$book->getId().'/pages/67')
            ->toArray();

        self::assertSame(67, $response['pageNumber']);
        self::assertSame('67676767', $response['content']);
        // str_word_count() counts only alphabetic words, so a digit-only string is 0.
        self::assertSame(0, $response['wordCount']);
    }

    #[DisplayName('Requesting an unknown page returns 404')]
    #[Severity(Severity::NORMAL)]
    public function testGetUnknownPageReturns404(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);
        $this->persistPage($book, 1, 'only page');

        $this->authenticatedClient($user)
            ->request('GET', '/api/books/'.$book->getId().'/pages/99');

        self::assertResponseStatusCodeSame(404);
    }

    #[DisplayName('Listing pages requires authentication')]
    #[Severity(Severity::CRITICAL)]
    public function testListPagesRequiresAuth(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        static::createClient()->request('GET', '/api/books/'.$book->getId().'/pages');
        self::assertResponseStatusCodeSame(401);
    }

    private function persistBook(User $user): Book
    {
        $book = (new Book())
            ->setUser($user)
            ->setTitle('A book')
            ->setOriginalFilename('a-book.pdf')
            ->setFormat('pdf');

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }

    private function persistPage(Book $book, int $number, string $content): void
    {
        $page = new Page();
        $page->setBook($book);
        $page->setPageNumber($number);
        $page->setContent($content);

        $this->em->persist($page);
        $book->setTotalPages(max($book->getTotalPages(), $number));
        $this->em->flush();
    }
}
