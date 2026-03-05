<?php
declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\ViewHelpers;

use GeorgRinger\News\Domain\Model\News;
use Spielerj\EventnewsRecurring\Service\RecurrenceCalculator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Outputs EXDATE lines for an ICS VEVENT, covering both manually excluded dates
 * and dates excluded via school/public holiday ICS calendars.
 *
 * Usage in ICS template:
 *   <enr:icsExdates event="{newsItem}" />
 *
 * Each excluded date produces one EXDATE line, e.g.:
 *   EXDATE:20260317T090000
 * or for full-day events:
 *   EXDATE;VALUE=DATE:20260317
 */
class IcsExdatesViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('event', News::class, 'The news event object', true);
    }

    public function render(): string
    {
        /** @var News $event */
        $event = $this->arguments['event'];

        if (!method_exists($event, 'getRecurringEvent') || !$event->getRecurringEvent()) {
            return '';
        }

        /** @var RecurrenceCalculator $calculator */
        $calculator = GeneralUtility::makeInstance(RecurrenceCalculator::class);
        $excludedDays = $calculator->getExcludedDayStrings($event);

        if (empty($excludedDays)) {
            return '';
        }

        // Determine the upper bound from the RRULE config (UNTIL date)
        $rruleConfig = $calculator->buildRRuleConfig($event);
        $until = isset($rruleConfig['UNTIL']) && $rruleConfig['UNTIL'] instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($rruleConfig['UNTIL'])
            : null;

        $isFullDay = method_exists($event, 'getFullDay') && $event->getFullDay();
        $eventTime = $event->getDatetime();
        $timeStr = ($eventTime instanceof \DateTimeInterface)
            ? $eventTime->format('His')
            : '000000';

        $lines = [];
        foreach ($excludedDays as $dayStr) {
            // Filter out days beyond the RRULE UNTIL date
            if ($until !== null) {
                $day = \DateTimeImmutable::createFromFormat('Y-m-d', $dayStr, $until->getTimezone());
                if ($day === false || $day > $until) {
                    continue;
                }
            }

            $compact = str_replace('-', '', $dayStr); // → YYYYMMDD
            if ($isFullDay) {
                $lines[] = 'EXDATE;VALUE=DATE:' . $compact;
            } else {
                $lines[] = 'EXDATE:' . $compact . 'T' . $timeStr;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
