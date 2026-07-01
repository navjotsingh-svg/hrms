<?php

return [
    'survey_question_types' => [
        'text' => 'Short Text',
        'textarea' => 'Long Text',
        'rating' => 'Rating (1–5)',
        'select' => 'Single Choice',
        'yes_no' => 'Yes / No',
    ],

    'default_survey_questions' => [
        [
            'question' => 'What was your primary reason for leaving?',
            'type' => 'textarea',
            'options' => null,
            'is_required' => true,
            'sort_order' => 1,
        ],
        [
            'question' => 'How would you rate your overall experience working here?',
            'type' => 'rating',
            'options' => null,
            'is_required' => true,
            'sort_order' => 2,
        ],
        [
            'question' => 'What did you enjoy most about working here?',
            'type' => 'textarea',
            'options' => null,
            'is_required' => true,
            'sort_order' => 3,
        ],
        [
            'question' => 'What could the organization improve?',
            'type' => 'textarea',
            'options' => null,
            'is_required' => true,
            'sort_order' => 4,
        ],
        [
            'question' => 'Would you recommend this company to others? Why or why not?',
            'type' => 'textarea',
            'options' => null,
            'is_required' => true,
            'sort_order' => 5,
        ],
    ],
];
