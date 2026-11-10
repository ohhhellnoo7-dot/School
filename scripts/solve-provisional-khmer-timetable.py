"""Exact database curriculum, one teacher per class subject, complete sessions; draft only."""
import json
import sys
from pathlib import Path
from collections import defaultdict
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'.tmp/khmer-solver'))
from ortools.sat.python import cp_model
folder=ROOT/'storage/app/private/timetable-drafts'
input_path=Path(sys.argv[1]) if len(sys.argv)>1 else folder/'khmer-provisional-input.json'
output_path=Path(sys.argv[2]) if len(sys.argv)>2 else folder/'khmer-provisional-solved.json'
data=json.loads(input_path.read_text())
model=cp_model.CpModel()
class_slots=defaultdict(list); teacher_slots=defaultdict(list); room_slots=defaultdict(list)
teacher_load=defaultdict(list); teacher_names=defaultdict(dict)
variables=[]; preferences=[]
days=['Monday','Tuesday','Wednesday','Thursday','Friday']
for c in data['classes']:
    if sum(s['hours'] for s in c['subjects'])!=20: raise ValueError(f"Incomplete curriculum {c['id']}")
    offset=0 if c['session']=='Morning' else 4
    reservations={r['day']:r for r in c['reservations']}
    for s in c['subjects']:
        owners=[]; occurrences=[]
        for t in data['teachers']:
            if s['name'] not in t['subjects']: continue
            owner=model.new_bool_var(f"owner_{c['id']}_{s['id']}_{t['id']}"); owners.append(owner)
            if s.get('assigned_teacher_id')==t['id']: preferences.append(owner)
            enabled=teacher_names[t['id']].get(s['name'])
            if enabled is None:
                enabled=model.new_bool_var(f"qualified_{t['id']}_{len(teacher_names[t['id']])}")
                teacher_names[t['id']][s['name']]=enabled
            model.add(owner<=enabled)
            options=[]
            for d,day in enumerate(days):
                r=reservations.get(day)
                if not r: continue
                for start in range(5-int(s['duration'])):
                    slots=[d*8+offset+start+i for i in range(int(s['duration']))]
                    if any(slot not in t['available'] for slot in slots): continue
                    hour=(7 if offset==0 else 13)+start
                    begin=f'{hour:02}:00'; end=f'{hour+int(s["duration"]):02}:00'
                    if begin<r['starts_at'] or end>r['ends_at']: continue
                    if any(o['day']==day and o['classroom_id']==r['classroom_id'] and o['starts_at'][:5]<end and o['ends_at'][:5]>begin for o in data['outside']): continue
                    x=model.new_bool_var(f'lesson_{len(variables)}'); model.add(x<=owner)
                    options.append(x); occurrences.append(x*int(s['duration']))
                    teacher_load[t['id']].append(x*int(s['duration']))
                    for slot in slots:
                        class_slots[c['id'],slot].append(x); teacher_slots[t['id'],slot].append(x); room_slots[r['classroom_id'],slot].append(x)
                    variables.append((x,{'school_class_id':c['id'],'subject_id':s['id'],'teacher_id':t['id'],'classroom_id':r['classroom_id'],'room':r['room'],'day':day,'starts_at':begin,'ends_at':end,'entry_type':'academic','academic_year':data.get('academic_year','2026-2027')}))
            model.add(sum(options)==int(s['hours']/s['duration'])*owner)
        model.add(sum(owners)==1)
        model.add(sum(occurrences)==int(s['hours']))
    for d in range(5):
        for h in range(4): model.add(sum(class_slots[c['id'],d*8+offset+h])==1)
for t in data['teachers']:
    model.add(sum(teacher_load[t['id']])<=int(t['limit']))
    if teacher_load[t['id']]: model.add(sum(teacher_load[t['id']])>=1)
    model.add(sum(teacher_names[t['id']].values())<=t.get('subject_limit',2))
for options in teacher_slots.values(): model.add(sum(options)<=1)
for options in room_slots.values(): model.add(sum(options)<=1)
# Prefer established subject-teacher links without making an infeasible old assignment mandatory.
model.maximize(sum(preferences))
solver=cp_model.CpSolver(); solver.parameters.max_time_in_seconds=120
solver.parameters.stop_after_first_solution=True; solver.parameters.num_search_workers=8
print(f"Solving {len(data['classes'])} cohorts, {len(data['teachers'])} staff records, {len(variables)} valid lesson options.",flush=True)
status=solver.solve(model)
result={'academic_year':data.get('academic_year','2026-2027'),'status':solver.status_name(status),'fingerprint':data['fingerprint'],'backup':data['backup'],'curriculum_status':'provisional','planned_staff_ids':[t['id'] for t in data['teachers'] if t.get('planned')],'rows':[]}
if status in (cp_model.OPTIMAL,cp_model.FEASIBLE): result['rows']=[row for x,row in variables if solver.value(x)]
output_path.write_text(json.dumps(result,indent=2))
print(f"{result['status']}: {len(result['rows'])} lessons; source remains provisional.",flush=True)
sys.exit(0 if result['rows'] else 1)
