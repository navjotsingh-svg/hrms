<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Income tax slabs (India — FY 2024-25 onward, illustrative defaults)
    |--------------------------------------------------------------------------
    |
    | Amounts are annual taxable income after standard deduction and PF.
    | Update slabs here when tax laws change.
    |
    */
    'income_tax' => [
        'new' => [
            'standard_deduction' => 75000,
            'rebate' => [
                'max_taxable_income' => 700000,
                'max_tax' => null,
            ],
            'slabs' => [
                ['upto' => 300000, 'rate' => 0],
                ['upto' => 700000, 'rate' => 0.05],
                ['upto' => 1000000, 'rate' => 0.10],
                ['upto' => 1200000, 'rate' => 0.15],
                ['upto' => 1500000, 'rate' => 0.20],
                ['upto' => null, 'rate' => 0.30],
            ],
        ],
        'old' => [
            'standard_deduction' => 50000,
            'rebate' => [
                'max_taxable_income' => 500000,
                'max_tax' => 12500,
            ],
            'slabs' => [
                ['upto' => 250000, 'rate' => 0],
                ['upto' => 500000, 'rate' => 0.05],
                ['upto' => 1000000, 'rate' => 0.20],
                ['upto' => null, 'rate' => 0.30],
            ],
        ],
    ],
];
