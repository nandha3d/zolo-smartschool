# Zolo SmartSchool — Timetable V2 Full Implementation Specification

**Target:** `nandha3d/zolo-smartschool`  
**Primary module:** Laravel/PHP timetable management  
**Status:** Implementation specification — execute all phases completely  
**Audience:** Low-level AI coding agent / autonomous coding agent  
**Priority:** Production correctness over speed

---

## 0. EXECUTION CONTRACT

You are implementing a production-grade Timetable V2 system in the existing Zolo SmartSchool repository.

### Absolute rules

1. **Do not implement only part of this specification.**
2. **Do not replace working existing timetable/mobile functionality unnecessarily.**
3. **Do not silently change existing API response contracts used by Flutter apps.**
4. **Do not delete existing timetable data during migration.**
5. **Do not use random scheduling.**
6. **Do not use `shuffle()` or random selection anywhere in the final scheduler.**
7. **Do not store new structured scheduling data inside `timetables.note` JSON when a proper database column/table is specified below.**
8. **Do not treat a generated timetable as valid merely because no obvious teacher conflict exists.**
9. **All hard constraints must be satisfied before a timetable can be committed.**
10. **A timetable with unscheduled required lessons is a failed generation, not a partial success.**
11. **Generated timetables must be deterministic: identical inputs + identical constraints must produce identical output.**
12. **Every generation must be validated after solving and before persistence.**
13. **Generation must happen as a preview first. Persistence occurs only after validation and explicit Apply.**
14. **Existing locked timetable entries must never be moved or deleted by regeneration.**
15. **All school/semester/class/teacher/resource data must be scoped correctly to the authenticated school.**
16. **Never trust IDs supplied by the browser. Validate ownership and relationships server-side.**
17. **Every new database change must be a Laravel migration. Never edit an old migration that may already have run in production.**
18. **Every new feature must have automated tests.**
19. **Run the full relevant test suite after every implementation phase.**
20. **Do not claim completion until all acceptance criteria in this document pass.**

### Important quality statement

Absolute bug-free software cannot be guaranteed by an AI agent. Therefore this specification defines **deterministic behavior, invariants, automated tests, integration tests, failure handling, validation, and regression checks** that must all pass before the implementation is considered complete.

---

# 1. CURRENT SYSTEM — DO NOT BREAK

The existing repository already contains a timetable implementation.

Important existing components include:

```text
PHP_CODE/app/Http/Controllers/TimetableController.php
PHP_CODE/app/Services/TimetableGeneratorService.php
PHP_CODE/app/Models/Timetable.php
PHP_CODE/app/Models/SubjectTeacher.php
PHP_CODE/app/Models/ClassSubject.php
PHP_CODE/app/Models/ClassSection.php
PHP_CODE/routes/web.php

PHP_CODE/app/Http/Controllers/Api/StudentApiController.php
PHP_CODE/app/Http/Controllers/Api/ParentApiController.php
PHP_CODE/app/Http/Controllers/Api/TeacherApiController.php

PHP_CODE/resources/views/timetable/
```

Flutter timetable consumers exist under:

```text
App code/e-school-saas-student-parent/e-school-saas/
App code/eschool-saas-staff/eschool-saas-staff/
```

Existing routes include timetable generation, teacher synchronization, matrix retrieval, quick update, CRUD, and teacher timetable endpoints.

Existing student/parent/teacher APIs must remain functional.

---

# 2. CURRENT PROBLEMS TO FIX

The current generator is a greedy/randomized allocator.

The implementation currently uses queue/shuffle style scheduling and attempts to find the first suitable slot.

This is insufficient for school-wide timetable planning.

Known limitations that must be addressed:

- Randomized generation.
- Class-by-class generation instead of school-wide solving.
- No formal hard/soft constraint model.
- No proper teacher availability model.
- No proper class availability model.
- No resource/room conflict model.
- No robust subject distribution optimization.
- No proper consecutive-period requirements.
- No reliable teacher workload balancing.
- No generation preview/approval workflow.
- No locked-slot preservation model.
- No partial repair/regeneration model.
- Excess weekly-period requirements can be truncated instead of producing a hard failure.
- Alternate teacher data is partly represented through timetable metadata.
- Scheduling requirements are not first-class records.
- Period definitions are too dependent on generated time slots.
- Existing timetable data and planning requirements are not clearly separated.
- Generation lacks complete post-solve validation.
- Generation does not provide detailed explanations for placement decisions.

---

# 3. TARGET ARCHITECTURE

The final architecture must separate four concepts:

```text
1. TIMETABLE CONFIGURATION
2. SCHEDULING REQUIREMENTS
3. SCHEDULING / SOLVING
4. GENERATED TIMETABLE
```

Target flow:

```text
School configuration
        |
        v
Period template
        |
        v
Classes + subjects + teachers + requirements
        |
        v
Availability + locked slots + resources
        |
        v
Hard constraints
        |
        v
Soft constraints
        |
        v
Deterministic scheduler
        |
        v
Candidate timetable
        |
        v
Full validator
        |
        +---- invalid ---> failure report
        |
        v
Quality metrics
        |
        v
Preview
        |
        v
Admin Apply
        |
        v
Final timetable rows
```

---

# 4. DOMAIN MODEL

## 4.1 Existing `timetables`

Keep the existing table for actual scheduled lessons.

Do not turn it into the planning configuration table.

It represents the final schedule:

```text
school
semester
class_section
day
period/time
subject
teacher
type
notes
```

Where the existing schema already contains a field, reuse it.

Where a new field is needed, add a migration.

---

# 5. NEW DATABASE TABLES

Create migrations for the following.

