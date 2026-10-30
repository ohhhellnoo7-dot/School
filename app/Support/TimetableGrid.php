<?php

namespace App\Support;

use App\Services\TeacherManagementService as Time;
use Illuminate\Support\Collection;

class TimetableGrid
{
    public static function build(Collection $lessons, array $periods): array
    {
        $cells = []; $outside = $lessons->reject(fn ($lesson) => in_array($lesson->day, Time::DAYS, true))->values();
        foreach (Time::DAYS as $day) {
            $groups = [];
            foreach ($lessons->where('day', $day)->sortBy('starts_at') as $lesson) {
                $start = Time::minute($lesson->starts_at); $end = Time::minute($lesson->ends_at);
                $first = array_search($start, array_map(fn ($p) => Time::minute($p[0]), $periods), true);
                $last = array_search($end, array_map(fn ($p) => Time::minute($p[1]), $periods), true);
                if ($first === false || $last === false || $last < $first || array_sum(array_map(fn ($p) => Time::duration($p[0], $p[1]), array_slice($periods, $first, $last - $first + 1))) !== $end - $start) { $outside->push($lesson); continue; }
                $key = array_key_last($groups);
                if ($key !== null && $first < $groups[$key]['end']) {
                    $groups[$key]['end'] = max($groups[$key]['end'], $last + 1);
                    $groups[$key]['lessons'][] = $lesson;
                } else $groups[] = ['start' => $first, 'end' => $last + 1, 'lessons' => [$lesson]];
            }
            foreach ($groups as $group) {
                $cells[$day][$group['start']] = ['span' => $group['end'] - $group['start'], 'lessons' => collect($group['lessons'])];
                for ($i = $group['start'] + 1; $i < $group['end']; $i++) $cells[$day][$i] = false;
            }
        }
        return compact('cells', 'outside');
    }
}
