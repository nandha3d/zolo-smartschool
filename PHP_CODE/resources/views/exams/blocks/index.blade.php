@extends('layouts.master')

@section('title')
    {{ __('Exam Block Schedule') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="zolo-page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="zolo-heading zolo-page-title" style="font-size:24px;font-weight:700;color:var(--zolo-ink, #0F172A);margin:0;">
                <i class="fa fa-calendar-check-o text-primary mr-2"></i>{{ __('Exam Block Schedule') }}
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                {{ __('Overlay exam schedules on the timetable: Block Morning for Exams & Afternoon for Supervised Revision.') }}
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-theme" data-toggle="modal" data-target="#createBlockModal">
                <i class="fa fa-plus-circle mr-1"></i>{{ __('Add Exam / Revision Block') }}
            </button>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card mb-4" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('exams.blocks.index') }}" class="row align-items-center">
                <div class="col-md-5 col-sm-12">
                    <label class="font-weight-bold text-muted mb-1" style="font-size:12px;text-transform:uppercase;">
                        {{ __('Select Examination') }}
                    </label>
                    <select name="exam_id" class="form-control" onchange="this.form.submit()">
                        @foreach ($exams as $ex)
                            <option value="{{ $ex->id }}" {{ $selected_exam_id == $ex->id ? 'selected' : '' }}>
                                {{ $ex->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5 col-sm-12 mt-2 mt-md-0">
                    <label class="font-weight-bold text-muted mb-1" style="font-size:12px;text-transform:uppercase;">
                        {{ __('Select Class Section') }}
                    </label>
                    <select name="class_section_id" class="form-control" onchange="this.form.submit()">
                        @foreach ($class_sections as $cs)
                            <option value="{{ $cs->id }}" {{ $selected_class_section_id == $cs->id ? 'selected' : '' }}>
                                {{ $cs->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 text-md-right mt-3 mt-md-0">
                    <span class="badge badge-pill badge-primary p-2">
                        {{ $blocks->count() }} {{ __('Blocks Scheduled') }}
                    </span>
                </div>
            </form>
        </div>
    </div>

    {{-- Schedule Grid --}}
    <div class="card" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 font-weight-bold" style="font-size:15px;color:var(--zolo-ink, #0F172A);">
                <i class="fa fa-th text-info mr-2"></i>{{ __('Scheduled Blocks Timeline') }}
            </h5>
            <small class="text-muted">{{ __('Morning Exams & Afternoon Revision Periods') }}</small>
        </div>
        <div class="card-body p-0">
            @if ($blocks->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fa fa-calendar-times-o fa-3x mb-3 text-muted" style="opacity:0.3;"></i>
                    <h5>{{ __('No schedule blocks created for this exam & class yet.') }}</h5>
                    <p class="text-muted mb-3">{{ __('Add morning exam periods and afternoon practice/revision sessions.') }}</p>
                    <button class="btn btn-sm btn-theme" data-toggle="modal" data-target="#createBlockModal">
                        <i class="fa fa-plus mr-1"></i>{{ __('Create First Schedule Block') }}
                    </button>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#F8FAFC;">
                            <tr>
                                <th style="font-weight:700;font-size:12px;">{{ __('Date') }}</th>
                                <th style="font-weight:700;font-size:12px;">{{ __('Block Type') }}</th>
                                <th style="font-weight:700;font-size:12px;">{{ __('Subject') }}</th>
                                <th style="font-weight:700;font-size:12px;">{{ __('Timing / Periods') }}</th>
                                <th style="font-weight:700;font-size:12px;">{{ __('Invigilator / In-Charge') }}</th>
                                <th style="font-weight:700;font-size:12px;">{{ __('Hall / Room') }}</th>
                                <th style="font-weight:700;font-size:12px;text-align:right;">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($blocks as $b)
                                <tr>
                                    <td class="font-weight-bold">
                                        <div style="font-size:14px;color:#0F172A;">{{ \Carbon\Carbon::parse($b->date)->format('d M Y') }}</div>
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($b->date)->format('l') }}</small>
                                    </td>
                                    <td>
                                        @if ($b->block_type === 'exam')
                                            <span class="badge badge-danger p-2" style="font-weight:700;letter-spacing:0.04em;">
                                                <i class="fa fa-pencil-square-o mr-1"></i>{{ __('EXAM BLOCK') }}
                                            </span>
                                        @elseif ($b->block_type === 'revision')
                                            <span class="badge badge-info p-2" style="font-weight:700;letter-spacing:0.04em;">
                                                <i class="fa fa-book mr-1"></i>{{ __('REVISION BLOCK') }}
                                            </span>
                                        @elseif ($b->block_type === 'practice')
                                            <span class="badge badge-warning p-2" style="font-weight:700;letter-spacing:0.04em;">
                                                <i class="fa fa-tasks mr-1"></i>{{ __('PRACTICE') }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary p-2">{{ strtoupper($b->block_type) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $b->subject->name ?? 'General Study' }}</strong>
                                        @if ($b->total_marks)
                                            <div class="text-muted small">Max Marks: {{ $b->total_marks }} (Pass: {{ $b->passing_marks ?? '-' }})</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-size:13px;font-weight:600;">
                                            {{ $b->custom_start_time ? date('h:i A', strtotime($b->custom_start_time)) : ($b->startPeriod?->name ?? 'Morning') }}
                                            -
                                            {{ $b->custom_end_time ? date('h:i A', strtotime($b->custom_end_time)) : ($b->endPeriod?->name ?? 'Afternoon') }}
                                        </div>
                                        @if ($b->startPeriod && $b->endPeriod)
                                            <small class="text-muted">Periods: {{ $b->startPeriod->name }} to {{ $b->endPeriod->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div><i class="fa fa-user-circle mr-1 text-muted"></i>{{ $b->examIncharge?->user?->full_name ?? ($b->examIncharge?->user?->first_name ?? 'Not Assigned') }}</div>
                                        @if ($b->assistantIncharge)
                                            <small class="text-muted">Asst: {{ $b->assistantIncharge->user?->first_name ?? '' }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-dark">{{ $b->hallResource->name ?? 'Classroom' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-outline-danger" onclick="deleteBlock({{ $b->id }})">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal: Create Exam Schedule Block --}}
<div class="modal fade" id="createBlockModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-plus-circle text-primary mr-2"></i>{{ __('Add Exam / Revision Schedule Block') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createBlockForm" action="{{ route('exams.blocks.store') }}" method="POST">
                @csrf
                <input type="hidden" name="exam_id" value="{{ $selected_exam_id }}">
                <input type="hidden" name="class_section_id" value="{{ $selected_class_section_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Date of Exam / Session') }} <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Schedule Block Type') }} <span class="text-danger">*</span></label>
                            <select name="block_type" class="form-control" required>
                                <option value="exam">{{ __('Morning Exam Block (Examination Paper)') }}</option>
                                <option value="revision">{{ __('Afternoon Revision Block (Supervised Prep)') }}</option>
                                <option value="practice">{{ __('Practice / Problem Solving Session') }}</option>
                                <option value="lab_practical">{{ __('Lab Practical Exam') }}</option>
                                <option value="study_leave">{{ __('Study Leave (Half Day Release)') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Subject') }}</label>
                            <select name="subject_id" class="form-control">
                                <option value="">-- {{ __('Select Subject (Optional)') }} --</option>
                                @foreach ($subjects as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->type ?? 'Core' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Hall / Room Assignment') }}</label>
                            <select name="hall_or_resource_id" class="form-control">
                                <option value="">-- {{ __('Default Classroom') }} --</option>
                                @foreach ($resources as $res)
                                    <option value="{{ $res->id }}">{{ $res->name }} (Capacity: {{ $res->capacity ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Custom Start Time') }}</label>
                            <input type="time" name="custom_start_time" class="form-control" value="09:00">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Custom End Time') }}</label>
                            <input type="time" name="custom_end_time" class="form-control" value="12:00">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Exam In-Charge / Chief Invigilator') }}</label>
                            <select name="exam_incharge_staff_id" class="form-control">
                                <option value="">-- {{ __('Assign Invigilator') }} --</option>
                                @foreach ($staffMembers as $stf)
                                    <option value="{{ $stf->id }}">
                                        {{ $stf->user->full_name ?? ($stf->user->first_name . ' ' . $stf->user->last_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Assistant / Relieving Invigilator') }}</label>
                            <select name="assistant_incharge_staff_id" class="form-control">
                                <option value="">-- {{ __('Optional Assistant') }} --</option>
                                @foreach ($staffMembers as $stf)
                                    <option value="{{ $stf->id }}">
                                        {{ $stf->user->full_name ?? ($stf->user->first_name . ' ' . $stf->user->last_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Total Marks') }}</label>
                            <input type="number" name="total_marks" class="form-control" placeholder="100">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Passing Marks') }}</label>
                            <input type="number" name="passing_marks" class="form-control" placeholder="35">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Instructions / Topics to Cover') }}</label>
                        <textarea name="instructions" class="form-control" rows="2" placeholder="e.g. Bring scientific calculator. Afternoon revision will focus on Chapter 4 & 5."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-theme">{{ __('Save Schedule Block') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteBlock(id) {
    if (!confirm("Are you sure you want to delete this schedule block?")) return;
    $.ajax({
        url: "{{ url('exams/blocks') }}/" + id,
        type: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function (res) {
            location.reload();
        },
        error: function (err) {
            alert('Failed to delete block');
        }
    });
}
</script>
@endsection