Names may be adjusted to match the repository's naming conventions, but the concepts and fields are mandatory.

---

## 5.1 `timetable_templates`

Purpose: define the school's weekly timetable structure.

Suggested fields:

```text
id
school_id
name
description nullable
working_days JSON
is_default boolean
is_active boolean
created_by nullable
updated_by nullable
created_at
updated_at
```

Rules:

- `school_id` required.
- Only one active default template per school.
- Template belongs to one school.
- Soft deletion may be used if consistent with project conventions.

---

## 5.2 `timetable_periods`

Purpose: define reusable periods.

Fields:

```text
id
school_id
timetable_template_id
period_number
name
start_time
end_time
type
is_instructional
is_active
created_at
updated_at
```

`type` examples:

```text
Lecture
Break
Lunch
Activity
Assembly
Free
```

Rules:

- `period_number` unique inside a template.
- `start_time < end_time`.
- Periods cannot overlap.
- Break/non-instructional periods cannot be used for lessons.
- Times must be valid and normalized.

---

## 5.3 `timetable_breaks`

Only create this if break metadata cannot cleanly be represented by `timetable_periods`.

Fields:

```text
id
school_id
timetable_template_id
name
start_time
end_time
period_after nullable
is_active
```

Do not duplicate information unnecessarily. Prefer representing breaks as non-instructional periods if that works cleanly with the existing UI.

---

## 5.4 `subject_schedule_requirements`

This is mandatory.

Fields:

```text
id
school_id
class_section_id
subject_id
class_subject_id nullable
semester_id nullable
weekly_periods
min_periods_per_day
max_periods_per_day
allow_consecutive boolean
required_consecutive_periods nullable
preferred_day nullable
preferred_period_start nullable
preferred_period_end nullable
avoid_first_period boolean
avoid_last_period boolean
priority integer
is_active boolean
created_at
updated_at
```

Rules:

- `weekly_periods >= 0`.
- `min_periods_per_day >= 0`.
- `max_periods_per_day >= min_periods_per_day`.
- `required_consecutive_periods` only valid when `allow_consecutive = true`.
- Requirement must reference a valid subject assigned to the class.
- Semester must match current/selected semester when applicable.

---

# 6. TEACHER AVAILABILITY

Create:

## `teacher_availability`

Fields:

```text
id
school_id
teacher_id
day
period_id
status
reason nullable
created_at
updated_at
```

Statuses:

```text
available
unavailable
preferred
```

Default behavior:

- If no record exists, teacher is considered available.
- Explicit `unavailable` blocks scheduling.
- `preferred` is a soft preference.

Never interpret missing availability as unavailable.

---

# 7. CLASS AVAILABILITY

Create:

## `class_section_availability`

Fields:

```text
id
school_id
class_section_id
day
period_id
status
reason nullable
created_at
updated_at
```

Statuses:

```text
available
unavailable
preferred
```

Examples:

- Assembly.
- Activity.
- Club.
- Library.
- Lunch.
- Class-specific blocked period.

---

# 8. RESOURCES / ROOMS

Create a generic resource model.

## `timetable_resources`

Fields:

```text
id
school_id
name
type
capacity nullable
description nullable
is_active
created_at
updated_at
```

Examples:

```text
Computer Lab
Physics Lab
Chemistry Lab
Library
Auditorium
Sports Ground
Art Room
```

---

## `timetable_resource_availability`

Fields:

```text
id
school_id
resource_id
day
period_id
status
reason nullable
created_at
updated_at
```

---

## `subject_schedule_requirements` resource fields

Add if needed:

```text
resource_id nullable
resource_required boolean
```

If a lesson requires a resource, the solver must reserve it.

A resource cannot be assigned to two simultaneous classes.

---

# 9. TEACHER-SUBJECT ASSIGNMENT

Existing `subject_teachers` remains the source of truth for valid teacher assignments.

Do not allow the scheduler to assign a teacher to a subject unless:

```text
teacher is assigned to subject/class
```

or the requirement explicitly supports a valid school-level assignment according to existing business rules.

The scheduler must validate:

```text
class_section
class_subject
subject
teacher
school
semester
```

relationships.

---

# 10. PRIMARY AND ALTERNATE TEACHERS

Do not use `timetables.note` as the authoritative source for teacher assignment.

The authoritative assignment must be represented by relational data.

Existing `subject_teachers` should remain the primary relationship.

If alternate/substitute teachers are required for scheduling, create:

## `subject_teacher_alternates`

Fields:

```text
id
school_id
subject_teacher_id
alternate_teacher_id
priority
is_active
created_at
updated_at
```

Rules:

- Alternate teacher must belong to the same school.
- Alternate teacher must be qualified/allowed according to school rules.
- Alternate teacher availability must be checked.
- Alternate teacher cannot create another teacher conflict.

---

# 11. LOCKED TIMETABLE SLOTS

Add a proper field to the timetable record if not already present:

```text
is_locked boolean default false
```

Optional:

```text
locked_by
locked_at
lock_reason
```

Rules:

- A locked timetable entry cannot be moved by automatic generation.
- A locked timetable entry cannot be deleted by regeneration.
- A locked slot is treated as a hard constraint.
- Manual administrators with proper permission may unlock it.

---

# 12. GENERATION RUNS

Create:

## `timetable_generation_runs`

Fields:

```text
id
school_id
session_year_id nullable
semester_id nullable
template_id
started_by
status
algorithm_version
input_hash
started_at
completed_at nullable
error_message nullable
statistics JSON nullable
created_at
updated_at
```

Statuses:

```text
draft
running
completed
failed
applied
cancelled
```

A generation run must be auditable.

---

