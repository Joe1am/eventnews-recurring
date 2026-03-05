<?php

defined('TYPO3') || die();

(function () {
    /***********
    * Extend EXT:news
    */

    // Register extended News model
    $GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['classes']['Domain/Model/News'][] = 'eventnews_recurring';

    // Register extended NewsRepository with recurring support
    $GLOBALS['TYPO3_CONF_VARS']['EXT']['news']['classes']['Domain/Repository/NewsRepository'][] = 'eventnews_recurring';
    
    // XClass QueryResultPaginator to handle OccurrenceQueryResult
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\GeorgRinger\News\Pagination\QueryResultPaginator::class] = [
        'className' => \Spielerj\EventnewsRecurring\Pagination\QueryResultPaginator::class
    ];

    // Repeatable date-picker element (Y-m-d values, comma-separated)
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1709550001] = [
        'nodeName' => 'eventnewsRecurringRepeatableDate',
        'priority' => 40,
        'class' => \Spielerj\EventnewsRecurring\Form\Element\RepeatableDateElement::class,
    ];

    // Repeatable time-range element (HH:MM-HH:MM values, comma-separated)
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1709550002] = [
        'nodeName' => 'eventnewsRecurringRepeatableTimeRange',
        'priority' => 40,
        'class' => \Spielerj\EventnewsRecurring\Form\Element\RepeatableTimeRangeElement::class,
    ];

    // Persistent cache for holiday ICS data (FAL files + remote URLs)
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['eventnews_recurring_holidays'] ??= [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend'  => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
        'options'  => ['defaultLifetime' => 86400],
        'groups'   => ['pages'],
    ];



})();
