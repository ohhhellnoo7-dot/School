<?php

// Dates used by the school calendar for 2026 and 2027.
$common = [
    '1-1' => 'new_year',
    '1-7' => 'victory',
    '3-8' => 'women',
    '4-14' => 'khmer_new_year', '4-15' => 'khmer_new_year', '4-16' => 'khmer_new_year',
    '5-1' => 'labour',
    '5-14' => 'king_birthday',
    '6-18' => 'queen_mother',
    '9-24' => 'constitution',
    '10-15' => 'king_father',
    '10-29' => 'coronation',
    '11-9' => 'independence',
    '12-29' => 'peace',
];

return [
    2026 => array_replace($common, [
        '5-1' => 'labour_and_visak',
        '5-5' => 'ploughing',
        '10-10' => 'pchum_ben', '10-11' => 'pchum_ben', '10-12' => 'pchum_ben',
        '11-23' => 'water_festival', '11-24' => 'water_festival', '11-25' => 'water_festival',
    ]),
    2027 => array_replace($common, [
        '5-20' => 'visak', '5-24' => 'ploughing',
        '9-29' => 'pchum_ben', '9-30' => 'pchum_ben', '10-1' => 'pchum_ben',
        '11-12' => 'water_festival', '11-13' => 'water_festival', '11-14' => 'water_festival',
    ]),
];
