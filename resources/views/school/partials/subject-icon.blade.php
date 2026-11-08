@php
    $subjectName = mb_strtolower(trim($name));
    $subjectIcon = 'book-half';
    $subjectIcons = [
        'physics' => ['physics', 'physic', 'រូបវិទ្យា'],
        'bank' => ['history', 'histo', 'ប្រវត្តិ'],
        'calculator' => ['mathematics', 'math', 'algebra', 'geometry', 'គណិត'],
        'flask' => ['chemistry', 'គីមី'],
        'flower1' => ['biology', 'ជីវវិទ្យា'],
        'globe' => ['geography', 'ភូមិវិទ្យា'],
        'pc-display' => ['computer', 'computing', 'ict', 'informatics', 'technology', 'កុំព្យូទ័រ'],
        'activity' => ['physical education', 'sport', 'fitness', 'កីឡា'],
        'palette' => ['art', 'drawing', 'សិល្បៈ'],
        'music-note-beamed' => ['music', 'តន្ត្រី'],
        'translate' => ['english', 'khmer', 'chinese', 'french', 'language', 'អង់គ្លេស', 'ភាសា'],
        'beaker' => ['science', 'វិទ្យាសាស្ត្រ'],
    ];
    foreach ($subjectIcons as $icon => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($subjectName, $keyword)) {
                $subjectIcon = $icon;
                break 2;
            }
        }
    }
@endphp
@if($subjectIcon === 'physics')
    <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><ellipse cx="12" cy="12" rx="10" ry="4"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="10" ry="4" transform="rotate(120 12 12)"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
@else
    <i class="bi bi-{{ $subjectIcon }}" aria-hidden="true"></i>
@endif
