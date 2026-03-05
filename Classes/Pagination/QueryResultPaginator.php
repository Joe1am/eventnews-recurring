<?php

declare(strict_types=1);

namespace Spielerj\EventnewsRecurring\Pagination;

use GeorgRinger\News\Pagination\CustomAbstractPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Spielerj\EventnewsRecurring\Persistence\OccurrenceQueryResult;

/**
 * XClass replacement for QueryResultPaginator to handle OccurrenceQueryResult
 * Since the original is final, we reimplement it completely
 */
class QueryResultPaginator extends CustomAbstractPaginator
{
    private QueryResultInterface $queryResult;
    private ?iterable $paginatedQueryResult = null;

    public function __construct(
        QueryResultInterface $queryResult,
        int $currentPageNumber = 1,
        int $itemsPerPage = 10,
        int $initialLimit = 0,
        int $initialOffset = 0
    ) {
        $this->queryResult = $queryResult;
        $this->setCurrentPageNumber($currentPageNumber);
        $this->setItemsPerPage($itemsPerPage);
        $this->initialLimit = $initialLimit;
        $this->initialOffset = $initialOffset;
        $this->updateInternalState();
    }

    protected function updatePaginatedItems(int $limit, int $offset): void
    {
        // Handle OccurrenceQueryResult differently
        if ($this->queryResult instanceof OccurrenceQueryResult) {
            $allItems = $this->queryResult->toArray();
            $this->paginatedQueryResult = array_slice($allItems, $offset, $limit);
        } else {
            // Standard QueryResult with database query
            $query = $this->queryResult->getQuery();
            if ($query) {
                $this->paginatedQueryResult = $query
                    ->setLimit($limit)
                    ->setOffset($offset)
                    ->execute();
            }
        }
    }

    public function getPaginatedItems(): iterable
    {
        return $this->paginatedQueryResult ?? [];
    }

    protected function getTotalAmountOfItems(): int
    {
        $total = $this->queryResult->count();
        
        // Respect initialLimit from plugin settings
        if ($this->initialLimit > 0 && $total > $this->initialLimit) {
            return $this->initialLimit;
        }
        
        return $total;
    }

    protected function getAmountOfItemsOnCurrentPage(): int
    {
        if ($this->paginatedQueryResult === null) {
            return 0;
        }
        
        if (is_array($this->paginatedQueryResult)) {
            return count($this->paginatedQueryResult);
        }
        
        if ($this->paginatedQueryResult instanceof \Countable) {
            return $this->paginatedQueryResult->count();
        }
        
        return iterator_count($this->paginatedQueryResult);
    }
}
