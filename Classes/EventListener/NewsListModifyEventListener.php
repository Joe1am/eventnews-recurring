<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\EventListener;

use GeorgRinger\News\Event\NewsListActionEvent;
use Spielerj\EventnewsRecurring\Service\RecurrenceCalculator;
use Spielerj\EventnewsRecurring\Persistence\OccurrenceQueryResult;

/**
 * Event Listener to expand recurring events in News list action
 */
final class NewsListModifyEventListener
{
    private RecurrenceCalculator $recurrenceCalculator;

    public function __construct(RecurrenceCalculator $recurrenceCalculator)
    {
        $this->recurrenceCalculator = $recurrenceCalculator;
    }

    public function __invoke(NewsListActionEvent $event): void
    {
        $assignedValues = $event->getAssignedValues();
        $news = $assignedValues['news'] ?? null;
        $demand = $assignedValues['demand'] ?? null;
        $settings = $assignedValues['settings'] ?? [];

        if (!$news) {
            return;
        }

        // Check if recurring event expansion is enabled (default: false)
        $expandRecurring = isset($settings['expandRecurringEvents']) 
            && (bool)$settings['expandRecurringEvents'];
        
        if (!$expandRecurring) {
            return;
        }

        // Check if there are any recurring events
        $hasRecurring = false;
        foreach ($news as $newsItem) {
            if (method_exists($newsItem, 'getRecurringEvent') && $newsItem->getRecurringEvent()) {
                $hasRecurring = true;
                break;
            }
        }

        // If no recurring events, don't modify anything
        if (!$hasRecurring) {
            return;
        }

        // Determine date range
        $rangeStart = $this->getRangeStart($demand);
        $rangeEnd = $this->getRangeEnd($demand, $rangeStart);

        // Expand news to occurrences
        $occurrences = $this->expandNewsToOccurrences($news, $rangeStart, $rangeEnd);

        // Apply limit from settings if set and no pagination
        $limit = isset($settings['limit']) ? (int)$settings['limit'] : 0;
        
        if ($limit > 0) {
            $occurrences = array_slice($occurrences, 0, $limit);
        }

        // Wrap occurrences in a QueryResult-compatible object
        $occurrenceQueryResult = new OccurrenceQueryResult($occurrences);
        $assignedValues['news'] = $occurrenceQueryResult;
        
        $event->setAssignedValues($assignedValues);
    }

    private function getRangeStart($demand): \DateTimeImmutable
    {
        if ($demand && method_exists($demand, 'getSearchDateFrom')) {
            $searchFrom = $demand->getSearchDateFrom();
            if ($searchFrom) {
                try {
                    return new \DateTimeImmutable($searchFrom);
                } catch (\Exception $e) {
                }
            }
        }
        return new \DateTimeImmutable('now');
    }

    private function getRangeEnd($demand, \DateTimeImmutable $rangeStart): \DateTimeImmutable
    {
        if ($demand && method_exists($demand, 'getSearchDateTo')) {
            $searchTo = $demand->getSearchDateTo();
            if ($searchTo) {
                try {
                    return new \DateTimeImmutable($searchTo);
                } catch (\Exception $e) {
                }
            }
        }

        // Check for year/month
        if ($demand && method_exists($demand, 'getYear') && $demand->getYear()) {
            $year = $demand->getYear();
            $month = method_exists($demand, 'getMonth') ? $demand->getMonth() : 0;
            
            if ($month > 0) {
                $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                return new \DateTimeImmutable("$year-$month-$lastDay 23:59:59");
            } else {
                return new \DateTimeImmutable("$year-12-31 23:59:59");
            }
        }

        return $rangeStart->modify('+12 months');
    }

    private function expandNewsToOccurrences(
        iterable $news,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd
    ): array {
        $occurrences = [];

        foreach ($news as $newsItem) {
            if (!method_exists($newsItem, 'getIsEvent') || !$newsItem->getIsEvent()) {
                $occurrences[] = $newsItem;
                continue;
            }

            if (method_exists($newsItem, 'getRecurringEvent') && $newsItem->getRecurringEvent()) {
                // Recurring: generate occurrences
                $generated = $this->recurrenceCalculator->generateOccurrencesForRange(
                    $newsItem,
                    $rangeStart,
                    $rangeEnd
                );

                foreach ($generated as $occ) {
                    $virtual = clone $newsItem;
                    if (method_exists($virtual, 'setDatetime')) {
                        $virtual->setDatetime(\DateTime::createFromImmutable($occ['start']));
                    }
                    if (method_exists($virtual, 'setEventEnd')) {
                        $virtual->setEventEnd(\DateTime::createFromImmutable($occ['end']));
                    }
                    $occurrences[] = $virtual;
                }
            } else {
                // Non-recurring
                $eventDate = $newsItem->getDatetime();
                if ($eventDate) {
                    $eventDateImmutable = \DateTimeImmutable::createFromMutable($eventDate);
                    if ($eventDateImmutable >= $rangeStart && $eventDateImmutable <= $rangeEnd) {
                        $occurrences[] = $newsItem;
                    }
                } else {
                    $occurrences[] = $newsItem;
                }
            }
        }

        // Sort by datetime
        usort($occurrences, function($a, $b) {
            $aTime = $a->getDatetime() ? $a->getDatetime()->getTimestamp() : 0;
            $bTime = $b->getDatetime() ? $b->getDatetime()->getTimestamp() : 0;
            return $aTime <=> $bTime;
        });

        return $occurrences;
    }
}
