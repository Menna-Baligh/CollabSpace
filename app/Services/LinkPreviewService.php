<?php

namespace App\Services;

class LinkPreviewService
{
    /**
     * Extract the first safe HTTP/HTTPS URL from text and return basic metadata.
     * Blocks localhost, private, loopback, link-local, and reserved IP ranges.
     *
     * @return array{url: string, domain: string}|null
     */
    public function extract(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        if (! preg_match('/https?:\/\/[^\s<>"\']+/i', $text, $matches)) {
            return null;
        }

        $url = rtrim($matches[0], '.,;:!?)');
        $parsed = parse_url($url);

        if (! is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parsed['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254'], true)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return [
            'url' => $url,
            'domain' => $host,
        ];
    }
}
