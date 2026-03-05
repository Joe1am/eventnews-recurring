<?php

declare(strict_types=1);

return [
    // Override eventnews mapping to use our extended model
    \Spielerj\EventnewsRecurring\Domain\Model\News::class => [
        'tableName' => 'tx_news_domain_model_news',
    ],
];