# 13. GENERATION RESULT / PREVIEW

Do not write unapproved generated rows directly into the final timetable.

Use either:

### Option A

A dedicated staging table:

`timetable_generation_entries`

or

### Option B

A generation-run foreign key on a staging representation.

Preferred design:

## `timetable_generation_entries`

Fields:

```text
id
generation_run_id
class_section_id
subject_id
subject_teacher_id nullable
teacher_id
resource_id nullable
day
period_id
start_time
end_time
type
is_locked
constraint_score
explanation JSON nullable
created_at
updated_at
```

The final timetable is created only when the generation run is applied.

---

# 14. HARD CONSTRAINTS

A valid timetable MUST satisfy all of these.

## H01 — Class conflict

A class cannot have two lessons in the same day + period.

```text
class_section_id + day + period_id
```

must be unique.

---

## H02 — Teacher conflict

A teacher cannot teach two lessons at the same day + period.

```text
teacher_id + day + period_id
```

must be unique.

---

## H03 — Resource conflict

A resource cannot be used by two lessons at the same day + period.

```text
resource_id + day + period_id
```

must be unique.

---

## H04 — Teacher availability

Teacher cannot be scheduled when explicitly unavailable.

---

## H05 — Class availability

Class cannot be scheduled when explicitly unavailable.

---

## H06 — Resource availability

Resource cannot be scheduled when explicitly unavailable.

---

## H07 — Required weekly periods

For every active subject requirement:

```text
scheduled_count == weekly_periods
```

Never silently truncate.

---

## H08 — Valid subject

Subject must belong to the class according to existing class-subject rules.

---

## H09 — Valid teacher

Teacher must be assigned/authorized for the subject and class.

---

## H10 — Valid period

Lesson must use an active instructional period.

---

## H11 — Locked slots

Locked entries must remain unchanged.

---

## H12 — School isolation

No record from another school may be loaded or scheduled.

---

## H13 — Semester isolation

When a semester is selected, requirements and timetable entries must use the correct semester scope.

---

## H14 — Time overlap

Even if period IDs differ due to configuration mistakes, actual start/end times must not overlap for the same teacher/class/resource.

---

## H15 — No duplicate lesson

The same requirement cannot produce duplicate lessons in the same day + period.

---

# 15. SOFT CONSTRAINTS

Soft constraints improve the schedule but never override hard constraints.

Implement weighted scoring.

Suggested default weights:

```text
Subject distribution:             100
Avoid same subject twice/day:      80
Teacher workload balance:          70
Teacher consecutive-period limit:  70
Preferred teacher period:           50
Preferred subject period:            50
Avoid first period:                 30
Avoid last period:                  30
Spread difficult subjects:           40
Avoid unnecessary gaps:              40
```

Weights must be configurable eventually, but keep sensible defaults initially.

---

# 16. SUBJECT DISTRIBUTION

For each requirement:

```text
weekly_periods = N
```

Distribute lessons across available working days.

Example:

```text
Math = 5/week

Preferred:
Monday
Tuesday
Wednesday
Thursday
Friday
```

Avoid:

```text
Monday Math
Monday Math
Tuesday Math
Tuesday Math
Wednesday Math
```

unless the requirement explicitly allows it.

Prefer:

```text
Monday Math
Tuesday Math
Wednesday Math
Thursday Math
Friday Math
```

For 6+ weekly periods, distribute the additional occurrences to days with available capacity.

---

# 17. CONSECUTIVE PERIODS

Support:

```text
allow_consecutive = false
```

and:

```text
allow_consecutive = true
required_consecutive_periods = 2
```

Examples:

### Normal subject

```text
Math
Math
```

same day should be penalized or rejected depending on configuration.

### Lab

```text
Physics Lab
Physics Lab
```

should be supported as a required block.

The solver must treat a required double period as one scheduling block during placement.

---

# 18. TEACHER WORKLOAD

Calculate:

```text
teacher_daily_load
teacher_weekly_load
teacher_consecutive_load
teacher_gaps
```

Optional configuration:

```text
max_periods_per_day
max_periods_per_week
max_consecutive_periods
preferred_free_periods
```

Hard-limit behavior:

If a teacher has a configured hard maximum, exceeding it is invalid.

If it is only a preference, exceeding it is a soft violation.

---

# 19. SCHOOL-WIDE GENERATION

The generator MUST support generation for multiple classes in one run.

Do not treat each class as an independent problem when teacher/resource conflicts cross classes.

Input:

```text
school
session year
semester
template
selected classes
```

Then solve all selected classes together.

---

# 20. PARTIAL REGENERATION

Support:

```text
Full Generate
Repair Conflicts
Regenerate Selected Class
Regenerate Selected Day
Regenerate Selected Teacher
```

Rules:

- Locked entries never move.
- Unaffected valid entries should remain unchanged whenever possible.
- A repair operation must report exactly what changed.
- If repair is impossible, return a failure report instead of creating an invalid timetable.

---

# 21. DETERMINISTIC SOLVER

The final scheduler must NOT use:

```php
shuffle()
rand()
mt_rand()
random_int()
```

for choosing schedule placements.

Identical input must produce identical output.

Use deterministic ordering such as:

```text
1. Most constrained requirement first.
2. Highest priority first.
3. Fewest available slots first.
4. Highest weekly frequency first.
5. Stable subject ID as final tie-breaker.
```

For candidate slots:

```text
1. Hard constraints.
2. Highest soft score.
3. Earliest deterministic period.
4. Stable day order.
5. Stable period order.
```

---

# 22. SOLVER STRATEGY

Implement a constraint-based search engine.

Recommended approach:

