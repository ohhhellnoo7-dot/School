"""Solve all existing Khmer cohorts using real qualified staff; never writes live data."""
import json
import sys
from pathlib import Path
from collections import defaultdict

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / '.tmp/khmer-solver'))
from ortools.sat.python import cp_model

folder = ROOT / 'storage/app/private/timetable-drafts'
data = json.loads((folder / 'khmer-full-study-input.json').read_text())
model = cp_model.CpModel()
by_class_slot = defaultdict(list)
by_teacher_slot = defaultdict(list)
by_subject = defaultdict(list)
by_subject_day = defaultdict(list)
by_teacher = defaultdict(list)
by_room_slot = defaultdict(list)
variables = []
days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
teachers = {t['id']: t for t in data['teachers']}
template = next(c for c in data['classes'] if c['grade'] == 'Grade 10' and c['section'] == 'A' and c['session'] == 'Morning')
template_hours = defaultdict(int)
for s in template['subjects']:
    template_hours[s['name']] += s['hours'] or 0
curricula = {}
penalties = []
grade_catalogs = defaultdict(dict)
for cohort in data['classes']:
    for subject in cohort['subjects']:
        grade_catalogs[cohort['grade']][subject['name']] = subject
preferred = {'Khmer':4, 'Mathematics':4, 'English':2, 'Physics':1, 'Chemistry':1, 'Biology':2, 'History':2, 'Geography':2, 'Computer Science':1, 'Physical Education':1}
core = set(preferred) - {'Computer Science', 'Physical Education'}

for c in data['classes']:
    subjects = {}
    for s in c['subjects']:
        if s['hours'] is None:
            raise ValueError(f"Cohort {c['id']} has unknown approved hours")
        if s['name'] not in subjects:
            subjects[s['name']] = dict(s, hours=0)
        subjects[s['name']]['hours'] += s['hours']
    for name, source in grade_catalogs[c['grade']].items():
        if name not in subjects:
            subjects[name] = dict(source, id=-source['id'], hours=0)
    if c['grade'] == 'Grade 10' and c['section'] == 'B' and c['session'] == 'Morning':
        for name, s in subjects.items():
            s['hours'] = template_hours.get(name, 0)
    total = sum(s['hours'] for s in subjects.values())
    if total > 20:
        raise ValueError(f"Cohort {c['id']} exceeds 20 hours")
    curricula[c['id']] = subjects
    reservations = {r['day']: r for r in c['reservations']}
    offset = 0 if c['session'] == 'Morning' else 4
    for name, s in subjects.items():
        subject_vars = []
        for t in data['teachers']:
            if name not in t['subjects']:
                continue
            owner = model.new_bool_var(f"owner_{c['id']}_{s['id']}_{t['id']}")
            subject_vars.append(owner)
            for d, day in enumerate(days):
                r = reservations.get(day)
                if not r:
                    continue
                for start in range(5 - s['duration']):
                    occupied = [d * 8 + offset + start + i for i in range(s['duration'])]
                    if any(slot not in t['available'] for slot in occupied):
                        continue
                    hour = (7 if offset == 0 else 13) + start
                    if f'{hour:02}:00' < r['starts_at'] or f'{hour+s["duration"]:02}:00' > r['ends_at']:
                        continue
                    if any(o['day'] == day and o['classroom_id'] == r['classroom_id'] and o['starts_at'][:5] < f'{hour+s["duration"]:02}:00' and o['ends_at'][:5] > f'{hour:02}:00' for o in data['outside']):
                        continue
                    x = model.new_bool_var(f"lesson_{len(variables)}")
                    model.add(x <= owner)
                    row = {'school_class_id': c['id'], 'subject_id': s['id'], 'teacher_id': t['id'], 'classroom_id': r['classroom_id'], 'room': r['room'], 'day': day, 'starts_at': f'{hour:02}:00', 'ends_at': f'{hour+s["duration"]:02}:00', 'entry_type': 'academic', 'academic_year': '2026-2027', 'subject': name, 'teacher': t['name']}
                    variables.append((x, row))
                    by_subject[c['id'], name].append(x * s['duration'])
                    by_subject_day[c['id'], name, day].append(x)
                    by_teacher[t['id']].append(x * s['duration'])
                    for slot in occupied:
                        by_class_slot[c['id'], slot].append(x)
                        by_teacher_slot[t['id'], slot].append(x)
                        by_room_slot[r['classroom_id'], slot].append(x)
        # All lessons of a class subject share one teacher, even across weekdays.
        model.add(sum(subject_vars) <= 1)
        hours = sum(by_subject[c['id'], name])
        if c['grade'] == 'Grade 10' and c['section'] in ('A', 'B') and c['session'] == 'Morning':
            model.add(hours == template_hours.get(name, 0))
        else:
            minimum = 2 if s['duration'] == 2 else (1 if name in core else 0)
            maximum = 6 if s['duration'] == 2 else (4 if name == 'English' else 3)
            model.add(hours >= minimum)
            model.add(hours <= maximum)
            deviation = model.new_int_var(0, 20, f"deviation_{c['id']}_{s['id']}")
            model.add_abs_equality(deviation, hours - preferred.get(name, 0))
            penalties.append(deviation)
    for d in range(5):
        for h in range(4):
            model.add(sum(by_class_slot[c['id'], d * 8 + offset + h]) == 1)
for xs in by_subject_day.values():
    model.add(sum(xs) <= 1)
for t in data['teachers']:
    model.add(sum(by_teacher[t['id']]) <= int(t['limit']))
    for slot in t['available']:
        model.add(sum(by_teacher_slot[t['id'], slot]) == 1)
for xs in by_room_slot.values():
    model.add(sum(xs) <= 1)
model.minimize(sum(penalties))
solver = cp_model.CpSolver()
solver.parameters.max_time_in_seconds = 120
solver.parameters.stop_after_first_solution = True
solver.parameters.num_search_workers = 8
print(f'Solving {len(data["classes"])} cohorts, {len(teachers)} real teachers, {len(variables)} lesson options.', flush=True)
status = solver.solve(model)
result = {'status': solver.status_name(status), 'fingerprint': data['fingerprint'], 'backup': data['backup'], 'rows': []}
if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
    result['rows'] = [row for x, row in variables if solver.value(x)]
    result['curriculum'] = {str(cid): {name: {'subject_id': s['id'], 'hours': sum((int(row['ends_at'][:2])-int(row['starts_at'][:2])) for row in result['rows'] if row['school_class_id'] == cid and row['subject'] == name)} for name, s in subjects.items()} for cid, subjects in curricula.items()}
(folder / 'khmer-full-study-solved.json').write_text(json.dumps(result, indent=2))
print(f"{result['status']}: {len(result['rows'])} lessons", flush=True)
sys.exit(0 if result['rows'] else 1)
