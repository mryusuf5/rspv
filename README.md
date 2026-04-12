# RSVP

A Rapid Serial Visual Presentation (RSVP) reading API built with Symfony 7.4 and API Platform 4. Upload a PDF or EPUB book and the server parses it into pages, which can then be read word-by-word.

## Stack

- **Backend:** Symfony 7.4, API Platform 4, Doctrine ORM
- **Database:** SQLite (via `var/data.db`)
- **Auth:** JWT (LexikJWTAuthenticationBundle)
- **Parsers:** `pdftotext` / `pdfinfo` (poppler-utils) for PDF, custom ZIP+XML parser for EPUB

## Features

- User registration and JWT login
- Upload PDF or EPUB books — parsed into virtual pages automatically
- EPUB chapter titles extracted from `toc.ncx` (EPUB 2) or nav document (EPUB 3)
- Per-user reading progress tracked by page number and word index
- Swagger UI available at `/api` (no auth required)

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/api/register` | No | Register a new user |
| `POST` | `/api/login` | No | Login, returns JWT token |
| `GET` | `/api/me` | Yes | Current user info |
| `GET` | `/api/books` | Yes | List your books |
| `POST` | `/api/books/upload` | Yes | Upload a PDF or EPUB (`multipart/form-data`) |
| `GET` | `/api/books/{id}` | Yes | Get a single book |
| `DELETE` | `/api/books/{id}` | Yes | Delete a book |
| `GET` | `/api/books/{bookId}/pages` | Yes | List pages (id, pageNumber, wordCount, chapterTitle) |
| `GET` | `/api/books/{bookId}/pages/{pageNumber}` | Yes | Get a single page with full content |
| `GET` | `/api/progress/{bookId}` | Yes | Get reading progress for a book |
| `PUT` | `/api/progress/{bookId}` | Yes | Save reading progress (`pageNumber`, `wordIndex`) |

## Page Model

| Field | Type | Description |
|-------|------|-------------|
| `pageNumber` | int | 1-based page number |
| `wordCount` | int | Number of words on the page |
| `chapterTitle` | string\|null | Chapter title (EPUB only, null for PDF) |
| `content` | string | Full text content (only in single-page endpoint) |

For EPUB files, pages are virtual — each spine item (chapter) is split into chunks of 300 words. The `chapterTitle` field is populated from the book's table of contents and is the same for all pages within a chapter.

## Setup

```bash
composer install
php bin/console lexik:jwt:generate-keypair
sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
```

The server must have `pdftotext` and `pdfinfo` installed:

```bash
apt install poppler-utils
```

## Test UI

Open `public/test.html` in a browser for a dark-themed API tester. It supports registration, login, book upload, page browsing with chapter navigation, and progress tracking. Each word on a page is displayed as a badge with its middle letter highlighted — the basis for RSVP-style reading.
