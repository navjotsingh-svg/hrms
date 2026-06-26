<?php

return [
    'default_templates' => [
        'birthday' => '{name} is celebrating a birthday today. 🎂',
        'work_anniversary' => '{name} is celebrating {years} year(s) with us today. 🎉',
        'new_joinee' => 'Welcome aboard, {name}! 👋',
    ],

    'placeholders' => [
        '{name}' => 'Employee full name',
        '{years}' => 'Years of service (anniversary only)',
        '{employee_code}' => 'Employee code',
    ],
];