```text
Phase 1:
Build scheduling requirements.

Phase 2:
Expand weekly requirements into lesson blocks.

Phase 3:
Sort blocks by constraint difficulty.

Phase 4:
Generate valid candidate slots.

Phase 5:
Place the most constrained block.

Phase 6:
Continue recursively/backtracking.

Phase 7:
If a dead-end occurs:
    undo the most recent placements
    try the next deterministic candidate.

Phase 8:
After all hard constraints are satisfied:
    optimize soft score.

Phase 9:
Validate independently.

Phase 10:
Return preview.
```

Do not trust the solver itself as the validator.

---

# 23. SOLVER SAFETY LIMITS

Prevent runaway generation.

Configuration:

```text
max_search_nodes
max_execution_seconds
```

On timeout:

```text
status = failed
```

Never return a partial timetable as successful.

The error must explain:

```text
Search limit reached.
No proven valid timetable was produced.
```

---

# 24. PRE-SOLVER FEASIBILITY CHECK

Before searching, calculate obvious impossible cases.

Examples:

```text
Required periods > total instructional slots
```

```text
Teacher weekly demand > teacher available capacity
```

```text
Class weekly demand > class available capacity
```

```text
Resource demand > resource availability
```

```text
A required teacher has zero available periods
```

Return a clear error.

Example:

```text
Cannot generate timetable.

Class: 8A
Subject: Mathematics
Required: 7 periods/week
Available instructional slots: 5

Resolution:
Increase working periods or reduce weekly requirement.
```

---

# 25. CONFLICT REPORT FORMAT

Every failed generation must return structured conflicts.

Example:

```json
{
  "code": "TEACHER_CAPACITY_EXCEEDED",
  "severity": "error",
  "teacher_id": 25,
  "teacher_name": "Mr Kumar",
  "required_periods": 32,
  "available_periods": 27,
  "message": "Teacher has 32 required periods but only 27 available periods."
}
```

Use stable machine-readable error codes.

Recommended codes:

```text
CLASS_CAPACITY_EXCEEDED
TEACHER_CAPACITY_EXCEEDED
RESOURCE_CAPACITY_EXCEEDED
NO_VALID_SLOT
TEACHER_UNAVAILABLE
CLASS_UNAVAILABLE
RESOURCE_UNAVAILABLE
LOCKED_SLOT_CONFLICT
INVALID_SUBJECT_ASSIGNMENT
INVALID_TEACHER_ASSIGNMENT
WEEKLY_PERIOD_REQUIREMENT_UNSATISFIED
INVALID_PERIOD_TEMPLATE
```

---

# 26. EXPLANATION ENGINE

Each generated lesson should optionally contain:

```json
{
  "why": [
    "Teacher available",
    "Class available",
    "Subject weekly requirement remaining",
    "Preferred morning period",
    "No teacher conflict",
    "Subject distribution improved"
  ]
}
```

For compromises:

```json
{
  "warnings": [
    "Placed on Friday due to limited teacher availability"
  ]
}
```

Do not expose internal algorithm details to normal users unless useful.

---

# 27. QUALITY METRICS

After generation calculate:

```text
hard_constraint_violations
soft_constraint_violations
total_score
subject_distribution_score
teacher_balance_score
teacher_consecutive_score
preference_score
gap_score
```

A valid timetable must have:

```text
hard_constraint_violations = 0
unscheduled_lessons = 0
```

Do not call a schedule "successful" otherwise.

---

# 28. PREVIEW API

Add a generation endpoint while preserving the old endpoint for compatibility.

Recommended:

```http
POST /timetable/generate
```

Request:

```json
{
  "template_id": 1,
  "semester_id": 2,
  "class_section_ids": [1,2,3],
  "mode": "full",
  "preserve_existing": true
}
```

Response:

```json
{
  "error": false,
  "generation_run_id": 123,
  "status": "completed",
  "statistics": {},
  "conflicts": [],
  "entries": [],
  "quality": {}
}
```

---

# 29. APPLY API

```http
POST /timetable/generation/{id}/apply
```

Rules:

1. Verify user permission.
2. Verify generation belongs to school.
3. Verify generation is completed.
4. Re-run validation.
5. Start DB transaction.
6. Preserve locked entries.
7. Persist generated entries.
8. Verify final database state.
9. Commit.
10. Mark generation as applied.

If any step fails:

```text
rollback
status = failed
```

---

# 30. CANCEL API

```http
POST /timetable/generation/{id}/cancel
```

A cancelled generation must never affect the final timetable.

---

# 31. EXISTING AUTO-GENERATE ENDPOINT

Existing:

```text
POST /timetable/auto-generate
```

must remain functional for backward compatibility.

Refactor it to call the new generation service.

Do not maintain two independent scheduling algorithms.

The old endpoint becomes an adapter:

```text
old request
    ↓
translate request
    ↓
new generation service
    ↓
new result
```

---

# 32. EXISTING QUICK UPDATE

Existing quick update must remain.

After changing a timetable slot manually:

1. Validate class conflict.
2. Validate teacher conflict.
3. Validate resource conflict.
4. Validate subject/teacher assignment.
5. Validate availability.
6. Save inside transaction.
7. Return structured conflict errors.

Never allow manual edits to create an invalid timetable.

---

# 33. MATRIX API

Existing:

```text
GET /timetable/get-matrix/{class_section_id}
```

must continue to work.

Enhance response only by adding fields, not removing existing fields.

Possible additions:

```json
{
  "period_id": 12,
  "is_locked": true,
  "resource_id": null,
  "warnings": [],
  "explanation": {}
}
```

Existing clients must continue to parse the old fields.

---

# 34. TIMETABLE SETTINGS UI

