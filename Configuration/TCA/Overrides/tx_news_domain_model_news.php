<?php

defined('TYPO3') || die();

$fields = [
    'recurring_event' => [
        'exclude' => true,
        'displayCond' => 'FIELD:is_event:>:0',
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_event',
        'onChange' => 'reload',
        'config' => [
            'type' => 'check',
            'default' => 0
        ]
    ],
    'recurring_type' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.description',
        'onChange' => 'reload',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.minutely', 'value' => 'minutely'],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.hourly', 'value' => 'hourly'],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.daily', 'value' => 'daily'],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.weekly', 'value' => 'weekly'],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.monthly', 'value' => 'monthly'],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_type.yearly', 'value' => 'yearly'],
            ],
            'default' => 'daily'
        ],
    ],
    'recurring_interval' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_interval',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_interval.description',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'default' => 1,
            'range' => [
                'lower' => 1,
                'upper' => 365
            ]
        ],
    ],
    'recurring_days' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
                'OR' => [
                    'FIELD:recurring_type:=:daily',
                    'FIELD:recurring_type:=:weekly',
                    'FIELD:recurring_type:=:hourly',
                    'FIELD:recurring_type:=:minutely',
                ],
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_days',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_days.description',
        'config' => [
            'type' => 'check',
            'items' => [
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.monday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.tuesday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.wednesday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.thursday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.friday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.saturday'],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.sunday'],
            ],
            'cols' => 7
        ],
    ],
    'recurring_until' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_until',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_until.description',
        'config' => [
            'type' => 'datetime',
            'format' => 'date',
            'default' => 0,
        ],
    ],
    'recurring_count' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_count',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_count.description',
        'config' => [
            'type' => 'number',
            'size' => 5,
            'default' => 0,
            'range' => [
                'lower' => 0,
                'upper' => 999
            ]
        ],
    ],
    'recurring_exclude_dates' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_dates',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_dates.description',
        'config' => [
            'type' => 'user',
            'renderType' => 'eventnewsRecurringRepeatableDate',
            'labelAdd' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_dates.add',
            'labelRemove' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_dates.remove',
            'labelOpenPicker' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_dates.open_picker',
        ],
    ],
    'recurring_time_ranges' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
                'OR' => [
                    'FIELD:recurring_type:=:hourly',
                    'FIELD:recurring_type:=:minutely',
                ],
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges.description',
        'config' => [
            'type' => 'user',
            'renderType' => 'eventnewsRecurringRepeatableTimeRange',
            'labelAdd' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges.add',
            'labelRemove' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges.remove',
            'labelFrom' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges.from',
            'labelTo' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_time_ranges.to',
        ],
    ],
    'recurring_exclude_school_holidays' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_school_holidays',
        'config' => [
            'type' => 'check',
            'default' => 0,
        ],
    ],
    'recurring_exclude_public_holidays' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_exclude_public_holidays',
        'config' => [
            'type' => 'check',
            'default' => 0,
        ],
    ],
    'recurring_monthly_week' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
                'FIELD:recurring_type:=:monthly',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.description',
        'onChange' => 'reload',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.bydate', 'value' => 0],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.first', 'value' => 1],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.second', 'value' => 2],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.third', 'value' => 3],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.fourth', 'value' => 4],
                ['label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_week.last', 'value' => -1],
            ],
            'default' => 0,
        ],
    ],
    'recurring_monthly_weekday' => [
        'exclude' => true,
        'displayCond' => [
            'AND' => [
                'FIELD:is_event:>:0',
                'FIELD:recurring_event:>:0',
                'FIELD:recurring_type:=:monthly',
                'FIELD:recurring_monthly_week:!=:0',
            ]
        ],
        'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_weekday',
        'description' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.recurring_monthly_weekday.description',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.monday', 'value' => 0],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.tuesday', 'value' => 1],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.wednesday', 'value' => 2],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.thursday', 'value' => 3],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.friday', 'value' => 4],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.saturday', 'value' => 5],
                ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.sunday', 'value' => 6],
            ],
            'default' => 0,
        ],
    ],
];

// Create palette for recurring settings
$GLOBALS['TCA']['tx_news_domain_model_news']['palettes']['palette_recurring'] = [
    'label' => 'LLL:EXT:eventnews_recurring/Resources/Private/Language/locallang_db.xlf:palette.recurring_settings',
    'showitem' => 'recurring_type,recurring_interval,--linebreak--,recurring_monthly_week,recurring_monthly_weekday,--linebreak--,recurring_days,--linebreak--,recurring_time_ranges,--linebreak--,recurring_until,recurring_count,--linebreak--,recurring_exclude_dates,--linebreak--,recurring_exclude_school_holidays,recurring_exclude_public_holidays'
];

// Add fields to TCA
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tx_news_domain_model_news', $fields);

// Add recurring_event checkbox after full_day in palette_event
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'tx_news_domain_model_news',
    'palette_event',
    'recurring_event',
    'after:full_day'
);

// Add recurring settings palette after palette_event
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'tx_news_domain_model_news',
    '--palette--;;palette_recurring',
    '',
    'after:--palette--;;palette_event'
);
