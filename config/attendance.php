<?php

return [
    // اسم اتصال قاعدة بيانات البرودكشن (resultsys) — على السيرفر، جهاز منفصل
    // عن هذا التطبيق، متوصل بيه عبر الشبكة المحلية للمدرسة (مش نفس الجهاز)
    'resultsys_connection' => 'resultsys',

    // جدول attlog محلي (نفس جهاز teejan_attendence، هو المتصل مباشرة بجهاز البصمة)
    'attlog' => [
        'table' => 'attlog',
        'employee_column' => 'employeeID',
        'timestamp_column' => 'checktime',
        'processed_column' => 'is_processed',
        'device_column' => 'deviceName',
    ],

    // اسم الجهاز اللي يستعمله الموظفين العاديين (غير المعلمين)
    'reception_device' => 'Reception',

    // اسم الجهاز المخصص للطلبة وحدهم — يتحط لما يتعرف الجهاز فعليا
    'student_device' => env('ATTENDANCE_STUDENT_DEVICE'),

    'windows' => [
        'check_in_before_minutes' => 20,
        'check_in_after_minutes' => 15,
        'check_out_before_minutes' => 5,
        'check_out_after_minutes' => 20,
    ],

    // نافذة مطابقة حصص المعلمين في نمط "الجلسات الزمنية" (deviceName + مدة)
    'duration_matching' => [
        'min_minutes' => 15,
        'max_minutes' => 80,
        'grace_minutes' => 2,
    ],

    'statuses' => [
        'pending' => 'pending',
        'checked_in' => 'checked_in',
        'completed' => 'completed',
    ],
];