Build a proper timetable configuration screen.

Sections:

```text
A. Working Days
B. Period Structure
C. Breaks
D. Default Template
E. Teacher Availability
F. Class Availability
G. Resources
```

Example:

```text
Monday      [✓]
Tuesday     [✓]
Wednesday   [✓]
Thursday    [✓]
Friday      [✓]
Saturday    [ ]
Sunday      [ ]
```

Period editor:

```text
P1   08:30 - 09:15
P2   09:15 - 10:00
P3   10:00 - 10:45
Break 10:45 - 11:00
P4   11:00 - 11:45
```

---

# 35. SUBJECT REQUIREMENT UI

For every class subject:

```text
Subject: Mathematics

Periods per week:       [ 5 ]
Min per day:            [ 1 ]
Max per day:            [ 2 ]

Allow consecutive:      [ No ]

Preferred:
[ Morning ]

Avoid first period:     [ No ]
Avoid last period:      [ No ]

Priority:
[ High ]
```

Validate all values immediately.

---

# 36. TEACHER AVAILABILITY UI

Grid:

```text
             P1 P2 P3 P4 P5 P6 P7
Monday       ✓  ✓  ✕  ✓  ✓  ✓  ✓
Tuesday      ✓  ✓  ✓  ✓  ✓  ✕  ✓
Wednesday    ✓  ✓  ✓  ✓  ✓  ✓  ✓
...
```

Legend:

```text
✓ Available
✕ Unavailable
★ Preferred
```

---

# 37. CLASS AVAILABILITY UI

Use the same grid model.

Support:

```text
Assembly
Club
Activity
Library
Other blocked periods
```

---

# 38. RESOURCE UI

CRUD:

```text
Name
Type
Capacity
Availability
```

Examples:

```text
Computer Lab
Physics Lab
Library
Auditorium
```

---

# 39. GENERATION UI

New flow:

```text
1. Select Template
2. Select Semester
3. Select Classes
4. Choose Generation Mode
5. Review Constraints
6. Generate
7. Show Progress
8. Show Result
9. Review Conflicts/Warnings
10. Preview Matrix
11. Apply
```

Generation modes:

```text
Full
Repair
Selected Classes
Selected Teacher
```

---

# 40. GENERATION RESULT UI

Show:

```text
Generation Completed

Classes: 24
Lessons scheduled: 720
Unscheduled: 0
Hard conflicts: 0

Soft violations: 6

Quality Score: 94.2%

[ View Timetable ]
[ View Warnings ]
[ Apply Timetable ]
[ Cancel ]
```

Never show a misleading success state when required lessons are missing.

---

# 41. CONFLICT UI

Use severity:

```text
ERROR
WARNING
INFO
```

Example:

```text
ERROR
Teacher capacity exceeded

Mr Kumar
Required: 31
Available: 25

[View affected subjects]
```

---

# 42. LOCKED SLOT UI

Each timetable cell must support:

```text
Lock
Unlock
```

Locked cell visually indicates:

```text
🔒
```

Regeneration must preserve it.

---

# 43. REPAIR UI

When a conflict occurs:

```text
Conflict detected.

Affected lessons: 4

[Repair automatically]
[Review manually]
```

Automatic repair must only modify the minimum necessary entries where possible.

Show a change summary:

```text
Moved:
8A Math
Monday P3 → Tuesday P2

Moved:
8B Science
Tuesday P2 → Tuesday P4

Unchanged:
716 lessons
```

---

# 44. API AUTHORIZATION

Every new endpoint must enforce:

```text
school ownership
permission
feature availability
semester scope
```

Use existing:

```php
ResponseService::noFeatureThenRedirect(...)
ResponseService::noPermissionThenSendJson(...)
```

patterns where appropriate.

Never rely on UI permissions.

---

# 45. TRANSACTIONS

Use database transactions for:

```text
Apply generation
Manual timetable update
Bulk repair
Bulk delete
Lock/unlock operations where multiple records change
```

Rollback on any exception.

---

# 46. VALIDATION SERVICE

Create a central service:

```text
TimetableValidator
```

It must be reusable by:

```text
Generator
Quick Update
Manual Create
Manual Update
Apply Generation
Repair
Import
```

Do not duplicate conflict logic across controllers.

Validator methods should cover:

```text
validateClassConflict()
validateTeacherConflict()
validateResourceConflict()
validateAvailability()
validateWeeklyRequirements()
validateLockedSlots()
validateAssignments()
validateSemesterScope()
validateSchoolScope()
validateCompleteTimetable()
```

---

# 47. SERVICE ARCHITECTURE

Recommended:

```text
app/Services/Timetable/
    TimetablePlanningService.php
    TimetableGenerationService.php
    TimetableValidationService.php
    TimetableConstraintService.php
    TimetableFeasibilityService.php
    TimetableScoringService.php
    TimetableRepairService.php
    TimetableApplyService.php
    TimetableExplanationService.php
    TimetableInputBuilder.php
```

Use interfaces where the repository already follows repository/service abstractions.

---

# 48. CONTROLLER RESPONSIBILITY

Controllers must remain thin.

Controller:

```text
validate request
authorize
call service
format response
```

Controller must NOT contain:

```text
nested scheduling loops
solver logic
constraint calculations
large DB queries for scheduling
```

---

# 49. REPOSITORY / QUERY RULES

Avoid N+1 queries.

Before solving, load required data in batches:

```text
classes
subjects
class subjects
subject teachers
teachers
availability
resources
periods
locked timetable
existing timetable
```

Index the database appropriately.

---

# 50. DATABASE INDEXES

Add indexes for common conflict queries.

