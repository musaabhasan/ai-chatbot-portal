<?php

declare(strict_types=1);

namespace ChatbotPortal\Rag;

use RuntimeException;

final class DocumentExtractor
{
    public function extract(string $path, string $mimeType): string
    {
        return match ($mimeType) {
            'text/plain' => $this->text($path),
            'application/pdf' => $this->pdf($path),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->docx($path),
            default => throw new RuntimeException('Unsupported document type.'),
        };
    }

    private function text(string $path): string
    {
        $content = file_get_contents($path);
        return $content === false ? '' : $this->normalize($content);
    }

    private function pdf(string $path): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException('PDF extraction requires smalot/pdfparser.');
        }

        $parser = new \Smalot\PdfParser\Parser();
        return $this->normalize($parser->parseFile($path)->getText());
    }

    private function docx(string $path): string
    {
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new RuntimeException('DOCX extraction requires phpoffice/phpword.');
        }

        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $parts = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $parts[] = (string) $element->getText();
                }
            }
        }

        return $this->normalize(implode("\n", $parts));
    }

    private function normalize(string $content): string
    {
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\R{3,}/', "\n\n", $content) ?? $content;

        return trim($content);
    }
}
