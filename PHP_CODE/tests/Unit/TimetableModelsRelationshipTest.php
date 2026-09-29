<?php

namespace Tests\Unit;

use App\Models\ClassSection;
use App\Models\ClassSectionAvailability;
use App\Models\ClassSubject;
use App\Models\Semester;
use App\Models\SessionYear;
use App\Models\Subject;
use App\Models\SubjectScheduleRequirement;
use App\Models\SubjectTeacher;
use App\Models\SubjectTeacherAlternate;
use App\Models\TeacherAvailability;
use App\Models\Timetable;
use App\Models\TimetableGenerationEntry;
use App\Models\TimetableGenerationRun;
use App\Models\TimetablePeriod;
use App\Models\TimetableResource;
use App\Models\TimetableResourceAvailability;
use App\Models\TimetableTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class TimetableModelsRelationshipTest extends TestCase
{
    public function test_timetable_template_relationships(): void
    {
        $template = new TimetableTemplate();

        $this->assertInstanceOf(HasMany::class, $template->periods());
        $this->assertInstanceOf(TimetablePeriod::class, $template->periods()->getRelated());

        $this->assertInstanceOf(HasMany::class, $template->instructional_periods());
        $this->assertInstanceOf(TimetablePeriod::class, $template->instructional_periods()->getRelated());

        $this->assertInstanceOf(HasMany::class, $template->generation_runs());
        $this->assertInstanceOf(TimetableGenerationRun::class, $template->generation_runs()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $template->creator());
        $this->assertInstanceOf(User::class, $template->creator()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $template->updater());
        $this->assertInstanceOf(User::class, $template->updater()->getRelated());
    }

    public function test_timetable_period_relationships(): void
    {
        $period = new TimetablePeriod();

        $this->assertInstanceOf(BelongsTo::class, $period->template());
        $this->assertInstanceOf(TimetableTemplate::class, $period->template()->getRelated());

        $this->assertInstanceOf(HasMany::class, $period->timetables());
        $this->assertInstanceOf(Timetable::class, $period->timetables()->getRelated());

        $this->assertInstanceOf(HasMany::class, $period->teacher_availabilities());
        $this->assertInstanceOf(TeacherAvailability::class, $period->teacher_availabilities()->getRelated());

        $this->assertInstanceOf(HasMany::class, $period->class_availabilities());
        $this->assertInstanceOf(ClassSectionAvailability::class, $period->class_availabilities()->getRelated());

        $this->assertInstanceOf(HasMany::class, $period->resource_availabilities());
        $this->assertInstanceOf(TimetableResourceAvailability::class, $period->resource_availabilities()->getRelated());
    }

    public function test_timetable_resource_relationships(): void
    {
        $resource = new TimetableResource();

        $this->assertInstanceOf(HasMany::class, $resource->availabilities());
        $this->assertInstanceOf(TimetableResourceAvailability::class, $resource->availabilities()->getRelated());

        $this->assertInstanceOf(HasMany::class, $resource->requirements());
        $this->assertInstanceOf(SubjectScheduleRequirement::class, $resource->requirements()->getRelated());

        $this->assertInstanceOf(HasMany::class, $resource->timetables());
        $this->assertInstanceOf(Timetable::class, $resource->timetables()->getRelated());
    }

    public function test_subject_schedule_requirement_relationships(): void
    {
        $req = new SubjectScheduleRequirement();

        $this->assertInstanceOf(BelongsTo::class, $req->class_section());
        $this->assertInstanceOf(ClassSection::class, $req->class_section()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $req->subject());
        $this->assertInstanceOf(Subject::class, $req->subject()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $req->class_subject());
        $this->assertInstanceOf(ClassSubject::class, $req->class_subject()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $req->semester());
        $this->assertInstanceOf(Semester::class, $req->semester()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $req->resource());
        $this->assertInstanceOf(TimetableResource::class, $req->resource()->getRelated());

        $this->assertInstanceOf(HasMany::class, $req->subject_teachers());
        $this->assertInstanceOf(SubjectTeacher::class, $req->subject_teachers()->getRelated());
    }

    public function test_teacher_availability_relationships(): void
    {
        $avail = new TeacherAvailability();

        $this->assertInstanceOf(BelongsTo::class, $avail->teacher());
        $this->assertInstanceOf(User::class, $avail->teacher()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $avail->period());
        $this->assertInstanceOf(TimetablePeriod::class, $avail->period()->getRelated());
    }

    public function test_class_section_availability_relationships(): void
    {
        $avail = new ClassSectionAvailability();

        $this->assertInstanceOf(BelongsTo::class, $avail->class_section());
        $this->assertInstanceOf(ClassSection::class, $avail->class_section()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $avail->period());
        $this->assertInstanceOf(TimetablePeriod::class, $avail->period()->getRelated());
    }

    public function test_timetable_resource_availability_relationships(): void
    {
        $avail = new TimetableResourceAvailability();

        $this->assertInstanceOf(BelongsTo::class, $avail->resource());
        $this->assertInstanceOf(TimetableResource::class, $avail->resource()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $avail->period());
        $this->assertInstanceOf(TimetablePeriod::class, $avail->period()->getRelated());
    }

    public function test_subject_teacher_alternate_relationships(): void
    {
        $alt = new SubjectTeacherAlternate();

        $this->assertInstanceOf(BelongsTo::class, $alt->subject_teacher());
        $this->assertInstanceOf(SubjectTeacher::class, $alt->subject_teacher()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $alt->alternate_teacher());
        $this->assertInstanceOf(User::class, $alt->alternate_teacher()->getRelated());
    }

    public function test_timetable_generation_run_relationships(): void
    {
        $run = new TimetableGenerationRun();

        $this->assertInstanceOf(BelongsTo::class, $run->template());
        $this->assertInstanceOf(TimetableTemplate::class, $run->template()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $run->session_year());
        $this->assertInstanceOf(SessionYear::class, $run->session_year()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $run->semester());
        $this->assertInstanceOf(Semester::class, $run->semester()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $run->user());
        $this->assertInstanceOf(User::class, $run->user()->getRelated());

        $this->assertInstanceOf(HasMany::class, $run->entries());
        $this->assertInstanceOf(TimetableGenerationEntry::class, $run->entries()->getRelated());
    }

    public function test_timetable_generation_entry_relationships(): void
    {
        $entry = new TimetableGenerationEntry();

        $this->assertInstanceOf(BelongsTo::class, $entry->generation_run());
        $this->assertInstanceOf(TimetableGenerationRun::class, $entry->generation_run()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->class_section());
        $this->assertInstanceOf(ClassSection::class, $entry->class_section()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->subject());
        $this->assertInstanceOf(Subject::class, $entry->subject()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->subject_teacher());
        $this->assertInstanceOf(SubjectTeacher::class, $entry->subject_teacher()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->teacher());
        $this->assertInstanceOf(User::class, $entry->teacher()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->resource());
        $this->assertInstanceOf(TimetableResource::class, $entry->resource()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $entry->period());
        $this->assertInstanceOf(TimetablePeriod::class, $entry->period()->getRelated());
    }

    public function test_timetable_new_relationships(): void
    {
        $timetable = new Timetable();

        $this->assertInstanceOf(BelongsTo::class, $timetable->period());
        $this->assertInstanceOf(TimetablePeriod::class, $timetable->period()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $timetable->resource());
        $this->assertInstanceOf(TimetableResource::class, $timetable->resource()->getRelated());

        $this->assertInstanceOf(BelongsTo::class, $timetable->locked_by_user());
        $this->assertInstanceOf(User::class, $timetable->locked_by_user()->getRelated());
    }

    public function test_subject_teacher_new_relationships(): void
    {
        $st = new SubjectTeacher();

        $this->assertInstanceOf(HasMany::class, $st->alternates());
        $this->assertInstanceOf(SubjectTeacherAlternate::class, $st->alternates()->getRelated());

        $this->assertInstanceOf(HasMany::class, $st->active_alternates());
        $this->assertInstanceOf(SubjectTeacherAlternate::class, $st->active_alternates()->getRelated());
    }

    public function test_class_section_new_relationships(): void
    {
        $cs = new ClassSection();

        $this->assertInstanceOf(HasMany::class, $cs->schedule_requirements());
        $this->assertInstanceOf(SubjectScheduleRequirement::class, $cs->schedule_requirements()->getRelated());

        $this->assertInstanceOf(HasMany::class, $cs->availabilities());
        $this->assertInstanceOf(ClassSectionAvailability::class, $cs->availabilities()->getRelated());
    }

    public function test_user_new_relationships(): void
    {
        $user = new User();

        $this->assertInstanceOf(HasMany::class, $user->teacher_availabilities());
        $this->assertInstanceOf(TeacherAvailability::class, $user->teacher_availabilities()->getRelated());

        $this->assertInstanceOf(HasMany::class, $user->alternate_teacher_assignments());
        $this->assertInstanceOf(SubjectTeacherAlternate::class, $user->alternate_teacher_assignments()->getRelated());
    }

    public function test_subject_new_relationships(): void
    {
        $subject = new Subject();

        $this->assertInstanceOf(HasMany::class, $subject->schedule_requirements());
        $this->assertInstanceOf(SubjectScheduleRequirement::class, $subject->schedule_requirements()->getRelated());
    }
}
