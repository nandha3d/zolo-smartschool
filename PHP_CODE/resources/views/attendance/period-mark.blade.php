@extends('layouts.master')

@section('title')
    {{ __('Period attendance') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="page-title">{{ __('Period attendance') }}</h3>
            <a href="{{ url()->previous() }}" class="btn btn-light btn-sm"><i class="fa fa-arrow-left mr-1"></i>{{ __('Back') }}</a>
        </div>

        <div class="card mb-3">
            <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 font-weight-bold">{{ $slot->subject?->name }} · {{ $slot->class_section?->full_name }}</h5>
                    <div class="text-muted">
                        {{ $date->format('l, d M Y') }} · P{{ $period }} ({{ substr($slot->start_time, 0, 5) }} - {{ substr($slot->end_time, 0, 5) }})
                        @if ($existing->isNotEmpty())
                            · <span class="text-success">{{ __('Already marked; saving updates it.') }}</span>
                        @endif
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-outline-success btn-sm" id="pa-all-present">{{ __('All present') }}</button>
                    <button type="button" class="btn btn-theme btn-sm" id="pa-save"><i class="fa fa-save mr-1"></i>{{ __('Save') }}</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                        <tr>
                            <th style="width:90px;">{{ __('Roll No') }}</th>
                            <th>{{ __('Student') }}</th>
                            <th style="width:280px;">{{ __('Status') }}</th>
                            <th>{{ __('Remark') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($students as $s)
                            @php
                                $cur = $existing->get($s->id);
                            @endphp
                            <tr data-student="{{ $s->id }}">
                                <td>{{ $s->roll_number_label ?? $s->roll_number }}</td>
                                <td>{{ $s->user?->full_name }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
                                        @foreach (['present' => 'P', 'absent' => 'A', 'late' => 'L'] as $value => $label)
                                            <label class="btn btn-outline-{{ $value === 'present' ? 'success' : ($value === 'absent' ? 'danger' : 'warning') }} {{ ($cur?->status ?? '') === $value ? 'active' : '' }}">
                                                <input type="radio" name="st_{{ $s->id }}" value="{{ $value }}" autocomplete="off" {{ ($cur?->status ?? '') === $value ? 'checked' : '' }}> {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <select class="form-control form-control-sm d-inline-block ml-1 pa-other" style="width:auto;">
                                        <option value="">{{ __('Other') }}</option>
                                        @foreach (['left_early' => __('Left early'), 'excused' => __('Excused'), 'infirmary' => __('Infirmary'), 'school_activity' => __('School activity')] as $value => $label)
                                            <option value="{{ $value }}" {{ ($cur?->status ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input class="form-control form-control-sm pa-remark" maxlength="255" value="{{ $cur?->remarks }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted p-4">{{ __('No students in this class.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            // Choosing P/A/L clears "Other" and vice versa, so each row has one status.
            $(document).on('change', 'tr[data-student] input[type=radio]', function () {
                $(this).closest('tr').find('.pa-other').val('');
            });
            $(document).on('change', '.pa-other', function () {
                if (this.value) {
                    var tr = $(this).closest('tr');
                    tr.find('input[type=radio]').prop('checked', false).parent().removeClass('active');
                }
            });
            $('#pa-all-present').on('click', function () {
                $('tr[data-student]').each(function () {
                    var tr = $(this);
                    if (tr.find('input[type=radio]:checked').length || tr.find('.pa-other').val()) { return; }
                    tr.find('input[value=present]').prop('checked', true).parent().addClass('active');
                });
            });

            $('#pa-save').on('click', function () {
                var rows = [], missing = 0;
                $('tr[data-student]').each(function () {
                    var tr = $(this);
                    var status = tr.find('.pa-other').val() || tr.find('input[type=radio]:checked').val();
                    if (!status) { missing++; return; }
                    rows.push({student_id: tr.data('student'), status: status, remarks: tr.find('.pa-remark').val()});
                });
                if (missing) {
                    var msg = missing + ' ' + @json(__('students have no status yet.'));
                    typeof showErrorToast === 'function' ? showErrorToast(msg) : alert(msg);
                    return;
                }
                var btn = $(this).prop('disabled', true);
                $.ajax({
                    url: @json(route('period-attendance.store')), method: 'POST', contentType: 'application/json',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: JSON.stringify({timetable_id: {{ (int) $slot->id }}, date: @json($date->toDateString()), attendance: rows})
                }).done(function (res) {
                    (res.error ? showErrorToast : showSuccessToast)(res.message);
                }).fail(function (xhr) {
                    showErrorToast((xhr.responseJSON && xhr.responseJSON.message) || 'Error');
                }).always(function () { btn.prop('disabled', false); });
            });
        })();
    </script>
@endsection
