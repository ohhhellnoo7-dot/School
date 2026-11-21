@include('school.partials.student-attendance-summary', [
    'summaryClass' => 'windy-only',
    'attendanceSummary' => [
        'date' => $reportDate,
        'total' => $attendanceStudentCount,
        'statuses' => array_combine(['present', 'absent', 'late', 'excused'], $charts['attendance']->all()),
    ],
])
