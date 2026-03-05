<?php
declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use GeorgRinger\News\Domain\Model\News;
use Spielerj\EventnewsRecurring\Service\RecurrenceCalculator;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ViewHelper to get the adjusted start date for recurring events
 * 
 * For weekly recurring events, this returns the first valid occurrence date
 * if the original start date doesn't match the selected weekdays.
 * 
 * Usage in Fluid:
 * <enr:adjustedStartDate event="{newsItem}" />
 * 
 * Returns a timestamp that can be used with f:format.date
 */
class AdjustedStartDateViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('event', News::class, 'The news event object', true);
    }

    public function render(): int
    {
        $event = $this->arguments['event'];
        
        // For non-recurring events, return original datetime
        if (!$event->getRecurringEvent()) {
            return $event->getDatetime()->getTimestamp();
        }
        
        try {
            $calculator = GeneralUtility::makeInstance(RecurrenceCalculator::class);
            $eventStart = $calculator->toDateTimeImmutable($event->getDatetime());
            $rruleConfig = $calculator->buildRRuleConfig($event, $eventStart);
            
            if (empty($rruleConfig) || !isset($rruleConfig['DTSTART'])) {
                return $event->getDatetime()->getTimestamp();
            }
            
            // Return the adjusted DTSTART timestamp
            return $rruleConfig['DTSTART']->getTimestamp();
        } catch (\Exception $e) {
            return $event->getDatetime()->getTimestamp();
        }
    }
}
