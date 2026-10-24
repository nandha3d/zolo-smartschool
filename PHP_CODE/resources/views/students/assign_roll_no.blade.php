@extends('layouts.master')

@section('title')
    {{ __('assign_roll_no') }}
@endsection

@section('content')
    <style>
        .btn-outline-success {
            padding: 15px;
        }
    </style>
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage_students_roll_no') }}
            </h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list') . ' ' . __('students') }}
                        </h4>
                        <div id="toolbar" class="w-100">
                            <div class="row align-items-end">
                                <div class="form-group col-sm-12 col-md-3">
                                    <label class="filter-menu font-weight-bold" for="filter_roll_number_class_section_id">{{ __('Class Section') }} </label>
                                    <select name="filter_roll_number_class_section_id" id="filter_roll_number_class_section_id" class="form-control">
                                        @foreach ($class_section as $class)
                                            <option value="{{ $class->id }}" data-smart-prefix="{{ $class->getSmartRollPrefix($defaultPrefix ?? null) }}">
                                                {{ $class->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-sm-6 col-md-2">
                                    <label class="filter-menu font-weight-bold" for="sort_by">{{ __('Sort By') }} </label>
                                    <select name="sort_by" id="sort_by" class="form-control">
                                        <option value="first_name">{{ __('First Name') }}</option>
                                        <option value="last_name">{{ __('Last Name') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-sm-6 col-md-2">
                                    <label class="filter-menu font-weight-bold" for="order_by">{{ __('Order') }} </label>
                                    <select name="order_by" id="order_by" class="form-control">
                                        <option value="asc" selected>{{ __('Ascending') }}</option>
                                        <option value="desc">{{ __('Descending') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-sm-6 col-md-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="filter-menu font-weight-bold mb-1" for="roll_number_prefix">
                                            <i class="fa fa-tag text-primary mr-1"></i>{{ __('Prefix') }}
                                        </label>
                                        <label class="small text-muted mb-1 font-weight-normal" style="cursor:pointer;" title="{{ __('Auto-generate class prefix (e.g. CL1A, CL2A) per selected class') }}">
                                            <input type="checkbox" id="auto_class_prefix_toggle" checked> {{ __('Auto') }}
                                        </label>
                                    </div>
                                    <input type="text" id="roll_number_prefix" name="roll_number_prefix" class="form-control" placeholder="e.g. CL1A or 26/" value="{{ $initialClassPrefix ?? $defaultPrefix ?? '' }}">
                                </div>

                                <div class="form-group col-sm-6 col-md-2">
                                    <label class="filter-menu font-weight-bold" for="roll_number_digits">
                                        <i class="fa fa-hashtag text-primary mr-1"></i>{{ __('Digits Format') }}
                                    </label>
                                    <select name="roll_number_digits" id="roll_number_digits" class="form-control">
                                        <option value="0" {{ ($defaultDigits ?? '0') == '0' ? 'selected' : '' }}>{{ __('Auto (1, 2, 3...)') }}</option>
                                        <option value="2" {{ ($defaultDigits ?? '0') == '2' ? 'selected' : '' }}>{{ __('2 Digits (01, 02...)') }}</option>
                                        <option value="3" {{ ($defaultDigits ?? '0') == '3' ? 'selected' : '' }}>{{ __('3 Digits (001, 002...)') }}</option>
                                        <option value="4" {{ ($defaultDigits ?? '0') == '4' ? 'selected' : '' }}>{{ __('4 Digits (0001...)') }}</option>
                                        <option value="5" {{ ($defaultDigits ?? '0') == '5' ? 'selected' : '' }}>{{ __('5 Digits (00001...)') }}</option>
                                    </select>
                                </div>

                                <div class="form-group col-sm-6 col-md-1">
                                    <label class="filter-menu font-weight-bold" for="roll_number_start">{{ __('Start') }}</label>
                                    <input type="number" id="roll_number_start" name="roll_number_start" min="1" class="form-control" value="{{ $defaultStart ?? '1' }}">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                <span class="text-muted" style="font-size:12px;">
                                    <i class="fa fa-info-circle text-info mr-1"></i>
                                    {{ __('Type a prefix or select digit padding to re-calculate sequence automatically. You can also edit individual roll numbers directly in the table.') }}
                                </span>
                                <button type="button" id="btn_refresh_roll_sequence" class="btn btn-xs btn-outline-primary">
                                    <i class="fa fa-refresh mr-1"></i>{{ __('Recalculate') }}
                                </button>
                            </div>
                        </div>
                        <form id="assign-roll-no-form" action="{{ route('students.roll-number.update') }}" method="post">
                            @csrf
                            <div class="row search-container">
                                <div class="col-12">
                                    <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table" data-url="{{ route('students.roll-number.show',1) }}" data-click-to-select="true" data-search="true" data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-trim-on-search="false" data-mobile-responsive="true" data-maintain-selected="true" data-show-export="true" data-export-data-type='all' data-export-options='{ "fileName": "{{__('students')}} {{__('roll_no')}}-<?= date('d-m-y') ?>","ignoreColumn": ["operate"]}' data-query-params="studentRollNumberQueryParams">
                                        <thead>
                                        <tr>
                                            <th scope="col" data-field="no">{{ __('no.') }}</th>
                                            <th scope="col" data-field="student_id" data-visible="false">{{ __('student_id') }} </th>
                                            <th scope="col" data-field="user_id" data-visible="false">{{ __('User Id') }}</th>
                                            <th scope="col" data-field="new_roll_number">{{ __('new_roll_no') }}</th>
                                            <th scope="col" data-field="old_roll_number">{{ __('old_roll_no') }}</th>
                                            <th scope="col" data-field="first_name">{{ __('first_name') }}</th>
                                            <th scope="col" data-field="last_name">{{ __('last_name') }}</th>
                                            <th scope="col" data-field="dob">{{ __('dob') }}</th>
                                            <th scope="col" data-field="image" data-formatter="imageFormatter">{{ __('image') }}</th>
                                            <th scope="col" data-field="class_section_id" data-visible="false">{{ __('Class') . ' ' . __('section') . ' ' . __('id') }}</th>
                                            <th scope="col" data-field="admission_no">{{ __('admission_no') }}</th>
                                            <th scope="col" data-field="admission_date">{{ __('admission_date') }}</th>
                                        </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                            <div class="text-left">
                                <button class="btn btn-theme my-4 float-right px-4" id="submit-roll-btn" type="submit">
                                    <i class="fa fa-check mr-1"></i> {{ __('Save Roll Numbers') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        function syncSmartPrefix(force) {
            if ($('#auto_class_prefix_toggle').is(':checked') || force) {
                let selectedOpt = $('#filter_roll_number_class_section_id').find(':selected');
                let smartPrefix = selectedOpt.data('smart-prefix');
                if (typeof smartPrefix !== 'undefined') {
                    $('#roll_number_prefix').val(smartPrefix);
                }
            }
        }

        // Initialize prefix on page load with selected class's smart prefix
        syncSmartPrefix(false);

        // When class section dropdown changes: auto-sync prefix and refresh table
        $('#filter_roll_number_class_section_id').on('change', function() {
            syncSmartPrefix(false);
            $('#table_list').bootstrapTable('refresh');
        });

        // When auto toggle is clicked
        $('#auto_class_prefix_toggle').on('change', function() {
            if ($(this).is(':checked')) {
                syncSmartPrefix(true);
                $('#table_list').bootstrapTable('refresh');
            }
        });

        // If user manually types into the prefix field, uncheck Auto so custom prefix stays
        let prefixTimer;
        $('#roll_number_prefix').on('input keyup', function() {
            $('#auto_class_prefix_toggle').prop('checked', false);
            clearTimeout(prefixTimer);
            prefixTimer = setTimeout(function() {
                $('#table_list').bootstrapTable('refresh');
            }, 400);
        });

        // Auto-refresh table when sorting, digits, or start changes
        $('#sort_by, #order_by, #roll_number_digits, #roll_number_start').on('change', function() {
            $('#table_list').bootstrapTable('refresh');
        });

        $('#btn_refresh_roll_sequence').on('click', function() {
            $('#table_list').bootstrapTable('refresh');
        });

        // AJAX submit with feedback
        $('#assign-roll-no-form').on('submit', function(e) {
            e.preventDefault();
            let form = $(this);
            let btn = $('#submit-roll-btn');
            let origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving Roll Numbers...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                success: function(res) {
                    btn.prop('disabled', false).html(origHtml);
                    if (typeof showSuccessToast === 'function') {
                        showSuccessToast(res.message || 'Roll numbers updated successfully!');
                    }
                    $('#table_list').bootstrapTable('refresh');
                },
                error: function(err) {
                    btn.prop('disabled', false).html(origHtml);
                    let msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : 'Failed to update roll numbers';
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