At minimum:

```text
timetables:
school_id
class_section_id
teacher/subject_teacher relation
semester_id
day
start_time
end_time

teacher_availability:
school_id
teacher_id
day
period_id

class_section_availability:
school_id
class_section_id
day
period_id

resource_availability:
school_id
resource_id
day
period_id
```

Use composite indexes for high-frequency lookups.

Do not add redundant indexes blindly; inspect existing indexes first.

---

# 51. DATA MIGRATION

Existing timetable data must survive deployment.

Migration strategy:

```text
1. Add new nullable columns/tables.
2. Deploy code that understands old + new data.
3. Backfill safe fields.
4. Validate migrated records.
5. Only then enforce new non-null constraints where appropriate.
```

Never drop existing timetable records as part of this feature.

---

# 52. EXISTING DATA COMPATIBILITY

For old timetable rows:

- Infer period where safely possible.
- Preserve start/end time.
- Preserve day.
- Preserve subject.
- Preserve teacher.
- Preserve semester.
- Preserve school.
- Preserve notes.
- Mark imported/legacy rows if useful.

Do not invent missing teacher/resource information.

---

# 53. FLUTTER COMPATIBILITY

Do not break:

```text
Student timetable
Parent timetable
Teacher timetable
Class timetable
```

Existing model fields such as:

```text
startTime
endTime
day
subject
teacher
note
type
semesterId
classSection
```

must remain available.

New fields may be added.

If API response structure changes, update all affected Flutter models and test both old and new response forms.

---

# 54. STUDENT/PARENT TIMETABLE RULE

Student/parent timetable must show only lessons relevant to that student's class and valid subject assignments.

Existing filtering by:

```text
student subjects
class section
subject teacher
```

must remain correct.

Do not expose another class's timetable.

---

# 55. TEACHER TIMETABLE RULE

Teacher timetable must show only the authenticated teacher's assignments.

Do not trust a teacher ID supplied by the client when the authenticated user ID is available.

Use:

```text
Auth::user()->id
```

for teacher self-view APIs.

---

# 56. AUDIT LOGGING

Record important generation operations:

```text
Generation started
Generation completed
Generation failed
Generation applied
Generation cancelled
Timetable repaired
Slot locked
Slot unlocked
Manual timetable conflict rejected
```

Do not log passwords, tokens, secrets, or sensitive personal information.

---

# 57. TESTING — MANDATORY

Do not consider implementation complete without tests.

---

## 57.1 Unit tests

Test:

```text
period overlap
time comparison
day ordering
subject distribution
weekly period counting
teacher capacity
class capacity
resource capacity
availability
consecutive periods
locked slots
scoring
deterministic ordering
```

---

## 57.2 Feature tests

Test:

```text
create requirement
update requirement
delete requirement
teacher availability
class availability
resource CRUD
generation
generation preview
generation apply
generation cancel
repair
quick update
lock/unlock
matrix API
```

---

## 57.3 Integration tests

Create realistic school fixtures:

```text
3 classes
8 subjects
5 teachers
2 resources
5 working days
6 periods/day
breaks
teacher availability
subject weekly requirements
locked lesson
```

Generate the complete timetable.

Verify:

```text
zero hard conflicts
all required lessons scheduled
teacher capacity valid
resource capacity valid
locked lesson unchanged
same input produces same output
```

---

# 58. DETERMINISM TEST

Run generation twice with identical database/input state.

Compare:

```text
class
subject
teacher
day
period
resource
```

The generated result must be identical.

This test is mandatory.

---

# 59. IMPOSSIBLE CASE TEST

Create:

```text
Teacher available: 2 periods
Required teaching: 5 periods
```

Expected:

```text
generation status = failed
error code = TEACHER_CAPACITY_EXCEEDED
no final timetable changes
```

---

# 60. LOCKED SLOT TEST

Create:

```text
8A Math
Monday P1
locked = true
```

Run full regeneration.

Expected:

```text
Monday P1 remains exactly unchanged.
```

---

# 61. TEACHER CLASH TEST

Create:

```text
8A Math -> Teacher A -> Monday P2
8B Science -> Teacher A -> Monday P2
```

Expected:

```text
validator rejects
```

---

# 62. CLASS CLASH TEST

Create two lessons for the same class at the same period.

Expected:

```text
validator rejects.
```

---

# 63. RESOURCE CLASH TEST

Create:

```text
8A Computer Lab -> Monday P3
8B Computer Lab -> Monday P3
```

Expected:

```text
validator rejects.
```

---

# 64. AVAILABILITY TEST

Teacher:

```text
Monday P3 = unavailable
```

Expected:

```text
No generated lesson uses Teacher + Monday P3.
```

---

# 65. WEEKLY REQUIREMENT TEST

If:

```text
Math = 5/week
```

then final schedule must contain exactly:

```text
5
```

Math lessons for that class/semester.

Not:

```text
4
```

and not:

```text
6
```

---

# 66. OVER-CAPACITY TEST

If:

```text
available slots = 30
required slots = 34
```

generation must fail before writing final timetable.

---

# 67. MANUAL UPDATE TEST

Attempt to move a teacher into a conflicting slot.

Expected:

```text
HTTP validation/conflict response
database unchanged
```

---

# 68. SECURITY TESTS

Verify:

```text
School A cannot read School B timetable.
School A cannot update School B timetable.
Teacher A cannot impersonate Teacher B.
Parent cannot request another parent's child.
Student cannot access another class.
Generation run cannot be applied by another school.
```

---

# 69. PERFORMANCE

Do not perform:

```text
query inside every candidate slot
query inside recursive solver loop
N+1 teacher/resource lookup
```

