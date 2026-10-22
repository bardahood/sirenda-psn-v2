<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Penyaring HTML dari editor teks kaya aplikasi PSI (daftar putih). Hanya tag format dasar,
 * tabel, tautan http(s)/mailto, dan gambar http(s)/data:image yang dipertahankan; skrip,
 * gaya, atribut on*, iframe, dsb. dibuang. Dipakai saat menyimpan dan saat menampilkan.
 */
final class HtmlAman
{
    private const TAG = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code', 'span', 'div', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'a', 'img', 'hr'];

    private const ATRIBUT = ['a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height'], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'], 'ol' => ['start']];

    /** Tag yang dibuang beserta isinya (bukan hanya pembungkusnya). */
    private const BUANG_ISI = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'button', 'head', 'title', 'meta', 'link'];

    public static function bersihkan(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $doc = new DOMDocument;
        $lama = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__akar">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);

        $akar = $doc->getElementById('__akar');
        if (! $akar) {
            return e(strip_tags($html));
        }
        self::saring($akar);

        $hasil = '';
        foreach ($akar->childNodes as $n) {
            $hasil .= $doc->saveHTML($n);
        }
        $hasil = trim($hasil);

        return $hasil === '' ? null : $hasil;
    }

    private static function saring(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $anak) {
            if ($anak->nodeType === XML_COMMENT_NODE || $anak->nodeType === XML_PI_NODE || $anak->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($anak);

                continue;
            }
            if (! $anak instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($anak->tagName);
            if (in_array($tag, self::BUANG_ISI, true)) {
                $node->removeChild($anak);

                continue;
            }
            self::saring($anak);
            if (! in_array($tag, self::TAG, true)) {
                // Tag tak dikenal: buka pembungkus, pertahankan isinya.
                while ($anak->firstChild) {
                    $node->insertBefore($anak->firstChild, $anak);
                }
                $node->removeChild($anak);

                continue;
            }
            foreach (iterator_to_array($anak->attributes) as $attr) {
                $nama = strtolower($attr->name);
                $nilai = trim($attr->value);
                $boleh = in_array($nama, self::ATRIBUT[$tag] ?? [], true)
                    && match ($nama) {
                        'href' => (bool) preg_match('#^(https?://|mailto:)#i', $nilai),
                        'src' => (bool) preg_match('#^(https?://|data:image/(png|jpe?g|gif|webp);base64,)#i', $nilai),
                        'width', 'height', 'colspan', 'rowspan', 'start' => ctype_digit($nilai),
                        default => true,
                    };
                if (! $boleh) {
                    $anak->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a') {
                $anak->setAttribute('rel', 'noopener noreferrer');
                $anak->setAttribute('target', '_blank');
            }
            if ($tag === 'img' && ! $anak->hasAttribute('src')) {
                $node->removeChild($anak);
            }
        }
    }
}
