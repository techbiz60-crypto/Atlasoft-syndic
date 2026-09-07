<?php

namespace App\Actions\Pdf;

use ArPHP\I18N\Arabic;

/**
 * Prepares Arabic text for DomPDF.
 *
 * DomPDF has no text shaping engine: it draws each code point as-is, left
 * to right. Arabic then comes out as disconnected letters in reverse
 * order — and with a core font such as Helvetica, which carries no Arabic
 * glyphs at all, as a row of "?" instead.
 *
 * This converts the letters to their contextual presentation forms (the
 * U+FB50–U+FEFF blocks DejaVu Sans ships) and reverses the runs, so the
 * result reads correctly once DomPDF lays it out left to right.
 */
class ShapeArabicText
{
    public function __construct(private Arabic $arabic) {}

    public function handle(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Latin-only values (French labels, amounts, lot numbers) must be
        // left untouched — running them through the shaper would reverse
        // them too.
        if (! $this->containsArabic($text)) {
            return $text;
        }

        return $this->arabic->utf8Glyphs($text);
    }

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text);
    }
}
