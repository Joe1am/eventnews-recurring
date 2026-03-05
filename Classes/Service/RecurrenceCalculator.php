<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\Service;

use GeorgRinger\News\Domain\Model\News;
use RRule\RRule;
use RRule\RSet;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for calculating recurring event occurrences using php-rrule library
 * 
 * This service generates event occurrences dynamically based on recurrence rules
 * without creating duplicate database records.
 */
class RecurrenceCalculator
{
    private const MAX_OCCURRENCES = 500;
    private const MAX_YEARS_AHEAD = 2;

    /**
     * Generates all occurrences of an event within a given date range
     * 
     * @param News $event The recurring event (accepts any News model)
     * @param \DateTimeImmutable $rangeStart Start of the range
     * @param \DateTimeImmutable $rangeEnd End of the range
     * @return array<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    public function generateOccurrencesForRange(
        News $event,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd
    ): array {
        // Non-recurring events
        if (!$event->getRecurringEvent()) {
            return $this->getSingleOccurrence($event, $rangeStart, $rangeEnd);
        }

        // Get event details
        $eventStart = $this->toDateTimeImmutable($event->getDatetime());
        $eventEnd = $event->getEventEnd() 
            ? $this->toDateTimeImmutable($event->getEventEnd())
            : $eventStart;
        
        $duration = $eventEnd->getTimestamp() - $eventStart->getTimestamp();
        
        // Build RRule configuration with DTSTART
        $rruleConfig = $this->buildRRuleConfig($event, $eventStart);
        
        if (empty($rruleConfig)) {
            return [];
        }
        
        try {
            $rset = new RSet();
            $rset->addRRule(new RRule($rruleConfig));

            $excludedDayStrings = $this->collectExcludedDayStrings($event);

            $occurrences = [];

            // Get occurrences in range
            foreach ($rset as $occurrence) {
                $occStart = $this->toDateTimeImmutable($occurrence);

                // Stop if we're past the range
                if ($occStart > $rangeEnd) {
                    break;
                }

                // Only include if in range
                if ($occStart >= $rangeStart) {
                    // Skip occurrences on excluded dates
                    if (!empty($excludedDayStrings) && in_array($occStart->format('Y-m-d'), $excludedDayStrings, true)) {
                        continue;
                    }

                    if (!$this->isOccurrenceInTimeRange($event, $occStart)) {
                        continue;
                    }

                    $occEnd = $occStart->modify('+' . $duration . ' seconds');
                    $occurrences[] = [
                        'start' => $occStart,
                        'end' => $occEnd
                    ];
                }
            }

            return $occurrences;
        } catch (\Exception $e) {
            // Fallback: return empty array on error
            return [];
        }
    }

    /**
     * Checks if a given date is a valid occurrence of the event
     * 
     * @param News $event The recurring event
     * @param \DateTimeImmutable $date The date to check
     * @return bool
     */
    public function isValidOccurrence(News $event, \DateTimeImmutable $date): bool
    {
        if (!$event->getRecurringEvent()) {
            $eventDate = $this->toDateTimeImmutable($event->getDatetime());
            return $date->format('Y-m-d') === $eventDate->format('Y-m-d');
        }

        // Generate occurrences for the day and check if date matches
        $dayStart = $date->setTime(0, 0, 0);
        $dayEnd = $date->setTime(23, 59, 59);
        
        $occurrences = $this->generateOccurrencesForRange($event, $dayStart, $dayEnd);
        
        foreach ($occurrences as $occ) {
            if ($occ['start']->format('Y-m-d H:i') === $date->format('Y-m-d H:i')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Gets the next occurrence after a given date
     * 
     * @param News $event The recurring event
     * @param \DateTimeImmutable $after Date to search after
     * @return \DateTimeImmutable|null
     */
    public function getNextOccurrence(News $event, \DateTimeImmutable $after): ?\DateTimeImmutable
    {
        $rangeEnd = $after->modify('+' . self::MAX_YEARS_AHEAD . ' years');
        
        $occurrences = $this->generateOccurrencesForRange($event, $after, $rangeEnd);
        
        foreach ($occurrences as $occ) {
            if ($occ['start'] > $after) {
                return $occ['start'];
            }
        }
        
        return null;
    }

    /**
     * Build RRule configuration array from event data
     * 
     * @param News $event The recurring event
     * @param \DateTimeImmutable|null $eventStart Optional event start date. If null, DTSTART will not be included in config
     * @return array RRule configuration array
     */
    public function buildRRuleConfig(News $event, ?\DateTimeImmutable $eventStart = null): array
    {
        $type = $event->getRecurringType();
        $interval = max(1, (int)$event->getRecurringInterval());
        
        // Base configuration
        $config = [
            'INTERVAL' => $interval,
        ];
        
        // Only add DTSTART if eventStart is provided
        if ($eventStart !== null) {
            // For daily/weekly recurrence, adjust DTSTART to first valid occurrence day
            if (in_array($type, ['daily', 'weekly'], true)) {
                $selectedDays = $this->getSelectedWeekdays($event);
                if (!empty($selectedDays)) {
                    $eventStart = $this->getFirstValidWeekday($eventStart, $selectedDays);
                }
            }
            
            // For full-day events, strip time from DTSTART
            $dtstart = $eventStart;
            if (method_exists($event, 'getFullDay') && $event->getFullDay()) {
                $dtstart = $eventStart->setTime(0, 0, 0);
            }

            $config['DTSTART'] = $this->adjustDtstart($dtstart, $event);
        }
        
        // Set frequency
        $freq = match ($type) {
            'daily' => RRule::DAILY,
            'weekly' => RRule::WEEKLY,
            'monthly' => RRule::MONTHLY,
            'yearly' => RRule::YEARLY,
            'hourly' => RRule::HOURLY,
            'minutely' => RRule::MINUTELY,
            default => null
        };
        
        if ($freq === null) {
            return [];
        }
        
        $config['FREQ'] = $freq;
        
        // Add COUNT or UNTIL (mutually exclusive)
        $maxCount = $this->getMaxCount($event);
        $until = $this->getUntilDate($event);
        
        if ($maxCount > 0) {
            $config['COUNT'] = $maxCount;
        } elseif ($until) {
            $config['UNTIL'] = $until;
        }
        
        // Add BYDAY for daily, weekly, hourly, and minutely recurrence
        if (in_array($type, ['daily', 'weekly', 'hourly', 'minutely'], true)) {
            $selectedDays = $this->getSelectedWeekdays($event);
            if (!empty($selectedDays)) {
                $byDay  = [];
                $dayMap = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
                foreach ($selectedDays as $dayNum) {
                    if (isset($dayMap[$dayNum])) {
                        $byDay[] = $dayMap[$dayNum];
                    }
                }
                if (!empty($byDay)) {
                    $config['BYDAY'] = $byDay;
                }
            }
        }

        // For monthly recurrence: add BYDAY for "Nth weekday of month" mode
        // e.g. 3rd Tuesday → BYDAY=3TU, last Friday → BYDAY=-1FR
        if ($type === 'monthly'
            && method_exists($event, 'getRecurringMonthlyWeek')
            && ($weekPos = (int)$event->getRecurringMonthlyWeek()) !== 0
            && method_exists($event, 'getRecurringMonthlyWeekday')
        ) {
            $dayMap  = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
            $dayCode = $dayMap[(int)$event->getRecurringMonthlyWeekday()] ?? 'MO';
            $config['BYDAY'] = [$weekPos . $dayCode]; // e.g. "3TU" or "-1FR"
        }

        // For hourly/minutely with time ranges: restrict occurrences to the configured windows.
        // hourly:   BYHOUR=every hour-slot stepping by interval, INTERVAL=1
        // minutely: BYHOUR=all hours in range, BYMINUTE=slots from interval, INTERVAL=1
        if (in_array($type, ['hourly', 'minutely'], true) && method_exists($event, 'getRecurringTimeRangesArray')) {
            $timeRanges = $event->getRecurringTimeRangesArray();
            if (!empty($timeRanges)) {
                $config = array_merge($config, $this->buildByHourMinuteConfig($type, $interval, $timeRanges));
            }
        }

        return $config;
    }

    /**
     * Get single occurrence for non-recurring event
     */
    private function getSingleOccurrence(
        News $event,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd
    ): array {
        $eventStart = $this->toDateTimeImmutable($event->getDatetime());
        $eventEnd = $event->getEventEnd()
            ? $this->toDateTimeImmutable($event->getEventEnd())
            : $eventStart;
        
        // Check if event is in range
        if ($eventStart <= $rangeEnd && $eventEnd >= $rangeStart) {
            return [[
                'start' => $eventStart,
                'end' => $eventEnd
            ]];
        }
        
        return [];
    }

    /**
     * Get selected weekdays from event
     * 
     * @return int[] Array of weekday numbers (0=Sunday, 1=Monday, ..., 6=Saturday)
     */
    private function getSelectedWeekdays(News $event): array
    {
        $days = $event->getRecurringDays();
        
        if (empty($days)) {
            return [];
        }
        
        // TCA stores as comma-separated bit positions (0-6)
        // Checkboxes are stored as powers of 2
        $selectedDays = [];
        
        // If it's a string like "1,3,5", split it
        if (is_string($days) && str_contains($days, ',')) {
            return array_map('intval', explode(',', $days));
        }
        
        // If it's a bitmask (integer)
        if (is_int($days) || ctype_digit($days)) {
            $bitmask = (int)$days;
            for ($i = 0; $i < 7; $i++) {
                if ($bitmask & (1 << $i)) {
                    $selectedDays[] = $i;
                }
            }
            return $selectedDays;
        }
        
        return [];
    }

    /**
     * Adjust start date to first valid weekday if current day is not selected
     * 
     * @param \DateTimeImmutable $start Original start date
     * @param int[] $selectedDays Array of weekday numbers (0=Monday, 1=Tuesday, ..., 6=Sunday in TCA format)
     * @return \DateTimeImmutable Adjusted start date
     */
    private function getFirstValidWeekday(\DateTimeImmutable $start, array $selectedDays): \DateTimeImmutable
    {
        if (empty($selectedDays)) {
            return $start;
        }
        
        // Convert PHP weekday (1=Monday, 7=Sunday) to TCA format (0=Monday, 6=Sunday)
        $currentWeekday = (int)$start->format('N') - 1; // N: 1-7 -> 0-6
        
        // If current day is already in selected days, return as is
        if (in_array($currentWeekday, $selectedDays, true)) {
            return $start;
        }
        
        // Find next valid day (max 7 days ahead)
        for ($i = 1; $i <= 7; $i++) {
            $testDate = $start->modify("+{$i} day");
            $testWeekday = (int)$testDate->format('N') - 1;
            
            if (in_array($testWeekday, $selectedDays, true)) {
                return $testDate;
            }
        }
        
        // Fallback (should never happen)
        return $start;
    }

    /**
     * Get maximum count from event
     */
    private function getMaxCount(News $event): int
    {
        $count = (int)$event->getRecurringCount();
        return $count > 0 ? min($count, self::MAX_OCCURRENCES) : 0;
    }

    /**
     * Get until date from event
     */
    private function getUntilDate(News $event): ?\DateTimeImmutable
    {
        $until = $event->getRecurringUntil();
        
        if (!$until) {
            return null;
        }
        
        return $this->toDateTimeImmutable($until);
    }

    /**
     * Public wrapper – returns all excluded Y-m-d strings for use in ViewHelpers/ICS export.
     *
     * @return string[]
     */
    public function getExcludedDayStrings(News $event): array
    {
        return $this->collectExcludedDayStrings($event);
    }

    /**
     * Collects all excluded Y-m-d day strings for an event:
     * manual exclude dates + school/public holiday days from ICS calendars.
     *
     * @return string[]
     */
    private function collectExcludedDayStrings(News $event): array
    {
        $excludedDayStrings = [];

        if (method_exists($event, 'getRecurringExcludeDatesArray')) {
            foreach ($event->getRecurringExcludeDatesArray() as $exDate) {
                $excludedDayStrings[] = $exDate->format('Y-m-d');
            }
        }

        if (method_exists($event, 'getRecurringExcludeSchoolHolidays')
            || method_exists($event, 'getRecurringExcludePublicHolidays')
        ) {
            $excludeSchool = method_exists($event, 'getRecurringExcludeSchoolHolidays')
                && $event->getRecurringExcludeSchoolHolidays();
            $excludePublic = method_exists($event, 'getRecurringExcludePublicHolidays')
                && $event->getRecurringExcludePublicHolidays();

            if ($excludeSchool || $excludePublic) {
                $site = null;
                try {
                    $site = GeneralUtility::makeInstance(SiteFinder::class)
                        ->getSiteByPageId((int)$event->getPid());
                } catch (\Throwable $e) {
                    // Site not found – skip holiday exclusion gracefully
                }

                if ($site !== null) {
                    $holidayService = GeneralUtility::makeInstance(HolidayService::class);
                    if ($excludeSchool) {
                        $excludedDayStrings = array_merge(
                            $excludedDayStrings,
                            $holidayService->getSchoolHolidayDays($site)
                        );
                    }
                    if ($excludePublic) {
                        $excludedDayStrings = array_merge(
                            $excludedDayStrings,
                            $holidayService->getPublicHolidayDays($site)
                        );
                    }
                }
            }
        }

        return array_values(array_unique($excludedDayStrings));
    }

    /**
     * Returns false if the occurrence falls outside all configured time windows.
     * Returns true for non-hourly/minutely events or when no time ranges are set.
     */
    private function isOccurrenceInTimeRange(News $event, \DateTimeImmutable $occStart): bool
    {
        if (!in_array($event->getRecurringType(), ['hourly', 'minutely'], true)
            || !method_exists($event, 'getRecurringTimeRangesArray')
        ) {
            return true;
        }

        $timeRanges = $event->getRecurringTimeRangesArray();
        if (empty($timeRanges)) {
            return true;
        }

        $recurringType = $event->getRecurringType();
        $occHour    = (int)$occStart->format('H');
        $occMinutes = $occHour * 60 + (int)$occStart->format('i');

        // For minutely: compute the highest minute slot per hour
        $maxMinuteSlot = 0;
        if ($recurringType === 'minutely') {
            $step = max(1, (int)$event->getRecurringInterval());
            for ($m = 0; $m < 60; $m += $step) {
                $maxMinuteSlot = $m;
            }
        }

        foreach ($timeRanges as $range) {
            $fromParts   = explode(':', $range['from']);
            $toParts     = explode(':', $range['to']);
            $fromMinutes = (int)$fromParts[0] * 60 + (int)($fromParts[1] ?? 0);
            $toMinutes   = (int)$toParts[0]   * 60 + (int)($toParts[1]   ?? 0);
            if ($recurringType === 'minutely') {
                // Valid when: occurrence starts in window AND last minute-slot fits too
                if ($occMinutes >= $fromMinutes && $occHour * 60 + $maxMinuteSlot <= $toMinutes) {
                    return true;
                }
            } else {
                if ($occMinutes >= $fromMinutes && $occMinutes <= $toMinutes) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Shifts DTSTART to the earliest 'from' time of the configured time ranges
     * for hourly/minutely events so DTSTART aligns with the BYHOUR pattern.
     */
    private function adjustDtstart(\DateTimeImmutable $dtstart, News $event): \DateTimeImmutable
    {
        if (!in_array($event->getRecurringType(), ['hourly', 'minutely'], true)
            || !method_exists($event, 'getRecurringTimeRangesArray')
        ) {
            return $dtstart;
        }

        $timeRanges = $event->getRecurringTimeRangesArray();
        if (empty($timeRanges)) {
            return $dtstart;
        }

        $earliestMinutes = null;
        $earliestH = 0;
        $earliestM = 0;
        foreach ($timeRanges as $range) {
            $fp    = explode(':', $range['from']);
            $fh    = (int)($fp[0] ?? 0);
            $fm    = (int)($fp[1] ?? 0);
            $total = $fh * 60 + $fm;
            if ($earliestMinutes === null || $total < $earliestMinutes) {
                $earliestMinutes = $total;
                $earliestH = $fh;
                $earliestM = $fm;
            }
        }

        return $earliestMinutes !== null ? $dtstart->setTime($earliestH, $earliestM, 0) : $dtstart;
    }

    /**
     * Builds BYHOUR / BYMINUTE / INTERVAL config for hourly or minutely time-range events.
     *
     * hourly:   BYHOUR = hour slots stepping by $step, INTERVAL = 1
     * minutely: BYHOUR = all valid hours, BYMINUTE = minute positions from $step, INTERVAL = 1
     *
     * @param array<array{from: string, to: string}> $timeRanges
     * @return array Partial RRule config to merge
     */
    private function buildByHourMinuteConfig(string $type, int $interval, array $timeRanges): array
    {
        $step = max(1, $interval);

        if ($type === 'hourly') {
            $hourSlots = [];
            foreach ($timeRanges as $range) {
                $fromHour = (int)(explode(':', $range['from'])[0] ?? 0);
                $toHour   = (int)(explode(':', $range['to'])[0]   ?? 0);
                for ($h = $fromHour; $h <= $toHour; $h += $step) {
                    $hourSlots[] = $h;
                }
            }
            sort($hourSlots);
            $hourSlots = array_values(array_unique($hourSlots));
            return !empty($hourSlots) ? ['BYHOUR' => $hourSlots, 'INTERVAL' => 1] : [];
        }

        // minutely
        $minuteSlots = [];
        for ($m = 0; $m < 60; $m += $step) {
            $minuteSlots[] = $m;
        }
        $maxMinuteSlot = !empty($minuteSlots) ? max($minuteSlots) : 0;

        $hourSlots = [];
        foreach ($timeRanges as $range) {
            $fromHour  = (int)(explode(':', $range['from'])[0] ?? 0);
            $toParts   = explode(':', $range['to']);
            $toHour    = (int)($toParts[0] ?? 0);
            $toMinutes = (int)($toParts[0] ?? 0) * 60 + (int)($toParts[1] ?? 0);
            for ($h = $fromHour; $h <= $toHour; $h++) {
                if ($h * 60 + $maxMinuteSlot <= $toMinutes) {
                    $hourSlots[] = $h;
                }
            }
        }
        sort($hourSlots);
        $hourSlots = array_values(array_unique($hourSlots));

        return !empty($hourSlots)
            ? ['BYHOUR' => $hourSlots, 'BYMINUTE' => $minuteSlots, 'INTERVAL' => 1]
            : [];
    }

    /**
     * Convert various date formats to DateTimeImmutable
     */
    public function toDateTimeImmutable($value): \DateTimeImmutable
    {
        
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        
        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }
        
        // Assume integer timestamp
        $timestamp = (int)$value;
        if ($timestamp > 0) {
            // TYPO3 stores timestamps - create DateTimeImmutable from timestamp
            // setTimestamp() interprets the timestamp as UTC and automatically converts
            $dt = new \DateTimeImmutable('now');
            return $dt->setTimestamp($timestamp);
        }
        
        return new \DateTimeImmutable('now');
    }
}
