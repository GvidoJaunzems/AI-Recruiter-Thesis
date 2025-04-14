<?php

namespace RecruiterLib\Util;

use Smalot\PdfParser\Parser as PdfParserVendor;

/**
 * Utility class for parsing PDF files.
 */
class PdfParser {
    private PdfParserVendor $parser;

    public function __construct(?PdfParserVendor $parser = null) {
        $this->parser = $parser ?: new PdfParserVendor();
    }

    /**
     * Parses the text content from a PDF file.
     *
     * @param string $pdfPath Absolute path to the PDF file.
     * @return string The extracted text content.
     * @throws ParsingException If the file doesn't exist or parsing fails.
     */
    public function parsePdf(string $pdfPath): string {
        if (!file_exists($pdfPath)) {
            throw new ParsingException("PDF file not found at path: {$pdfPath}");
        }

        try {
            $pdf = $this->parser->parseFile($pdfPath);
            $text = $pdf->getText();
            return $text;
        } catch (\Exception $e) {
            // Log the original exception message
            error_log("[PdfParser] Error parsing PDF '{$pdfPath}': " . $e->getMessage());
            throw new ParsingException("Failed to parse PDF file '{$pdfPath}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }
} 