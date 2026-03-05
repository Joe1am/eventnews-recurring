<?php
declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use GeorgRinger\News\Domain\Model\News;
use RRule\RRule;
use Spielerj\EventnewsRecurring\Service\RecurrenceCalculator;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ViewHelper to output recurrence information in different formats
 * 
 * Usage in Fluid:
 * <enr:rrule event="{newsItem}" format="rfc" />   - RFC 5545 RRULE string
 * <enr:rrule event="{newsItem}" format="text" />  - Human-readable text
 * <enr:rrule event="{newsItem}" format="dates" /> - Array of date timestamps
 */
class RruleViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('event', News::class, 'The news event object', true);
        $this->registerArgument('format', 'string', 'Output format: rfc, text, dates', false, 'rfc');
    }

    public function render(): string
    {
        $event = $this->arguments['event'];
        $format = $this->arguments['format'];
        
        // Check if event is recurring
        if (!$event->getRecurringEvent()) {
            return '';
        }
        
        try {
            $calculator = GeneralUtility::makeInstance(RecurrenceCalculator::class);
            $rruleConfig = $calculator->buildRRuleConfig($event);

            if (empty($rruleConfig)) {
                return '';
            }

            // Non-text formats: return directly
            if ($format !== 'text') {
                $rrule = new RRule($rruleConfig);
                return match ($format) {
                    'dates' => $this->renderDates($rrule),
                    default => $rrule->rfcString(false),
                };
            }

            // Text format: build main text
            if (
                in_array($event->getRecurringType(), ['hourly', 'minutely'], true)
                && method_exists($event, 'getRecurringTimeRangesArray')
                && !empty($event->getRecurringTimeRangesArray())
            ) {
                $text = $this->renderSubDayText($event);
            } else {
                $rrule = new RRule($rruleConfig);
                $text  = $this->renderHumanReadable($rrule);
            }

            // Append excluded dates: ", außer am 17.03.2026 und 24.03.2026"
            $excludeDates = method_exists($event, 'getRecurringExcludeDatesArray')
                ? $event->getRecurringExcludeDatesArray()
                : [];
            $hasExcludedDates = false;
            if (!empty($excludeDates)) {
                $ll        = 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang.xlf:';
                $lang      = $this->getLanguageService();
                $except    = $lang->sL($ll . 'rrule.except');
                $and       = $lang->sL($ll . 'rrule.and');
                $dateFormatter = new \IntlDateFormatter(
                    $this->getSiteLocale(),
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::NONE
                );
                $formatted = array_map(
                    fn(\DateTimeInterface $d): string => $dateFormatter->format($d) ?: $d->format('d.m.Y'),
                    $excludeDates
                );
                $last      = array_pop($formatted);
                $datesStr  = empty($formatted)
                    ? $last
                    : implode(', ', $formatted) . ' ' . $and . ' ' . $last;
                $text .= ', ' . $except . ' ' . $datesStr;
                $hasExcludedDates = true;
            }

            // Append holiday exclusion hint
            if (method_exists($event, 'getRecurringExcludeSchoolHolidays')
                || method_exists($event, 'getRecurringExcludePublicHolidays')
            ) {
                $excludeSchool = method_exists($event, 'getRecurringExcludeSchoolHolidays')
                    && $event->getRecurringExcludeSchoolHolidays();
                $excludePublic = method_exists($event, 'getRecurringExcludePublicHolidays')
                    && $event->getRecurringExcludePublicHolidays();

                if ($excludeSchool || $excludePublic) {
                    $ll   = 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang.xlf:';
                    $lang = $this->getLanguageService();
                    if ($hasExcludedDates) {
                        // Dates already appended → combine with "sowie" (no second "außer")
                        if ($excludeSchool && $excludePublic) {
                            $hint = $lang->sL($ll . 'rrule.alsoExcludingBothHolidays');
                        } elseif ($excludeSchool) {
                            $hint = $lang->sL($ll . 'rrule.alsoExcludingSchoolHolidays');
                        } else {
                            $hint = $lang->sL($ll . 'rrule.alsoExcludingPublicHolidays');
                        }
                        if ($hint !== '') {
                            $text .= ' ' . $hint;
                        }
                    } else {
                        if ($excludeSchool && $excludePublic) {
                            $hint = $lang->sL($ll . 'rrule.excludingBothHolidays');
                        } elseif ($excludeSchool) {
                            $hint = $lang->sL($ll . 'rrule.excludingSchoolHolidays');
                        } else {
                            $hint = $lang->sL($ll . 'rrule.excludingPublicHolidays');
                        }
                        if ($hint !== '') {
                            $text .= ', ' . $hint;
                        }
                    }
                }
            }

            return $text;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Custom human-readable text for hourly/minutely events with time ranges,
     * built from XLF translation keys so it works in any language.
     *
     * Example (de): "alle 30 Minuten, jeden Mittwoch, 12:00–16:00 Uhr"
     * Example (en): "every 30 minutes, every Wednesday, 12:00–16:00"
     */
    private function renderSubDayText(News $event): string
    {
        $ll   = 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang.xlf:';
        $lang = $this->getLanguageService();

        $type     = $event->getRecurringType();
        $interval = max(1, (int)$event->getRecurringInterval());

        // --- Interval part: "alle 30 Minuten" / "every 30 minutes" ---
        $unitKey      = $type === 'minutely' ? 'rrule.unit.minutes' : 'rrule.unit.hours';
        $unit         = $lang->sL($ll . $unitKey);
        $intervalPart = sprintf($lang->sL($ll . 'rrule.every'), $interval, $unit);

        // --- Days part: "jeden Mittwoch" / "every Wednesday" ---
        $daysPart = '';
        $days     = $event->getRecurringDays();
        if (!empty($days)) {
            $selected = [];
            if (is_string($days) && str_contains((string)$days, ',')) {
                $selected = array_map('intval', explode(',', (string)$days));
            } elseif (ctype_digit((string)$days) || is_int($days)) {
                $bitmask = (int)$days;
                for ($i = 0; $i < 7; $i++) {
                    if ($bitmask & (1 << $i)) {
                        $selected[] = $i;
                    }
                }
            }
            if (!empty($selected)) {
                $formatter = new \IntlDateFormatter(
                    $this->getSiteLocale(),
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    null,
                    null,
                    'EEEE'
                );
                // Use a known Monday (2025-01-06) as base; add $i days to get the weekday name
                $baseMonday = new \DateTimeImmutable('2025-01-06');
                $names = array_filter(
                    array_map(
                        fn(int $i): string => $formatter->format($baseMonday->modify("+{$i} days")) ?: '',
                        $selected
                    )
                );
                $and   = $lang->sL($ll . 'rrule.and');
                $on    = $lang->sL($ll . 'rrule.on');
                $last  = array_pop($names);
                $joined = empty($names)
                    ? $last
                    : implode(', ', $names) . ' ' . $and . ' ' . $last;
                $daysPart = $on . ' ' . $joined;
            }
        }

        // --- Time ranges part: "zwischen 12:00 und 16:00 Uhr" ---
        $timeRanges = $event->getRecurringTimeRangesArray();
        $between    = $lang->sL($ll . 'rrule.between');
        $and        = $lang->sL($ll . 'rrule.and');
        $oclock     = $lang->sL($ll . 'rrule.oclock');
        $rangeParts = array_map(
            fn($r): string => trim($between . ' ' . $r['from'] . ' ' . $and . ' ' . $r['to']),
            $timeRanges
        );
        $lastRange  = array_pop($rangeParts);
        $rangeStr   = empty($rangeParts)
            ? $lastRange
            : implode(', ', $rangeParts) . ' ' . $and . ' ' . $lastRange;
        $timesPart  = $oclock !== '' ? $rangeStr . ' ' . $oclock : $rangeStr;

        $intervalTimePart = implode(' ', array_filter([$intervalPart, $timesPart]));

        // --- Duration part: "(Dauer: 15 Minuten)" ---
        $durationPart = '';
        $start = $event->getDatetime();
        $end   = $event->getEventEnd();
        if ($start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface && $end > $start) {
            $diffMinutes = (int)(($end->getTimestamp() - $start->getTimestamp()) / 60);
            $durationPart = sprintf($lang->sL($ll . 'rrule.duration'), $diffMinutes);
        }

        $mainPart = implode(', ', array_filter([$daysPart, $intervalTimePart]));
        return implode(' ', array_filter([$mainPart, $durationPart]));
    }

    /**
     * Build a LanguageService for the current frontend language.
     */
    private function getLanguageService(): \TYPO3\CMS\Core\Localization\LanguageService
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $locale  = 'en';
        if ($request && $request->getAttribute('language')) {
            $locale = $request->getAttribute('language')->getLocale()->getName();
        }
        /** @var LanguageServiceFactory $factory */
        $factory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
        return $factory->create($locale);
    }

    private function getSiteLocale(): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request && $request->getAttribute('language')) {
            return $request->getAttribute('language')->getLocale()->getName();
        }
        return 'en';
    }

    /**
     * Render human-readable recurrence text
     */
    private function renderHumanReadable(RRule $rrule): string
    {
        try {
            // Get current site language locale
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
            $locale = 'en'; // fallback
            
            if ($request && $request->getAttribute('language')) {
                $language = $request->getAttribute('language');
                $locale = $language->getLocale()->getName();
            }
            
            // Get date format from TYPO3 settings
            $dateFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'd.m.Y';
            
            return $rrule->humanReadable([
                'locale' => $locale,
                'date_formatter' => function(\DateTimeInterface $date) use ($dateFormat) {
                    return $date->format($dateFormat);
                },
                'include_start' => false,
                'include_until' => false
            ]);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Render array of timestamps as JSON
     */
    private function renderDates(RRule $rrule): string
    {
        try {
            $dates = [];
            $count = 0;
            $maxOccurrences = 100; // Limit for performance
            
            foreach ($rrule as $occurrence) {
                if ($count >= $maxOccurrences) {
                    break;
                }
                $dates[] = $occurrence->getTimestamp();
                $count++;
            }
            
            return json_encode($dates, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return '[]';
        }
    }
}
