<?php

declare(strict_types=1);

namespace FrontPressMdExp\Export;

use FrontPressMdExp\Helpers\Slug;

/**
 * Stateful per-post media collector.
 *
 * Usage:
 *   $mc->reset($folder, $slug);
 *   $mc->ingestFeatured($post);
 *   $mc->ingestHtml($renderedHtml);
 *   $url = $mc->ingestAttachment($acfImageId);   // returns /uploads/<folder>/<slug>/<file>
 *   $rewritten = $mc->rewriteHtml($renderedHtml);
 *   $plan = $mc->plan();                          // ['files', 'url_map', 'featured_url']
 *
 * Per-post media on disk: site/content/<folder>/<slug>/<basename>
 * Public URL:             /uploads/<folder>/<slug>/<basename>
 *   (rewritten to disk by mdframework's `/uploads/*` route)
 */
final class MediaCollector
{
    private array $siteHosts;
    private string $uploadsBaseDir;
    private string $uploadsBaseUrl;

    private string $folder = '';
    private string $slug = '';
    private array $taken = [];
    private array $files = [];
    private array $urlMap = [];
    private ?string $featuredUrl = null;

    public function __construct()
    {
        $home = parse_url((string) home_url(), PHP_URL_HOST);
        $site = parse_url((string) site_url(), PHP_URL_HOST);
        $this->siteHosts = array_unique(array_filter([$home, $site]));

        $u                    = wp_upload_dir();
        $this->uploadsBaseDir = is_array($u) ? (string) $u['basedir'] : '';
        $this->uploadsBaseUrl = is_array($u) ? (string) $u['baseurl'] : '';
    }

    public function reset(string $folder, string $slug): void
    {
        $this->folder      = $folder;
        $this->slug        = $slug;
        $this->taken       = [];
        $this->files       = [];
        $this->urlMap      = [];
        $this->featuredUrl = null;
    }

    public function ingestFeatured(\WP_Post $post): ?string
    {
        $thumbId = (int) get_post_thumbnail_id($post);
        if ($thumbId <= 0) {
            return null;
        }
        $url = $this->ingestAttachment($thumbId);
        if ($url !== null) {
            $this->featuredUrl = $url;
        }
        return $url;
    }

    public function ingestHtml(string $html): void
    {
        foreach ($this->extractCandidateUrls($html) as $url) {
            $this->ingestLocalUrl($url);
        }
    }

    public function ingestAttachment(int $attachmentId): ?string
    {
        if ($attachmentId <= 0) {
            return null;
        }
        $src = (string) get_attached_file($attachmentId);
        if ($src === '' || !is_file($src)) {
            return null;
        }
        $publicUrl = $this->stageFile($src);

        $original = (string) wp_get_attachment_url($attachmentId);
        if ($original !== '' && !isset($this->urlMap[$original])) {
            $this->urlMap[$original] = $publicUrl;
        }
        return $publicUrl;
    }

    public function ingestLocalUrl(string $url): ?string
    {
        if (!$this->isLocal($url)) {
            return null;
        }
        if (isset($this->urlMap[$url])) {
            return $this->urlMap[$url];
        }
        $src = $this->urlToPath($url);
        if ($src === null || !is_file($src)) {
            return null;
        }
        $publicUrl           = $this->stageFile($src);
        $this->urlMap[$url]  = $publicUrl;
        return $publicUrl;
    }

    public function rewriteHtml(string $html): string
    {
        if ($this->urlMap === []) {
            return $html;
        }
        return strtr($html, $this->urlMap);
    }

    /**
     * @return array{
     *   files: array<int, array{src:string, dest_rel:string, public_url:string}>,
     *   url_map: array<string, string>,
     *   featured_url: string|null
     * }
     */
    public function plan(): array
    {
        return [
            'files'        => array_values($this->files),
            'url_map'      => $this->urlMap,
            'featured_url' => $this->featuredUrl,
        ];
    }

    private function stageFile(string $src): string
    {
        $base = Slug::uniqueBasename(basename($src), $this->taken);
        $publicUrl = '/uploads/' . $this->folder . '/' . $this->slug . '/' . $base;
        $this->files[] = [
            'src'        => $src,
            'dest_rel'   => 'site/content/' . $this->folder . '/' . $this->slug . '/' . $base,
            'public_url' => $publicUrl,
        ];
        return $publicUrl;
    }

    private function extractCandidateUrls(string $html): array
    {
        $urls = [];
        if (preg_match_all('/(?:src|href)=("|\')(.*?)\1/i', $html, $m)) {
            foreach ($m[2] as $u) {
                $urls[] = html_entity_decode((string) $u, ENT_QUOTES | ENT_HTML5);
            }
        }
        if (preg_match_all('/srcset=("|\')(.*?)\1/i', $html, $m)) {
            foreach ($m[2] as $set) {
                foreach (explode(',', (string) $set) as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }
                    $space = strpos($part, ' ');
                    $u     = $space === false ? $part : substr($part, 0, $space);
                    $urls[] = html_entity_decode($u, ENT_QUOTES | ENT_HTML5);
                }
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }

    private function isLocal(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#')) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && in_array($host, $this->siteHosts, true);
    }

    private function urlToPath(string $url): ?string
    {
        $clean = strtok($url, '?#');
        if ($clean === false) {
            return null;
        }

        $attId = attachment_url_to_postid($clean);
        if ($attId) {
            $path = get_attached_file($attId);
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        if ($this->uploadsBaseUrl !== '' && str_starts_with($clean, $this->uploadsBaseUrl)) {
            $rel  = substr($clean, strlen($this->uploadsBaseUrl));
            $path = $this->uploadsBaseDir . $rel;
            if (is_file($path)) {
                return $path;
            }
        }

        if (str_starts_with($clean, '/')) {
            $abs = ABSPATH . ltrim($clean, '/');
            if (is_file($abs)) {
                return $abs;
            }
        }

        return null;
    }
}
