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

| Method     | Path                                       | Auth | Description                                           |
| ---------- | ------------------------------------------ | ---- | ----------------------------------------------------- |
| `POST`   | `/api/register`                          | No   | Register a new user                                   |
| `POST`   | `/api/login`                             | No   | Login, returns JWT token                              |
| `GET`    | `/api/me`                                | Yes  | Current user info                                     |
| `GET`    | `/api/books`                             | Yes  | List your books                                       |
| `POST`   | `/api/books/upload`                      | Yes  | Upload a PDF or EPUB (`multipart/form-data`)        |
| `GET`    | `/api/books/{id}`                        | Yes  | Get a single book                                     |
| `DELETE` | `/api/books/{id}`                        | Yes  | Delete a book                                         |
| `GET`    | `/api/books/{bookId}/pages`              | Yes  | List pages (id, pageNumber, wordCount, chapterTitle)  |
| `GET`    | `/api/books/{bookId}/pages/{pageNumber}` | Yes  | Get a single page with full content                   |
| `GET`    | `/api/progress/{bookId}`                 | Yes  | Get reading progress for a book                       |
| `PUT`    | `/api/progress/{bookId}`                 | Yes  | Save reading progress (`pageNumber`, `wordIndex`) |

## Page Model

| Field            | Type         | Description                                      |
| ---------------- | ------------ | ------------------------------------------------ |
| `pageNumber`   | int          | 1-based page number                              |
| `wordCount`    | int          | Number of words on the page                      |
| `chapterTitle` | string\|null | Chapter title (EPUB only, null for PDF)          |
| `content`      | string       | Full text content (only in single-page endpoint) |

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


## Testing

De backendtests kunnen lokaal worden uitgevoerd met:

```bash
php bin/phpunit
```


Voor rapportage kan JUnit-output worden gegenereerd met:

<pre class="overflow-visible! px-0!" data-start="1846" data-end="1901"><div class="relative w-full mt-4 mb-1"><div class=""><div class="contents"><div class="border border-token-border-light border-radius-3xl corner-superellipse/1.1 rounded-3xl"><div class="relative h-full w-full border-radius-3xl bg-token-bg-elevated-secondary corner-superellipse/1.1 overflow-clip rounded-3xl lxnfua_clipPathFallback"><div class="pointer-events-none absolute inset-x-4 top-12 bottom-4"><div class="pointer-events-none sticky z-40 shrink-0 z-1!"><div class="sticky bg-token-border-light"></div></div></div><div class="relative"><div class="h-full min-h-0 min-w-0"><div class="h-full min-h-0 min-w-0"><div class=""><div class="relative"><div class=""><div class="relative z-0 flex max-w-full"><div id="code-block-viewer" dir="ltr" class="q9tKkq_viewer cm-editor z-10 light:cm-light dark:cm-light flex h-full w-full flex-col items-stretch ͼs ͼ16"><div class="cm-scroller"><pre class="cm-content q9tKkq_readonly m-0"><code><span>php bin/phpunit </span><span class="ͼ12">--log-junit</span><span> build/junit.xml</span></code></pre></div></div></div></div></div></div></div></div><div class=""><div class=""></div></div></div></div></div></div></div></div></pre>

De gegenereerde testbestanden worden niet opgeslagen in Git, omdat dit build-output is.