Use preloaded in-memory structures.

Build maps such as:

```text
teacherAvailability[teacherId][day][periodId]
classAvailability[classId][day][periodId]
resourceAvailability[resourceId][day][periodId]
teacherOccupancy[teacherId][day][periodId]
classOccupancy[classId][day][periodId]
resourceOccupancy[resourceId][day][periodId]
```

This is mandatory for performance.

---

# 70. CACHE

Do not cache mutable scheduling state during an active generation unless cache invalidation is explicit.

Safe candidates for caching:

```text
period templates
static school configuration
subject metadata
```

Never allow stale availability data to create invalid schedules.

---

# 71. ERROR HANDLING

Never expose raw exception messages to end users.

Log internal exception details.

Return safe messages:

```text
Unable to generate timetable.
Please review the listed constraints.
```

For administrators, provide structured actionable conflict information.

---

# 72. NO SILENT FALLBACKS

Never do:

```php
if impossible:
    skip lesson
```

Never do:

```php
if teacher unavailable:
    assign random teacher
```

Never do:

```php
if no slot:
    continue
```

Every unsatisfied requirement must produce a failure/conflict.

---

# 73. NO RANDOMNESS

Remove random scheduling from the final algorithm.

The final repository must contain no scheduling dependency on:

```text
shuffle
rand
mt_rand
random_int
random ordering
```

unless explicitly used only in tests and never in production scheduling.

---

# 74. LOGICAL ORDER OF IMPLEMENTATION

Implement in this exact order.

## Phase 1 — Audit

- Inspect current timetable schema.
- Inspect all timetable migrations.
- Inspect current controller/service/model/repository.
- Inspect routes.
- Inspect Blade timetable UI.
- Inspect student/parent/teacher APIs.
- Inspect Flutter models and screens.
- Identify all current timetable consumers.
- Document current fields and compatibility requirements.

Do not modify code during the audit phase.

---

## Phase 2 — Database foundation

Implement:

```text
timetable_templates
timetable_periods
subject_schedule_requirements
teacher_availability
class_section_availability
timetable_resources
timetable_resource_availability
subject_teacher_alternates
timetable_generation_runs
timetable_generation_entries
```

Add indexes and foreign keys.

Run migrations on a clean test database.

Run migrations against a copy/fixture representing legacy data.

---

## Phase 3 — Domain models

Create/update Eloquent models and relationships.

Every relationship must have tests.

---

## Phase 4 — Configuration UI

Implement:

```text
Template
Periods
Breaks
Teacher availability
Class availability
Resources
```

---

## Phase 5 — Requirements UI

Implement subject weekly requirements.

---

## Phase 6 — Central validator

Implement `TimetableValidationService`.

Use it from:

```text
manual create
manual update
quick update
generator
apply
repair
```

---

## Phase 7 — Feasibility engine

Implement pre-solver impossible-case detection.

---

## Phase 8 — Deterministic scheduler

Replace greedy/random generation with deterministic constraint search.

---

## Phase 9 — Scoring

Implement soft constraints.

---

## Phase 10 — Preview generation

Implement generation run + staging entries.

---

## Phase 11 — Apply

Implement safe transactional apply.

---

## Phase 12 — Repair

Implement partial repair.

---

## Phase 13 — Matrix UI

Upgrade existing matrix without breaking current behavior.

---

## Phase 14 — Mobile compatibility

Verify student/parent/teacher Flutter APIs.

---

## Phase 15 — Tests

Run all unit, feature, integration, regression, security, and determinism tests.

---

## Phase 16 — Final audit

Search repository for:

```text
shuffle(
rand(
mt_rand(
random scheduling
silent skip
TODO timetable
```

Remove all production scheduling randomness and unresolved timetable TODOs.

---

# 75. ACCEPTANCE CRITERIA

The feature is COMPLETE only when all of the following are true.

### Database

- [ ] All required migrations exist.
- [ ] Foreign keys are correct.
- [ ] Composite indexes exist.
- [ ] Existing data survives migration.
- [ ] No destructive migration is used for existing timetable data.

### Scheduling

- [ ] No random scheduling.
- [ ] Deterministic output.
- [ ] School-wide generation.
- [ ] Class conflicts impossible.
- [ ] Teacher conflicts impossible.
- [ ] Resource conflicts impossible.
- [ ] Availability respected.
- [ ] Locked slots preserved.
- [ ] Weekly requirements exactly satisfied.
- [ ] Consecutive-period rules supported.
- [ ] Subject distribution supported.
- [ ] Teacher workload balancing supported.
- [ ] Soft constraints scored.
- [ ] Impossible cases reported clearly.

### Workflow

- [ ] Generate preview.
- [ ] Validate.
- [ ] Review.
- [ ] Apply.
- [ ] Cancel.
- [ ] Repair.
- [ ] Lock/unlock.
- [ ] Manual edits validated.

### API

- [ ] Existing endpoints remain compatible.
- [ ] New endpoints are authorized.
- [ ] School isolation enforced.
- [ ] Semester scope enforced.
- [ ] Structured errors returned.

### UI

- [ ] Configuration screen.
- [ ] Subject requirements.
- [ ] Teacher availability.
- [ ] Class availability.
- [ ] Resource management.
- [ ] Generation preview.
- [ ] Conflict report.
- [ ] Quality metrics.
- [ ] Lock/unlock.
- [ ] Repair.
- [ ] Existing matrix preserved.

### Mobile

- [ ] Student timetable works.
- [ ] Parent timetable works.
- [ ] Teacher timetable works.
- [ ] Class timetable works.
- [ ] Existing JSON fields remain compatible.

### Tests

