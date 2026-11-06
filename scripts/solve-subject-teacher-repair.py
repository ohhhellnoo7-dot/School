"""Plan one teacher per class subject without changing approved teaching hours."""
import json
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / '.tmp/khmer-solver'))
from ortools.sat.python import cp_model

folder = ROOT / 'storage/app/private/timetable-drafts'
data = json.loads((folder / 'subject-teacher-repair-input.json').read_text())
fixed_owners = {}
previous_rows = set()
if '--refine' in sys.argv:
    previous = json.loads((folder / 'subject-teacher-repair-solved.json').read_text())
    if previous['fingerprint'] != data['fingerprint'] or not previous['rows']:
        raise ValueError('No matching feasible plan to refine')
    fixed_owners = {(r['school_class_id'], r['subject_id']): r['teacher_id'] for r in previous['rows']}
    previous_rows = {(r['school_class_id'], r['subject_id'], r['teacher_id'], r['day'], r['starts_at']) for r in previous['rows']}
if '--joint' not in sys.argv and '--refine' not in sys.argv:
    # Assign complete subjects to staff before placing lesson blocks. This
    # removes interchangeable teacher choices from the larger time-slot model.
    assignment = cp_model.CpModel()
    loads = defaultdict(list)
    ownership = []
    preferences = []
    for c in data['classes']:
        for s in c['subjects']:
            if not s['hours']:
                continue
            choices = []
            for t in data['teachers']:
                if s['name'] not in t['subjects']:
                    continue
                if c['id'] == data['pinned_class_id'] and s['name'] == 'Khmer' and t['id'] != data['pinned_teacher_id']:
                    continue
                owner = assignment.new_bool_var(f"assign_{c['id']}_{s['id']}_{t['id']}")
                choices.append(owner)
                loads[t['id'], c['session']].append(owner * s['hours'])
                ownership.append((owner, c['id'], s['id'], t['id']))
                preferred = sum(1 for r in data['lessons'] if r['subject_id'] == s['id'] and r['teacher_id'] == t['id'])
                preferences.append(owner * preferred)
            assignment.add(sum(choices) == 1)
    for t in data['teachers']:
        for session, offset in [('Morning', 0), ('Afternoon', 4)]:
            capacity = sum(1 for slot in t['available'] if offset <= slot % 8 < offset + 4)
            assignment.add(sum(loads[t['id'], session]) == capacity)
    assignment.maximize(sum(preferences))
    allocator = cp_model.CpSolver()
    allocator.parameters.max_time_in_seconds = 20
    allocator.parameters.num_search_workers = 8
    assignment_status = allocator.solve(assignment)
    print('Subject allocation:', allocator.status_name(assignment_status), flush=True)
    if assignment_status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        fixed_owners = {(cid, sid): tid for x, cid, sid, tid in ownership if allocator.value(x)}
    else:
        sys.exit(1)
model = cp_model.CpModel()
class_slots, room_slots, teacher_slots = (defaultdict(list) for _ in range(3))
teacher_hours = defaultdict(list)
options = []
changes = []
days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
existing = {(r['school_class_id'], r['subject_id'], r['teacher_id'], r['day'], r['starts_at'][:5]) for r in data['lessons']}
for c in data['classes']:
    offset = 0 if c['session'] == 'Morning' else 4
    reservations = {r['day']: r for r in c['reservations']}
    for s in c['subjects']:
        if not s['hours']:
            continue
        owners, hours = [], []
        for t in data['teachers']:
            if s['name'] not in t['subjects']:
                continue
            if fixed_owners and t['id'] != fixed_owners[c['id'], s['id']]:
                continue
            if c['id'] == data['pinned_class_id'] and s['name'] == 'Khmer' and t['id'] != data['pinned_teacher_id']:
                continue
            owner = model.new_bool_var(f"owner_{c['id']}_{s['id']}_{t['id']}")
            owners.append(owner)
            for d, day in enumerate(days):
                r = reservations.get(day)
                if not r:
                    continue
                for h in range(5 - s['duration']):
                    occupied = [d * 8 + offset + h + i for i in range(s['duration'])]
                    if any(slot not in t['available'] for slot in occupied):
                        continue
                    start = (7 if offset == 0 else 13) + h
                    end = start + s['duration']
                    if f'{start:02}:00' < r['starts_at'] or f'{end:02}:00' > r['ends_at']:
                        continue
                    if any(o['classroom_id'] == r['classroom_id'] and o['day'] == day and o['starts_at'][:5] < f'{end:02}:00' and o['ends_at'][:5] > f'{start:02}:00' for o in data['outside']):
                        continue
                    x = model.new_bool_var(f'lesson_{len(options)}')
                    model.add(x <= owner)
                    row = dict(school_class_id=c['id'], subject_id=s['id'], teacher_id=t['id'], classroom_id=r['classroom_id'], room=r['room'], day=day, starts_at=f'{start:02}:00', ends_at=f'{end:02}:00', entry_type='academic', academic_year=data['academic_year'])
                    options.append((x, row))
                    hours.append(x * s['duration'])
                    teacher_hours[t['id']].append(x * s['duration'])
                    for slot in occupied:
                        class_slots[c['id'], slot].append(x)
                        room_slots[r['classroom_id'], slot].append(x)
                        teacher_slots[t['id'], slot].append(x)
                    kept = (c['id'], s['id'], t['id'], day, row['starts_at']) in existing
                    if previous_rows:
                        model.add_hint(x, int((c['id'], s['id'], t['id'], day, row['starts_at']) in previous_rows))
                    if not kept:
                        changes.append(x)
        model.add(sum(owners) == 1)
        model.add(sum(hours) == s['hours'])
    for d in range(5):
        for h in range(4):
            model.add(sum(class_slots[c['id'], d * 8 + offset + h]) == 1)
for xs in room_slots.values():
    model.add(sum(xs) <= 1)
for xs in teacher_slots.values():
    model.add(sum(xs) <= 1)
for t in data['teachers']:
    model.add(sum(teacher_hours[t['id']]) <= t['limit'])
    # When demand uses every available hour, expose that equality directly to
    # the solver instead of requiring it to infer it across all class slots.
    if sum(s['hours'] for c in data['classes'] for s in c['subjects']) == sum(len(teacher['available']) for teacher in data['teachers']):
        for slot in t['available']:
            model.add(sum(teacher_slots[t['id'], slot]) == 1)
solver = cp_model.CpSolver()
if '--refine' in sys.argv:
    model.minimize(sum(changes))
solver.parameters.max_time_in_seconds = 60 if '--refine' in sys.argv else 300
solver.parameters.num_search_workers = 8
print(f"Planning {len(data['classes'])} cohorts with one teacher per subject; Sok Dara fixed for 10E Khmer.", flush=True)
status = solver.solve(model)
result = {'status': solver.status_name(status), 'fingerprint': data['fingerprint'], 'academic_year': data['academic_year'], 'rows': []}
if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
    result['rows'] = [row for x, row in options if solver.value(x)]
    result['changed_lessons'] = sum(solver.value(x) for x in changes)
(folder / 'subject-teacher-repair-solved.json').write_text(json.dumps(result, indent=2))
print(f"{result['status']}: {len(result['rows'])} lessons", flush=True)
sys.exit(0 if result['rows'] else 1)
