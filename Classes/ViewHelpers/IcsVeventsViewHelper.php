<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\ViewHelpers;

use GeorgRinger\News\Domain\Model\News;
use Spielerj\EventnewsRecurring\Service\RecurrenceCalculator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ViewHelper that generates one VEVENT block per time slot for hourly/minutely events.
 *
 * Calendar clients (Apple Calendar, Google Calendar, Outlook) do not reliably
 * expand BYHOUR+BYMINUTE within a MINUTELY/HOURLY RRULE. The only universally
 * compatible approach is to emit one VEVENT per time slot, each carrying a
 * simple WEEKLY (or DAILY) RRULE.
 *
 * Returns an empty string for non-hourly/minutely events so the template can
 * fall back to the classic single-VEVENT output.
 *
 * Usage:
 *   <enr:icsVevents event="{newsItem}" domain="example.com" />
 */
class IcsVeventsViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('event', News::class, 'The news event object', true);
        $this->registerArgument('domain', 'string', 'Domain used in UID (defaults to site base host)', false, '');
    }

    public function render(): string
    {
        $event  = $this->arguments['event'];
        $domain = $this->arguments['domain'];
        if ($domain === '') {
            $request = $this->renderingContext->getAttribute(\Psr\Http\Message\ServerRequestInterface::class);
            $domain  = $request->getAttribute('site')->getBase()->getHost();
        }
        $type   = $event->getRecurringType();

        if (!in_array($type, ['hourly', 'minutely'], true)) {
            return '';
        }

        if (!method_exists($event, 'getRecurringTimeRangesArray')) {
            return '';
        }

        $timeRanges = $event->getRecurringTimeRangesArray();
        if (empty($timeRanges)) {
            return '';
        }

        /** @var RecurrenceCalculator $calculator */
        $calculator = GeneralUtility::makeInstance(RecurrenceCalculator::class);
        $eventStart = $calculator->toDateTimeImmutable($event->getDatetime());
        $eventEnd   = $event->getEventEnd()
            ? $calculator->toDateTimeImmutable($event->getEventEnd())
            : $eventStart;
        $duration = $eventEnd->getTimestamp() - $eventStart->getTimestamp();

        // Get the adjusted base date (first valid weekday) via buildRRuleConfig
        $rruleConfig = $calculator->buildRRuleConfig($event, $eventStart);
        $baseDate    = isset($rruleConfig['DTSTART'])
            ? $rruleConfig['DTSTART']->setTime(0, 0, 0)
            : $eventStart->setTime(0, 0, 0);

        $slots = $this->buildTimeSlots($event, $type, $timeRanges, $baseDate, $duration);
        if (empty($slots)) {
            return '';
        }

        $slotRrule   = $this->buildSlotRrule($rruleConfig);
        $exdateLines = $this->buildExdateLines($event, $slots);

        // DTSTAMP in UTC
        $dtstamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');

        $uid         = $event->getUid();
        $summary     = $this->escapeText($event->getTitle() ?? '');
        $description = $this->buildDescription($event);
        $location    = $this->buildLocation($event);

        $output = '';
        foreach ($slots as $index => $slot) {
            $dtstart = (new \DateTimeImmutable())->setTimestamp($slot['start'])->format('Ymd\THis');
            $dtend   = (new \DateTimeImmutable())->setTimestamp($slot['end'])->format('Ymd\THis');

            $vevent  = "BEGIN:VEVENT\n";
            $vevent .= "UID:news-{$uid}-{$index}@{$domain}\n";
            $vevent .= "DTSTAMP:{$dtstamp}\n";
            $vevent .= "DTSTART:{$dtstart}\n";
            $vevent .= "DTEND:{$dtend}\n";
            $vevent .= "SUMMARY:{$summary}\n";
            if ($description !== '') {
                $vevent .= "DESCRIPTION:{$description}\n";
            }
            if ($location !== '') {
                $vevent .= "LOCATION:{$location}\n";
            }
            if ($slotRrule !== '') {
                $vevent .= "RRULE:{$slotRrule}\n";
            }
            $vevent .= $exdateLines;
            $vevent .= "END:VEVENT\n";

            $output .= $vevent;
        }

        return $output;
    }

    // -------------------------------------------------------------------------

    /**
     * Compute all time slots using the same "valid hour" logic as RecurrenceCalculator.
     *
     * @return array<int, array{start: int, end: int}>
     */
    private function buildTimeSlots(
        News $event,
        string $type,
        array $timeRanges,
        \DateTimeImmutable $baseDate,
        int $duration
    ): array {
        $interval = max(1, (int)$event->getRecurringInterval());
        $slots    = [];

        foreach ($timeRanges as $range) {
            $fromParts = explode(':', $range['from']);
            $toParts   = explode(':', $range['to']);
            $fromH     = (int)($fromParts[0] ?? 0);
            $fromM     = (int)($fromParts[1] ?? 0);
            $toH       = (int)($toParts[0] ?? 0);
            $toM       = (int)($toParts[1] ?? 0);

            if ($type === 'hourly') {
                // One slot per step-hour; only include hours whose slot fits the window
                for ($h = $fromH; $h <= $toH; $h += $interval) {
                    $slotStart = $baseDate->setTime($h, $fromM, 0);
                    $slots[]   = [
                        'start' => $slotStart->getTimestamp(),
                        'end'   => $slotStart->getTimestamp() + $duration,
                    ];
                }
            } else {
                // minutely: iterate by total minutes from range start to range end
                $startTotal = $fromH * 60 + $fromM;
                $endTotal   = $toH * 60 + $toM;
                for ($totalMin = $startTotal; $totalMin <= $endTotal; $totalMin += $interval) {
                    $h         = intdiv($totalMin, 60);
                    $m         = $totalMin % 60;
                    $slotStart = $baseDate->setTime($h, $m, 0);
                    $slots[]   = [
                        'start' => $slotStart->getTimestamp(),
                        'end'   => $slotStart->getTimestamp() + $duration,
                    ];
                }
            }
        }

        return $slots;
    }

    /**
     * Build the RRULE string for individual slot VEVENTs.
     * Replaces HOURLY/MINUTELY frequency with WEEKLY (if weekdays selected) or DAILY.
     * Removes BYHOUR/BYMINUTE since those are now encoded in each DTSTART.
     */
    private function buildSlotRrule(array $rruleConfig): string
    {
        $hasByday = !empty($rruleConfig['BYDAY']);
        $parts    = [];

        $parts[] = $hasByday ? 'FREQ=WEEKLY' : 'FREQ=DAILY';

        if ($hasByday) {
            $byday   = is_array($rruleConfig['BYDAY'])
                ? implode(',', $rruleConfig['BYDAY'])
                : (string)$rruleConfig['BYDAY'];
            $parts[] = 'BYDAY=' . $byday;
        }

        if (!empty($rruleConfig['COUNT'])) {
            $parts[] = 'COUNT=' . $rruleConfig['COUNT'];
        } elseif (!empty($rruleConfig['UNTIL'])) {
            $until = $rruleConfig['UNTIL'];
            if ($until instanceof \DateTimeInterface) {
                $parts[] = 'UNTIL=' . $until->format('Ymd\THis\Z');
            }
        }

        return implode(';', $parts);
    }

    /**
     * Build EXDATE lines for all excluded dates.
     * For each excluded date, emit one EXDATE per slot (matching each DTSTART).
     *
     * @param array<int, array{start: int}> $slots
     */
    private function buildExdateLines(News $event, array $slots): string
    {
        if (!method_exists($event, 'getRecurringExcludeDatesArray')) {
            return '';
        }

        $excludeDates = $event->getRecurringExcludeDatesArray();
        if (empty($excludeDates)) {
            return '';
        }

        $excludedYmd = array_map(
            static fn(\DateTimeInterface $d): string => $d->format('Ymd'),
            $excludeDates
        );

        $lines = '';
        foreach ($slots as $slot) {
            $slotDt  = (new \DateTimeImmutable())->setTimestamp($slot['start']);
            $slotYmd = $slotDt->format('Ymd');
            if (in_array($slotYmd, $excludedYmd, true)) {
                $lines .= 'EXDATE:' . $slotDt->format('Ymd\THis') . "\n";
            }
        }

        return $lines;
    }

    private function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\\n', $text);
        return $text;
    }

    private function buildDescription(News $event): string
    {
        if ($event->getTeaser()) {
            return $this->escapeText(substr($event->getTeaser(), 0, 500));
        }
        if ($event->getBodytext()) {
            return $this->escapeText(substr(strip_tags($event->getBodytext()), 0, 500));
        }
        return '';
    }

    private function buildLocation(News $event): string
    {
        if (method_exists($event, 'getLocation') && $event->getLocation()) {
            return $this->escapeText($event->getLocation()->getTitle() ?? '');
        }
        if (method_exists($event, 'getLocationSimple') && $event->getLocationSimple()) {
            return $this->escapeText($event->getLocationSimple());
        }
        return '';
    }
}
