<?php

namespace Spielerj\EventnewsRecurring\Domain\Model;

/**
 * Extended News model with recurring event support
 */
class News extends \GeorgRinger\Eventnews\Domain\Model\News
{
    /**
     * @var bool
     */
    protected $recurringEvent = false;

    /**
     * @var string
     */
    protected $recurringType = '';

    /**
     * @var int
     */
    protected $recurringInterval = 1;

    /**
     * @var string
     */
    protected $recurringDays = '';

    /**
     * @var \DateTime
     */
    protected $recurringUntil = null;

    /**
     * @var int
     */
    protected $recurringCount = 0;

    /**
     * @return bool
     */
    public function getRecurringEvent(): bool
    {
        return $this->recurringEvent;
    }

    /**
     * @param bool $recurringEvent
     */
    public function setRecurringEvent(bool $recurringEvent): void
    {
        $this->recurringEvent = $recurringEvent;
    }

    /**
     * @return string
     */
    public function getRecurringType(): string
    {
        return $this->recurringType;
    }

    /**
     * @param string $recurringType
     */
    public function setRecurringType(string $recurringType): void
    {
        $this->recurringType = $recurringType;
    }

    /**
     * @return int
     */
    public function getRecurringInterval(): int
    {
        return $this->recurringInterval;
    }

    /**
     * @param int $recurringInterval
     */
    public function setRecurringInterval(int $recurringInterval): void
    {
        $this->recurringInterval = $recurringInterval;
    }

    /**
     * @return string
     */
    public function getRecurringDays(): string
    {
        return $this->recurringDays;
    }

    /**
     * @param string $recurringDays
     */
    public function setRecurringDays(string $recurringDays): void
    {
        $this->recurringDays = $recurringDays;
    }

    /**
     * @return \DateTime
     */
    public function getRecurringUntil(): ?\DateTime
    {
        return $this->recurringUntil;
    }

    /**
     * @param \DateTime $recurringUntil
     */
    public function setRecurringUntil(?\DateTime $recurringUntil): void
    {
        $this->recurringUntil = $recurringUntil;
    }

    /**
     * @return int
     */
    public function getRecurringCount(): int
    {
        return $this->recurringCount;
    }

    /**
     * @param int $recurringCount
     */
    public function setRecurringCount(int $recurringCount): void
    {
        $this->recurringCount = $recurringCount;
    }

    /**
     * Helper method: Get recurring days as array
     * Converts comma-separated string or bitmask to array of day numbers
     *
     * @return int[] Array of day numbers (0=Sunday, 1=Monday, ..., 6=Saturday)
     */
    public function getRecurringDaysArray(): array
    {
        if (empty($this->recurringDays)) {
            return [];
        }

        // If comma-separated string
        if (str_contains($this->recurringDays, ',')) {
            return array_map('intval', explode(',', $this->recurringDays));
        }

        // If bitmask (from checkboxes)
        $bitmask = (int)$this->recurringDays;
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            if ($bitmask & (1 << $i)) {
                $days[] = $i;
            }
        }
        return $days;
    }

    /**
     * Check if this is a recurring event
     *
     * @return bool
     */
    public function isRecurringEvent(): bool
    {
        return $this->recurringEvent;
    }

    /**
     * @var string
     */
    protected $recurringExcludeDates = '';

    /**
     * @return string
     */
    public function getRecurringExcludeDates(): string
    {
        return $this->recurringExcludeDates;
    }

    /**
     * @param string $recurringExcludeDates
     */
    public function setRecurringExcludeDates(string $recurringExcludeDates): void
    {
        $this->recurringExcludeDates = $recurringExcludeDates;
    }

    /**
     * Returns the exclude dates as an array of DateTimeImmutable objects.
     *
     * @return \DateTimeImmutable[]
     */
    public function getRecurringExcludeDatesArray(): array
    {
        if (empty($this->recurringExcludeDates)) {
            return [];
        }
        $dates = [];
        foreach (array_filter(array_map('trim', explode(',', $this->recurringExcludeDates))) as $dateStr) {
            try {
                $dates[] = new \DateTimeImmutable($dateStr);
            } catch (\Exception $e) {
                // skip malformed entries
            }
        }
        return $dates;
    }

    /**
     * @var string
     */
    protected $recurringTimeRanges = '';

    /**
     * @return string
     */
    public function getRecurringTimeRanges(): string
    {
        return $this->recurringTimeRanges;
    }

    /**
     * @param string $recurringTimeRanges
     */
    public function setRecurringTimeRanges(string $recurringTimeRanges): void
    {
        $this->recurringTimeRanges = $recurringTimeRanges;
    }

    /**
     * Returns time ranges as array of ['from' => 'HH:MM', 'to' => 'HH:MM'] pairs.
     *
     * @return array<array{from: string, to: string}>
     */
    public function getRecurringTimeRangesArray(): array
    {
        if (empty($this->recurringTimeRanges)) {
            return [];
        }
        $ranges = [];
        foreach (array_filter(array_map('trim', explode(',', $this->recurringTimeRanges))) as $item) {
            $parts = explode('-', $item, 2);
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $ranges[] = ['from' => $parts[0], 'to' => $parts[1]];
            }
        }
        return $ranges;
    }

    /**
     * @var bool
     */
    protected $recurringExcludeSchoolHolidays = false;

    public function getRecurringExcludeSchoolHolidays(): bool
    {
        return $this->recurringExcludeSchoolHolidays;
    }

    public function setRecurringExcludeSchoolHolidays(bool $recurringExcludeSchoolHolidays): void
    {
        $this->recurringExcludeSchoolHolidays = $recurringExcludeSchoolHolidays;
    }

    /**
     * @var bool
     */
    protected $recurringExcludePublicHolidays = false;

    public function getRecurringExcludePublicHolidays(): bool
    {
        return $this->recurringExcludePublicHolidays;
    }

    public function setRecurringExcludePublicHolidays(bool $recurringExcludePublicHolidays): void
    {
        $this->recurringExcludePublicHolidays = $recurringExcludePublicHolidays;
    }

    /**
     * Ordinal week position for monthly weekday recurrence.
     * 0 = disabled (use day-of-month from DTSTART), 1–4 = 1st–4th, -1 = last
     *
     * @var int
     */
    protected $recurringMonthlyWeek = 0;

    public function getRecurringMonthlyWeek(): int
    {
        return $this->recurringMonthlyWeek;
    }

    public function setRecurringMonthlyWeek(int $recurringMonthlyWeek): void
    {
        $this->recurringMonthlyWeek = $recurringMonthlyWeek;
    }

    /**
     * Weekday for monthly weekday recurrence (only used when recurringMonthlyWeek != 0).
     * 0=Monday, 1=Tuesday, 2=Wednesday, 3=Thursday, 4=Friday, 5=Saturday, 6=Sunday
     *
     * @var int
     */
    protected $recurringMonthlyWeekday = 0;

    public function getRecurringMonthlyWeekday(): int
    {
        return $this->recurringMonthlyWeekday;
    }

    public function setRecurringMonthlyWeekday(int $recurringMonthlyWeekday): void
    {
        $this->recurringMonthlyWeekday = $recurringMonthlyWeekday;
    }
}