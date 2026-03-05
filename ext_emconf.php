<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Event News Recurring',
    'description' => 'Adds recurring event support to georgringer/eventnews. Automatically generates event instances based on recurrence patterns.',
    'category' => 'plugin',
    'author' => '',
    'author_email' => '',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
            'news' => '12.0.0-12.99.99',
            'eventnews' => '7.0.0-7.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
