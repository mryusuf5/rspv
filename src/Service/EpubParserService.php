<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

class EpubParserService
{
    /**
     * Words per virtual page for EPUB content (EPUBs have no fixed pages).
     */
    private const WORDS_PER_PAGE = 300;

    /**
     * Parse an EPUB file and return an array of page content strings.
     * Since EPUBs are reflowable, content is split into virtual pages by word count.
     *
     * @return string[] indexed from 1
     */
    public function parse(string $filePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Failed to open EPUB file as ZIP archive.');
        }

        try {
            $opfPath = $this->findOpfPath($zip);
            $opfContent = $zip->getFromName($opfPath);

            if ($opfContent === false) {
                throw new RuntimeException("Cannot read OPF file at: {$opfPath}");
            }

            $opfDir = dirname($opfPath);
            if ($opfDir === '.') {
                $opfDir = '';
            }

            $opfXml = new \SimpleXMLElement($opfContent);
            $opfXml->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');

            $manifest = $this->parseManifest($opfXml, $opfDir);
            $spineItems = $this->parseSpine($opfXml, $manifest);

            $fullText = '';
            foreach ($spineItems as $itemPath) {
                $rawContent = $zip->getFromName($itemPath);
                if ($rawContent !== false) {
                    $fullText .= ' ' . $this->extractTextFromHtml($rawContent);
                }
            }
        } finally {
            $zip->close();
        }

        return $this->splitIntoPages(trim($fullText));
    }

    /**
     * Extract title and author metadata from an EPUB file.
     *
     * @return array{title: string, author: string|null}
     */
    public function extractMetadata(string $filePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Failed to open EPUB file as ZIP archive.');
        }

        try {
            $opfPath = $this->findOpfPath($zip);
            $opfContent = $zip->getFromName($opfPath);

            if ($opfContent === false) {
                throw new RuntimeException("Cannot read OPF file at: {$opfPath}");
            }

            $opfXml = new \SimpleXMLElement($opfContent);
            $opfXml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $opfXml->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');

            $titleNodes = $opfXml->xpath('//dc:title');
            $authorNodes = $opfXml->xpath('//dc:creator');

            $title = ($titleNodes && count($titleNodes) > 0)
                ? (string) $titleNodes[0]
                : 'Unknown Title';

            $author = ($authorNodes && count($authorNodes) > 0)
                ? (string) $authorNodes[0]
                : null;
        } finally {
            $zip->close();
        }

        return ['title' => $title, 'author' => $author];
    }

    private function findOpfPath(\ZipArchive $zip): string
    {
        $containerContent = $zip->getFromName('META-INF/container.xml');

        if ($containerContent === false) {
            throw new RuntimeException('EPUB is missing META-INF/container.xml');
        }

        $containerXml = new \SimpleXMLElement($containerContent);
        $containerXml->registerXPathNamespace('cn', 'urn:oasis:names:tc:opendocument:xmlns:container');

        $rootfiles = $containerXml->xpath('//cn:rootfile[@media-type="application/oebps-package+xml"]');

        if (empty($rootfiles)) {
            // Fallback: try without namespace
            $rootfiles = $containerXml->xpath('//*[@media-type="application/oebps-package+xml"]');
        }

        if (empty($rootfiles)) {
            throw new RuntimeException('Cannot locate OPF rootfile in container.xml');
        }

        return (string) $rootfiles[0]['full-path'];
    }

    /**
     * @return array<string, string> map of manifest id => resolved file path in ZIP
     */
    private function parseManifest(\SimpleXMLElement $opfXml, string $opfDir): array
    {
        $manifest = [];

        $opfXml->registerXPathNamespace('opf', 'http://www.idpf.org/2007/opf');
        $items = $opfXml->xpath('//opf:manifest/opf:item') ?: $opfXml->xpath('//*[local-name()="manifest"]/*[local-name()="item"]');

        foreach ($items ?? [] as $item) {
            $id = (string) $item['id'];
            $href = (string) $item['href'];

            $fullPath = $opfDir !== ''
                ? ltrim($opfDir . '/' . $href, '/')
                : $href;

            $manifest[$id] = $fullPath;
        }

        return $manifest;
    }

    /**
     * @param  array<string, string> $manifest
     * @return string[]
     */
    private function parseSpine(\SimpleXMLElement $opfXml, array $manifest): array
    {
        $spineItems = [];

        $itemrefs = $opfXml->xpath('//opf:spine/opf:itemref') ?: $opfXml->xpath('//*[local-name()="spine"]/*[local-name()="itemref"]');

        foreach ($itemrefs ?? [] as $itemref) {
            $idref = (string) $itemref['idref'];
            if (isset($manifest[$idref])) {
                $spineItems[] = $manifest[$idref];
            }
        }

        return $spineItems;
    }

    private function extractTextFromHtml(string $html): string
    {
        // Suppress XML/HTML parse errors for malformed content
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // Remove script and style nodes
        foreach (['script', 'style', 'head'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            while ($nodes->length > 0) {
                $nodes->item(0)->parentNode->removeChild($nodes->item(0));
            }
        }

        $text = $dom->textContent ?? '';

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Split a long text into pages of roughly WORDS_PER_PAGE words each.
     *
     * @return string[] indexed from 1
     */
    private function splitIntoPages(string $text): array
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return [1 => ''];
        }

        $chunks = array_chunk($words, self::WORDS_PER_PAGE);
        $pages = [];

        foreach ($chunks as $index => $chunk) {
            $pages[$index + 1] = implode(' ', $chunk);
        }

        return $pages;
    }
}
