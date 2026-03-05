<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\EventListener;

use GeorgRinger\News\Event\ModifyDemandRepositoryEvent;
use GeorgRinger\News\Domain\Model\News;

/**
 * Modifies the query to include recurring events regardless of their original datetime
 */
final class NewsListQueryModifyEventListener
{
    public function __invoke(ModifyDemandRepositoryEvent $event): void
    {       
        $queryType = $event->getQuery()->getType();
        
        if ($queryType !== News::class && !is_subclass_of($queryType, News::class)) {
            return;
        }
        
        $query = $event->getQuery();
        $constraints = $event->getConstraints();
        
        // Check if there are any datetime constraints
        $hasDatetimeConstraints = isset($constraints['datetime']) 
            || isset($constraints['datetimeSearch']) 
            || isset($constraints['timeRestrictionGreater']);
        
        if (!$hasDatetimeConstraints) {
            // No datetime filtering, nothing to do
            return;
        }
        
        // Strategy: Remove datetime constraints for recurring events
        // Result: (normal events with datetime filter) OR (all recurring events)
        
        $nonDatetimeConstraints = [];
        $datetimeConstraints = [];
        
        foreach ($constraints as $key => $constraint) {
            // Identify datetime-related constraints by their keys
            if (is_string($key) && (
                $key === 'datetime' || 
                $key === 'datetimeSearch' || 
                $key === 'timeRestrictionGreater' ||
                strpos($key, 'datetime') !== false || 
                strpos($key, 'timeRestriction') !== false
            )) {
                $datetimeConstraints[$key] = $constraint;
            } else {
                $nonDatetimeConstraints[] = $constraint;
            }
        }
        
        // Build combined constraint:
        // (non-datetime AND datetime constraints) OR (non-datetime AND recurringEvent=1)
        $recurringConstraint = $query->equals('recurringEvent', 1);
        
        // Original: all constraints together
        $normalEventConstraints = array_merge($nonDatetimeConstraints, array_values($datetimeConstraints));
        
        // Recurring events: only non-datetime constraints + recurring flag
        $recurringEventConstraints = array_merge($nonDatetimeConstraints, [$recurringConstraint]);
        
        $combinedConstraint = $query->logicalOr(
            count($normalEventConstraints) > 1 ? $query->logicalAnd(...$normalEventConstraints) : $normalEventConstraints[0],
            count($recurringEventConstraints) > 1 ? $query->logicalAnd(...$recurringEventConstraints) : $recurringEventConstraints[0]
        );
        
        $event->setConstraints([$combinedConstraint]);
    }
}