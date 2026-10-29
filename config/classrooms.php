<?php

$khmerRooms = [];
foreach (range(7, 12) as $grade) {
    foreach (range('A', 'E') as $section) {
        $khmerRooms[] = [
            'room_number' => $grade.$section,
            'name' => 'Room '.$grade.$section,
            'capacity' => 40,
        ];
    }
}

$englishRooms = [];
foreach (range(1, 12) as $grade) {
    $number = 'E'.str_pad((string) $grade, 2, '0', STR_PAD_LEFT);
    $englishRooms[] = [
        'room_number' => $number,
        'name' => 'English Room '.$number,
        'capacity' => 40,
    ];
}

return [
    'timezone' => 'Asia/Bangkok',

    'weekdays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],

    'sessions' => [
        'Morning' => ['starts_at' => '07:00', 'ends_at' => '11:00'],
        'Afternoon' => ['starts_at' => '13:00', 'ends_at' => '17:00'],
        'Evening' => ['starts_at' => '17:30', 'ends_at' => '18:30'],
    ],

    'program_sessions' => [
        'Khmer' => ['Morning', 'Afternoon'],
        'English' => ['Morning', 'Afternoon', 'Evening'],
        'Chinese' => ['Evening'],
    ],

    'rooms' => [...$khmerRooms, ...$englishRooms],
];
