@extends('layouts.master')

@section('title')
    {{ __('Class Groups & Leadership') }}
@endsection

@section('content')
<div class="content-wrapper">
    <div class="zolo-page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="zolo-heading zolo-page-title" style="font-size:24px;font-weight:700;color:var(--zolo-ink, #0F172A);margin:0;">
                <i class="fa fa-users text-primary mr-2"></i>{{ __('Class Groups & Leadership') }}
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                {{ __('Assign Class Leaders, Bench Leaders, and Study Group Leads to promote student engagement.') }}
            </p>
        </div>
        <div class="d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#assignRoleModal">
                <i class="fa fa-star mr-1"></i>{{ __('Assign Leadership Role') }}
            </button>
            <button type="button" class="btn btn-sm btn-theme" data-toggle="modal" data-target="#createGroupModal">
                <i class="fa fa-plus-circle mr-1"></i>{{ __('Create Bench or Study Group') }}
            </button>
        </div>
    </div>

    {{-- Class Section Filter Card --}}
    <div class="card mb-4" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('students.leadership.index') }}" class="row align-items-center">
                <div class="col-md-5 col-sm-12">
                    <label class="font-weight-bold text-muted mb-1" style="font-size:12px;text-transform:uppercase;">
                        {{ __('Select Class Section') }}
                    </label>
                    <select name="class_section_id" id="filter_class_section_id" class="form-control" onchange="this.form.submit()">
                        @foreach ($class_sections as $cs)
                            <option value="{{ $cs->id }}" {{ $selected_class_section_id == $cs->id ? 'selected' : '' }}>
                                {{ $cs->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7 col-sm-12 d-flex justify-content-md-end align-items-center mt-3 mt-md-0">
                    <div class="zolo-stat-pill mr-3" style="background:#F1F5F9;padding:6px 14px;border-radius:20px;font-size:13px;">
                        <span class="text-muted">{{ __('Students in Class') }}:</span>
                        <strong class="text-dark ml-1">{{ $students->count() }}</strong>
                    </div>
                    <div class="zolo-stat-pill" style="background:#FEF3E2;padding:6px 14px;border-radius:20px;font-size:13px;">
                        <span style="color:#B45309;">{{ __('Assigned Leaders') }}:</span>
                        <strong style="color:#B45309;" class="ml-1">{{ $leadershipRoles->count() }}</strong>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Leadership Overview Cards --}}
    <div class="row">
        {{-- Section 1: Core Class Leaders --}}
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 font-weight-bold" style="font-size:15px;color:var(--zolo-ink, #0F172A);">
                        <i class="fa fa-star text-warning mr-2"></i>{{ __('Core Class Leaders') }}
                    </h5>
                    <button class="btn btn-xs btn-link text-primary p-0" data-toggle="modal" data-target="#assignRoleModal">
                        + {{ __('Assign') }}
                    </button>
                </div>
                <div class="card-body p-3">
                    @php
                        $coreRoles = $leadershipRoles->whereIn('role_type', ['class_leader', 'vice_class_leader', 'sports_captain', 'discipline_prefect']);
                    @endphp
                    @if ($coreRoles->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-id-badge fa-3x mb-2 text-muted" style="opacity:0.3;"></i>
                            <p class="mb-2">{{ __('No core class leaders appointed yet for this section.') }}</p>
                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#assignRoleModal">
                                {{ __('Appoint Class Leader') }}
                            </button>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($coreRoles as $role)
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-bottom">
                                    <div class="d-flex align-items-center">
                                        <div style="width:38px;height:38px;border-radius:50%;background:{{ $role->badge_color }}15;color:{{ $role->badge_color }};display:flex;align-items:center;justify-content:center;margin-right:12px;font-size:16px;">
                                            <i class="fa {{ $role->badge_icon }}"></i>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold text-dark" style="font-size:14px;">
                                                {{ $role->student->full_name ?? 'Student' }}
                                                <small class="text-muted ml-1">(Roll #{{ $role->student->roll_number ?? '-' }})</small>
                                            </div>
                                            <div style="font-size:12px;color:{{ $role->badge_color }};font-weight:600;">
                                                {{ $role->title }}
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <button class="btn btn-xs btn-outline-danger" onclick="deleteLeadershipRole({{ $role->id }})" title="{{ __('Remove Role') }}">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Section 2: Bench Leaders & Rows --}}
        <div class="col-lg-6 grid-margin stretch-card">
            <div class="card" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 font-weight-bold" style="font-size:15px;color:var(--zolo-ink, #0F172A);">
                        <i class="fa fa-flag text-success mr-2"></i>{{ __('Bench / Row Leaders') }}
                    </h5>
                    <button class="btn btn-xs btn-link text-primary p-0" data-toggle="modal" data-target="#createGroupModal" onclick="setGroupType('bench_group')">
                        + {{ __('Add Row') }}
                    </button>
                </div>
                <div class="card-body p-3">
                    @php
                        $benchGroups = $studentGroups->where('type', 'bench_group');
                    @endphp
                    @if ($benchGroups->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-th-large fa-3x mb-2 text-muted" style="opacity:0.3;"></i>
                            <p class="mb-2">{{ __('No benches / rows configured yet.') }}</p>
                            <button class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#createGroupModal" onclick="setGroupType('bench_group')">
                                {{ __('Create Bench 1 / Row 1') }}
                            </button>
                        </div>
                    @else
                        <div class="row">
                            @foreach ($benchGroups as $bg)
                                <div class="col-md-6 mb-3">
                                    <div style="border:1px solid #E2E8F0;border-left:4px solid {{ $bg->color_code }};border-radius:8px;padding:12px;background:#F8FAFC;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-weight-bold text-dark" style="font-size:13px;">{{ $bg->name }}</span>
                                            <button class="btn btn-xs text-danger p-0" onclick="deleteStudentGroup({{ $bg->id }})">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </div>
                                        <div style="font-size:12px;color:#047857;margin-bottom:6px;">
                                            <i class="fa fa-user-circle mr-1"></i>
                                            {{ __('Leader') }}: <strong>{{ $bg->leader->full_name ?? 'Unassigned' }}</strong>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">
                                            <i class="fa fa-users mr-1"></i>{{ $bg->students->count() }} {{ __('Students seated') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Section 3: Study Groups & Project Teams --}}
    <div class="card mb-4" style="border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 font-weight-bold" style="font-size:15px;color:var(--zolo-ink, #0F172A);">
                <i class="fa fa-cubes text-info mr-2"></i>{{ __('Study Groups & Project Teams') }}
            </h5>
            <button class="btn btn-sm btn-theme" data-toggle="modal" data-target="#createGroupModal" onclick="setGroupType('study_group')">
                <i class="fa fa-plus mr-1"></i>{{ __('Create Study Group') }}
            </button>
        </div>
        <div class="card-body p-3">
            @php
                $studyGroups = $studentGroups->whereIn('type', ['study_group', 'project_team', 'activity_club']);
            @endphp
            @if ($studyGroups->isEmpty())
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">{{ __('No study groups or project teams created for this section yet.') }}</p>
                </div>
            @else
                <div class="row">
                    @foreach ($studyGroups as $sg)
                        <div class="col-md-4 mb-3">
                            <div class="card h-100" style="border:1px solid #E2E8F0;border-radius:8px;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge" style="background:{{ $sg->color_code }}20;color:{{ $sg->color_code }};font-weight:700;">
                                            {{ strtoupper(str_replace('_', ' ', $sg->type)) }}
                                        </span>
                                        <button class="btn btn-xs text-danger p-0" onclick="deleteStudentGroup({{ $sg->id }})">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                    <h6 class="font-weight-bold text-dark mb-1">{{ $sg->name }}</h6>
                                    <p class="text-muted small mb-2">{{ $sg->description ?: 'Collaborative student team.' }}</p>
                                    <div class="border-top pt-2" style="font-size:12px;">
                                        <div><span class="text-muted">{{ __('Lead') }}:</span> <strong>{{ $sg->leader->full_name ?? 'None' }}</strong></div>
                                        <div class="text-muted mt-1">{{ $sg->students->count() }} {{ __('members enrolled') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal 1: Assign Leadership Role --}}
<div class="modal fade" id="assignRoleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-star text-warning mr-2"></i>{{ __('Assign Leadership Role') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="assignRoleForm" action="{{ route('students.leadership.assign-role') }}" method="POST">
                @csrf
                <input type="hidden" name="class_section_id" value="{{ $selected_class_section_id }}">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Select Student') }} <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-control" required>
                            <option value="">-- {{ __('Choose Student') }} --</option>
                            @foreach ($students as $st)
                                <option value="{{ $st->id }}">
                                    Roll #{{ $st->roll_number }} - {{ $st->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Role Type') }} <span class="text-danger">*</span></label>
                        <select name="role_type" id="modal_role_type" class="form-control" required onchange="autoFillRoleTitle(this.value)">
                            <option value="class_leader">{{ __('Class Leader (Head Monitor)') }}</option>
                            <option value="vice_class_leader">{{ __('Vice Class Leader (Deputy Monitor)') }}</option>
                            <option value="bench_leader">{{ __('Bench / Row Leader') }}</option>
                            <option value="group_leader">{{ __('Group Leader / Team Lead') }}</option>
                            <option value="discipline_prefect">{{ __('Discipline Prefect') }}</option>
                            <option value="sports_captain">{{ __('Sports Captain') }}</option>
                            <option value="lab_batch_leader">{{ __('Lab Batch Leader') }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Display Title / Badge Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="modal_role_title" class="form-control" value="Class Leader" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Badge Color') }}</label>
                            <input type="color" name="badge_color" class="form-control p-1" value="#E67E22" style="height:38px;">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Badge Icon') }}</label>
                            <select name="badge_icon" class="form-control">
                                <option value="fa-star">★ Star (Leader)</option>
                                <option value="fa-flag">⚑ Flag (Bench)</option>
                                <option value="fa-users">👥 Team (Group)</option>
                                <option value="fa-trophy">🏆 Trophy (Sports)</option>
                                <option value="fa-shield">🛡 Shield (Discipline)</option>
                                <option value="fa-flask">⚗ Flask (Lab)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-theme">{{ __('Assign Role') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Create Group Modal --}}
<div class="modal fade" id="createGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold"><i class="fa fa-users text-primary mr-2"></i>{{ __('Create Student Group') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createGroupForm" action="{{ route('students.leadership.store-group') }}" method="POST">
                @csrf
                <input type="hidden" name="class_section_id" value="{{ $selected_class_section_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Group Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="modal_group_name" class="form-control" placeholder="e.g. Row 1 Bench A or Group Alpha" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Group Type') }} <span class="text-danger">*</span></label>
                            <select name="type" id="modal_group_type" class="form-control" required>
                                <option value="bench_group">{{ __('Bench / Row Group') }}</option>
                                <option value="study_group">{{ __('Study / Academic Group') }}</option>
                                <option value="project_team">{{ __('Project Team') }}</option>
                                <option value="activity_club">{{ __('Activity Club') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Group Leader') }}</label>
                            <select name="leader_student_id" class="form-control">
                                <option value="">-- {{ __('Select Leader (Optional)') }} --</option>
                                @foreach ($students as $st)
                                    <option value="{{ $st->id }}">Roll #{{ $st->roll_number }} - {{ $st->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">{{ __('Theme Color') }}</label>
                            <input type="color" name="color_code" class="form-control p-1" value="#22577A" style="height:38px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Enroll Students') }}</label>
                        <select name="student_ids[]" class="form-control select2" multiple style="width:100%;">
                            @foreach ($students as $st)
                                <option value="{{ $st->id }}">Roll #{{ $st->roll_number }} - {{ $st->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Description / Notes') }}</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Optional notes regarding group purpose...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-theme">{{ __('Create Group') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function autoFillRoleTitle(roleType) {
    var titles = {
        'class_leader': 'Class Leader',
        'vice_class_leader': 'Vice Class Leader',
        'bench_leader': 'Bench Leader',
        'group_leader': 'Group Leader',
        'discipline_prefect': 'Discipline Prefect',
        'sports_captain': 'Sports Captain',
        'lab_batch_leader': 'Lab Batch Leader'
    };
    if (titles[roleType]) {
        document.getElementById('modal_role_title').value = titles[roleType];
    }
}

function setGroupType(type) {
    var typeSelect = document.getElementById('modal_group_type');
    if (typeSelect) {
        typeSelect.value = type;
        if (type === 'bench_group') {
            document.getElementById('modal_group_name').value = 'Row ' + (document.querySelectorAll('.card').length - 1) + ' Bench';
        }
    }
}

function deleteLeadershipRole(id) {
    if (!confirm("Are you sure you want to remove this student leadership role?")) return;
    $.ajax({
        url: "{{ url('students/groups-leadership/role') }}/" + id,
        type: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function (res) {
            location.reload();
        },
        error: function (err) {
            alert('Failed to remove role');
        }
    });
}

function deleteStudentGroup(id) {
    if (!confirm("Are you sure you want to delete this group?")) return;
    $.ajax({
        url: "{{ url('students/groups-leadership/group') }}/" + id,
        type: 'DELETE',
        data: { _token: '{{ csrf_token() }}' },
        success: function (res) {
            location.reload();
        },
        error: function (err) {
            alert('Failed to delete group');
        }
    });
}

$(document).ready(function() {
    $('#assignRoleForm').on('submit', function(e) {
        e.preventDefault();
        let form = $(this);
        let btn = form.find('button[type="submit"]');
        let origText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(res) {
                $('#assignRoleModal').modal('hide');
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(res.message || 'Student leadership role assigned successfully.');
                }
                setTimeout(function() { location.reload(); }, 600);
            },
            error: function(err) {
                btn.prop('disabled', false).html(origText);
                let msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : 'Failed to assign role';
                if (typeof showErrorToast === 'function') {
                    showErrorToast(msg);
                } else {
                    alert(msg);
                }
            }
        });
    });

    $('#createGroupForm').on('submit', function(e) {
        e.preventDefault();
        let form = $(this);
        let btn = form.find('button[type="submit"]');
        let origText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(res) {
                $('#createGroupModal').modal('hide');
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(res.message || 'Student group created successfully.');
                }
                setTimeout(function() { location.reload(); }, 600);
            },
            error: function(err) {
                btn.prop('disabled', false).html(origText);
                let msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : 'Failed to create group';
                if (typeof showErrorToast === 'function') {
                    showErrorToast(msg);
                } else {
                    alert(msg);
                }
            }
        });
    });
});
</script>
@endsection
