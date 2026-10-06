<?php

return [
    // اسم اتصال قاعدة بيانات البرودكشن (resultsys) — على السيرفر، جهاز منفصل
    // عن هذا التطبيق، متوصل بيه عبر الشبكة المحلية للمدرسة (مش نفس الجهاز)
    'resultsys_connection' => 'resultsys',

    // جدول attlog الحقيقي موجود في SQL Server (نفس الجهاز الفيزيائي، iVMS-4200
    // يكتب فيه مباشرة) — connection منفصل عن المحلي (MySQL) اللي فيه جداولنا
    'attlog' => [
        'connection' => env('ATTLOG_CONNECTION', 'attlog_sqlserver'),
        'table' => 'attlog',
        'employee_column' => 'employeeID',
        'timestamp_column' => 'authDateTime',
        'processed_column' => 'IsSynced',
        'device_column' => 'deviceName',
    ],

    // اسم الجهاز اللي يستعمله الموظفين العاديين (غير المعلمين) — الوحيد اللي
    // لسه نعتمد فيه على اسم الجهاز؛ المعلمون والطلبة يتعرّف عليهم بمعرّفهم
    // (employeeID) فقط، بلا شرط جهاز.
    'reception_device' => 'Reception',

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
