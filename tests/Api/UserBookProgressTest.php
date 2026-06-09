<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Book;
use App\Entity\User;
use App\Entity\UserBookProgress;
use Qameta\Allure\Attribute\DisplayName;
use Qameta\Allure\Attribute\Epic;
use Qameta\Allure\Attribute\Feature;
use Qameta\Allure\Attribute\Severity;

#[Epic('RSVP Reading API')]
#[Feature('Reading Progress')]
final class UserBookProgressTest extends AbstractApiTestCase
{
    #[DisplayName('GET returns zero defaults when the user has not started')]
    #[Severity(Severity::NORMAL)]
    public function testGetReturnsZeroDefaultsWhenNotStarted(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $response = $this->authenticatedClient($user)
            ->request('GET', '/api/progress/'.$book->getId())
            ->toArray();

        self::assertNull($response['id']);
        self::assertSame(1, $response['pageNumber']);
        self::assertSame(0, $response['wordIndex']);
        self::assertSame($book->getId(), $response['book']['id']);
    }

    #[DisplayName('PUT creates a progress row on first save')]
    #[Severity(Severity::CRITICAL)]
    public function testPutCreatesProgressWhenMissing(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $response = $this->authenticatedClient($user)
            ->request('PUT', '/api/progress/'.$book->getId(), [
                'json' => ['pageNumber' => 4, 'wordIndex' => 12],
            ])
            ->toArray();

        self::assertNotNull($response['id']);
        self::assertSame(4, $response['pageNumber']);
        self::assertSame(12, $response['wordIndex']);

        $this->em->clear();
        $row = $this->em->getRepository(UserBookProgress::class)
            ->findOneBy(['user' => $user->getId(), 'book' => $book->getId()]);
        self::assertNotNull($row);
        self::assertSame(4, $row->getPageNumber());
    }

    #[DisplayName('PUT upserts (no duplicate rows)')]
    #[Severity(Severity::CRITICAL)]
    public function testPutUpdatesExistingProgress(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $client = $this->authenticatedClient($user);

        $client->request('PUT', '/api/progress/'.$book->getId(), [
            'json' => ['pageNumber' => 2, 'wordIndex' => 5],
        ]);
        $second = $client->request('PUT', '/api/progress/'.$book->getId(), [
            'json' => ['pageNumber' => 9, 'wordIndex' => 0],
        ])->toArray();

        self::assertSame(9, $second['pageNumber']);
        self::assertSame(0, $second['wordIndex']);

        $rows = $this->em->getRepository(UserBookProgress::class)
            ->findBy(['user' => $user->getId(), 'book' => $book->getId()]);
        self::assertCount(1, $rows, 'PUT must upsert, not duplicate.');
    }

    #[DisplayName('PUT rejects payloads missing required fields')]
    #[Severity(Severity::NORMAL)]
    public function testPutRejectsMissingFields(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $this->authenticatedClient($user)->request('PUT', '/api/progress/'.$book->getId(), [
            'json' => ['pageNumber' => 3],
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[DisplayName('PUT rejects pageNumber < 1')]
    #[Severity(Severity::NORMAL)]
    public function testPutRejectsNonPositivePageNumber(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $this->authenticatedClient($user)->request('PUT', '/api/progress/'.$book->getId(), [
            'json' => ['pageNumber' => 0, 'wordIndex' => 0],
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[DisplayName('PUT rejects negative wordIndex')]
    #[Severity(Severity::NORMAL)]
    public function testPutRejectsNegativeWordIndex(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        $this->authenticatedClient($user)->request('PUT', '/api/progress/'.$book->getId(), [
            'json' => ['pageNumber' => 1, 'wordIndex' => -1],
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[DisplayName('GET on an unknown book returns 404')]
    #[Severity(Severity::NORMAL)]
    public function testGetForUnknownBookReturns404(): void
    {
        $user = $this->createUser('yusuf');

        $this->authenticatedClient($user)->request('GET', '/api/progress/9999');
        self::assertResponseStatusCodeSame(404);
    }

    #[DisplayName('GET progress requires authentication')]
    #[Severity(Severity::CRITICAL)]
    public function testGetRequiresAuth(): void
    {
        $user = $this->createUser('yusuf');
        $book = $this->persistBook($user);

        static::createClient()->request('GET', '/api/progress/'.$book->getId());
        self::assertResponseStatusCodeSame(401);
    }

    private function persistBook(User $user): Book
    {
        $book = (new Book())
            ->setUser($user)
            ->setTitle('Progress book')
            ->setOriginalFilename('progress.pdf')
            ->setFormat('pdf')
            ->setTotalPages(10);

        $this->em->persist($book);
        $this->em->flush();

        return $book;
    }
}
