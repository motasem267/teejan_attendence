<?php

return [
    // اسم اتصال قاعدة بيانات البرودكشن (نفس سيرفر MySQL، سكيما مختلفة)
    'resultsys_connection' => 'resultsys',

    'attlog' => [
        'table' => 'attlog',
        'employee_column' => 'employeeID',
        'timestamp_column' => 'checktime',
        'processed_column' => 'is_processed',
    ],

    'windows' => [
        'check_in_before_minutes' => 20,
        'check_in_after_minutes' => 15,
        'check_out_before_minutes' => 5,
        'check_out_after_minutes' => 20,
    ],

    'statuses' => [
        'pending' => 'pending',
        'checked_in' => 'checked_in',
        'completed' => 'completed',
    ],
];
