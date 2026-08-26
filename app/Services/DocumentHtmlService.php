<?php

namespace App\Services;

class DocumentHtmlService
{
    public function clean(string $html): string
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $allowed = ['div', 'span', 'p', 'br', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'blockquote', 'a'];
        foreach (array_reverse(iterator_to_array($dom->getElementsByTagName('*'))) as $node) {
            if (! in_array($node->nodeName, $allowed)) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $safeLink = $node->nodeName === 'a' && $attribute->name === 'href' && preg_match('~^https?://~i', $attribute->value);
                $safeClass = $attribute->name === 'class' && in_array($attribute->value, ['unfilled', 'page-break']);
                if (! $safeLink && ! $safeClass) {
                    $node->removeAttribute($attribute->name);
                }
            }
        }

        return preg_replace('/<\?xml[^>]+>/', '', $dom->saveHTML()) ?? '';
    }
}
