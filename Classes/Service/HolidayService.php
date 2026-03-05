<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves ICS-based holiday calendars (school holidays, public holidays) to
 * arrays of Y-m-d strings that can be used as exclude-day filters in the
 * RecurrenceCalculator.
 *
 * ICS sources are configured per-site via Site Settings:
 *   eventnewsRecurring.schoolHolidaysIcsPaths  (stringlist)
 *   eventnewsRecurring.publicHolidaysIcsPaths  (stringlist)
 *
 * Each entry can be either:
 *   - A FAL path:  1:/holidays/schulferien-2026.ics
 *   - An HTTP URL: https://www.schulferien.eu/downloads/ical4.php?land=10&type=1&year=2026
 */
class HolidayService
{
    /**
     * In-request runtime cache to avoid duplicate fetches within one request.
     * @var array<string, string|null>
     */
    private static array $runtimeCache = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns all school-holiday days as Y-m-d strings for the given site.
     *
     * @return string[]
     */
    public function getSchoolHolidayDays(Site $site): array
    {
        $paths = $site->getSettings()->get('eventnewsRecurring.schoolHolidaysIcsPaths') ?? [];
        return $this->getDaysFromPaths((array)$paths);
    }

    /**
     * Returns all public-holiday days as Y-m-d strings for the given site.
     *
     * @return string[]
     */
    public function getPublicHolidayDays(Site $site): array
    {
        $paths = $site->getSettings()->get('eventnewsRecurring.publicHolidaysIcsPaths') ?? [];
        return $this->getDaysFromPaths((array)$paths);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * @param string[] $paths
     * @return string[]
     */
    private function getDaysFromPaths(array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        $days = [];
        foreach ($paths as $path) {
            $path = trim((string)$path);
            if ($path === '') {
                continue;
            }
            try {
                $content = $this->fetchIcsContent($path);
                if ($content !== null) {
                    $days = array_merge($days, $this->parseIcsDays($content));
                }
            } catch (\Throwable $e) {
                $this->logError('Failed to load ICS from "' . $path . '": ' . $e->getMessage());
            }
        }

        return array_values(array_unique($days));
    }

    /**
     * Fetches raw ICS content from either a remote URL or a FAL path.
     * Applies runtime deduplication within the current request.
     */
    private function fetchIcsContent(string $path): ?string
    {
        $runtimeKey = md5($path);
        if (array_key_exists($runtimeKey, self::$runtimeCache)) {
            return self::$runtimeCache[$runtimeKey];
        }

        $content = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $this->fetchRemote($path)
            : $this->fetchLocal($path);

        self::$runtimeCache[$runtimeKey] = $content;
        return $content;
    }

    /**
     * Fetches ICS from a remote URL via TYPO3 RequestFactory (Guzzle).
     * Caches the result for 24 hours in the persistent holiday cache.
     */
    private function fetchRemote(string $url): ?string
    {
        $cache = $this->getCache();
        $cacheIdentifier = 'holiday_url_' . md5($url);

        if ($cache !== null && $cache->has($cacheIdentifier)) {
            return (string)$cache->get($cacheIdentifier);
        }

        try {
            /** @var RequestFactory $requestFactory */
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request($url, 'GET', [
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);
            if ($response->getStatusCode() === 200) {
                $content = (string)$response->getBody();
                $cache?->set($cacheIdentifier, $content, [], 86400); // 24 h TTL
                return $content;
            }
            $this->logError('Unexpected HTTP status ' . $response->getStatusCode() . ' for ' . $url);
        } catch (\Throwable $e) {
            $this->logError('HTTP request failed for "' . $url . '": ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetches ICS from a FAL file (e.g. "1:/holidays/schulferien-2026.ics").
     * The cache key includes filemtime so the cache auto-invalidates on file change.
     */
    private function fetchLocal(string $path): ?string
    {
        try {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $file = $resourceFactory->getFileObjectFromCombinedIdentifier($path);

            $cache = $this->getCache();
            $cacheIdentifier = 'holiday_fal_' . md5($path . $file->getModificationTime());

            if ($cache !== null && $cache->has($cacheIdentifier)) {
                return (string)$cache->get($cacheIdentifier);
            }

            $content = $file->getContents();
            $cache?->set($cacheIdentifier, $content, [], 0); // no TTL; key changes on file modification
            return $content;
        } catch (\Throwable $e) {
            $this->logError('Failed to read FAL file "' . $path . '": ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Parses ICS content and returns an array of Y-m-d date strings.
     *
     * Handles DATE-only DTSTART / DTEND values (all-day events), which is the
     * standard format used by school-holiday and public-holiday calendars.
     * DTEND is exclusive per RFC 5545 (e.g. a holiday ending on Feb 28 has
     * DTEND:20260301), so we stop one day before DTEND.
     */
    private function parseIcsDays(string $content): array
    {
        // Unfold RFC 5545 line folding (CRLF + SPACE/TAB → nothing)
        $content = preg_replace('/\r?\n[ \t]/', '', $content) ?? $content;

        $days = [];
        preg_match_all('/BEGIN:VEVENT(.+?)END:VEVENT/s', $content, $matches);

        foreach ($matches[1] ?? [] as $vevent) {
            $dtstart = $this->extractDateOnlyValue($vevent, 'DTSTART');
            if ($dtstart === null) {
                continue;
            }

            $dtend = $this->extractDateOnlyValue($vevent, 'DTEND');
            // DTEND is exclusive; if absent treat as single-day event
            $endExclusive = $dtend ?? $dtstart->modify('+1 day');

            // Expand range to individual days
            $current = $dtstart;
            while ($current < $endExclusive) {
                $days[] = $current->format('Y-m-d');
                $current = $current->modify('+1 day');
            }
        }

        return $days;
    }

    /**
     * Extracts a DATE-only value (YYYYMMDD) from a VEVENT block.
     * Handles both "DTSTART;VALUE=DATE:20260223" and plain "DTSTART:20260223".
     */
    private function extractDateOnlyValue(string $vevent, string $property): ?\DateTimeImmutable
    {
        if (preg_match('/' . preg_quote($property, '/') . '(?:;[^:]*)?:(\d{8})/', $vevent, $m)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $m[1]);
            return $dt !== false ? $dt->setTime(0, 0, 0) : null;
        }
        return null;
    }

    /**
     * Returns the persistent holiday cache, or null if it is not configured.
     */
    private function getCache(): ?FrontendInterface
    {
        try {
            return GeneralUtility::makeInstance(CacheManager::class)
                ->getCache('eventnews_recurring_holidays');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function logError(string $message): void
    {
        GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(self::class)
            ->error($message);
    }
}