- [ ] Unit tests pass.
- [ ] Feature tests pass.
- [ ] Integration tests pass.
- [ ] Security tests pass.
- [ ] Determinism test passes.
- [ ] Impossible-case tests pass.
- [ ] Locked-slot tests pass.
- [ ] Regression tests pass.

---

# 76. FINAL VERIFICATION COMMANDS

Before declaring completion, run the project's appropriate commands, adapting only to the repository's actual setup:

```bash
php artisan migrate:fresh --seed
php artisan test
```

If the project uses a different test setup, use the actual repository commands.

Also run static/lint checks available in the repository.

For Flutter applications, run the repository's actual:

```bash
flutter analyze
flutter test
```

Do not invent commands that the repository does not support.

---

# 77. FINAL MANUAL TEST

Create a realistic test school:

```text
Classes:
8A
8B
9A

Teachers:
10

Subjects:
8 per class

Periods:
7/day

Days:
Monday-Friday

Resources:
Computer Lab
Physics Lab

Teacher availability:
Several blocked periods

Subject requirements:
Math 5/week
Science 5/week
English 5/week
Tamil 5/week
Social Science 4/week
Computer 2/week
PE 2/week
Art 1/week
```

Lock at least 3 lessons.

Generate the complete timetable.

Verify:

```text
No class conflict.
No teacher conflict.
No resource conflict.
No availability violation.
No missing required lesson.
Locked lessons unchanged.
Output is deterministic.
Preview matches applied timetable.
Mobile APIs still work.
```

Then modify one teacher's availability and run repair.

Verify only necessary lessons move.

---

# 78. IMPORTANT IMPLEMENTATION PRINCIPLE

Do not optimize the algorithm before correctness.

Correct order:

```text
Correctness
    ↓
Validation
    ↓
Determinism
    ↓
Feasibility
    ↓
Performance
    ↓
Soft optimization
    ↓
UX
```

A schedule that looks balanced but contains one teacher conflict is invalid.

A schedule that is ugly but satisfies every hard constraint is a valid candidate that can then be improved by the soft optimizer.

---

# 79. FINAL DELIVERABLES

The implementation must produce:

```text
Database migrations
Eloquent models
Repositories where appropriate
Services
Constraint engine
Validator
Feasibility checker
Scoring engine
Generation run system
Preview system
Apply system
Repair system
REST/API endpoints
Blade UI
JavaScript for timetable interactions
Automated tests
Updated Flutter models only where necessary
Updated Flutter timetable screens only where necessary
Documentation
```

Also produce:

```text
TIMETABLE_V2_IMPLEMENTATION.md
```

containing:

- architecture
- database schema
- API documentation
- constraint definitions
- generation workflow
- repair workflow
- testing instructions
- deployment/migration notes

---

# 80. AGENT BEHAVIOR DURING IMPLEMENTATION

When working on this task:

### Before modifying a file

Read the complete relevant file.

### Before creating a new table

Search existing migrations/models for an equivalent concept.

### Before creating an endpoint

Search routes and controllers for an existing endpoint with similar behavior.

### Before changing an API response

Search all Flutter/API consumers.

### Before changing timetable schema

Search all references to the affected column.

### Before deleting code

Determine whether another module depends on it.

### After each phase

Run tests.

### If a test fails

Fix the root cause.

Do not weaken the test merely to make it pass.

### If existing behavior conflicts with the new architecture

Preserve backward compatibility through an adapter where practical.

### Never

- fabricate database columns
- fabricate routes
- assume relationships
- ignore migration order
- ignore school ownership
- skip validation
- silently drop lessons
- silently overwrite locked lessons
- use randomness
- declare success with failing tests

---

# 81. DEFINITION OF DONE

This task is DONE only when:

```text
[✓] Existing timetable functionality preserved
[✓] New planning model implemented
[✓] School-wide deterministic scheduler implemented
[✓] Hard constraints implemented
[✓] Soft constraints implemented
[✓] Feasibility checks implemented
[✓] Validation implemented
[✓] Preview implemented
[✓] Apply implemented
[✓] Repair implemented
[✓] Locked slots implemented
[✓] Configuration UI implemented
[✓] Requirement UI implemented
[✓] Availability UI implemented
[✓] Resource management implemented
[✓] Conflict explanations implemented
[✓] Quality metrics implemented
[✓] API compatibility verified
[✓] Flutter compatibility verified
[✓] Tests implemented
[✓] Tests passing
[✓] Security isolation verified
[✓] Determinism verified
[✓] Legacy data verified
[✓] No production scheduling randomness
[✓] No unresolved timetable TODOs
```

Do not report "implemented" if any mandatory checkbox is incomplete.

---

# 82. IMPLEMENTATION PRIORITY

If the coding agent must choose between speed and correctness:

```text
CORRECTNESS > DATA SAFETY > BACKWARD COMPATIBILITY > TESTABILITY > PERFORMANCE > UI POLISH
```

If the agent encounters an architectural ambiguity, inspect the existing repository first and choose the solution that preserves the existing application's conventions and data.

Do not invent a completely separate architecture when an existing project abstraction can be extended safely.

---

# 83. FINAL AGENT RESPONSE

After implementation, report:

```text
TIMETABLE V2 IMPLEMENTATION COMPLETE

Database:
...

Services:
...

APIs:
...

UI:
...

Mobile:
...

Tests:
...

Migration:
...

Determinism:
PASS/FAIL

Hard constraint validation:
PASS/FAIL

School isolation:
PASS/FAIL

Legacy compatibility:
PASS/FAIL

Known remaining issues:
...
```

If any mandatory requirement is not complete, report it as incomplete instead of claiming completion.
