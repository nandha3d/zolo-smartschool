@extends('layouts.master')

@section('title')
    {{ __('students') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="zolo-page-header">
            <h1 class="zolo-heading zolo-page-title">{{ __('manage') . ' ' . __('students') }}</h1>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('list') . ' ' . __('students') }}
                        </h4>
                        <div class="row align-items-end" id="toolbar">
                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu">{{ __('Class Section') }} <span class="text-danger">*</span></label>
                                <select name="filter_class_section_id" id="filter_class_section_id" class="form-control">
                                    <option value="">{{ __('select_class_section') }}</option>
                                    @foreach ($class_sections as $class_section)
                                        <option value={{ $class_section->id }}>{{$class_section->full_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <label class="filter-menu">{{ __('Session Year') }} <span class="text-danger">*</span></label>
                                <select name="filter_session_year_id" id="filter_session_year_id" class="form-control">
                                    @foreach ($sessionYears as $sessionYear)
                                        <option value={{ $sessionYear->id }} {{$sessionYear->default==1?"selected":""}}>{{$sessionYear->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            @can('student-delete')
                                <div class="form-group col-12 col-md-4">
                                    <button id="update-status" class="btn btn-secondary" disabled><span class="update-status-btn-name">{{ __('Inactive') }}</span></button>
                                </div>
                            @endcan
                        </div>

                        @can('student-delete')
                            <div class="col-12 mt-2 mb-2 text-right">
                                <b><a href="#" class="table-list-type active mr-2" data-id="0">{{__('active')}}</a></b> | <a href="#" class="ml-2 table-list-type" data-id="1">{{__("Inactive")}}</a>
                            </div>
                        @endcan
                        <div class="row">
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list'
                                       data-toggle="table" data-url="{{ route('students.show',[1]) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                                       data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                                       data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                                       data-sort-order="desc" data-maintain-selected="true" data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']" data-show-export="true"
                                       data-export-options='{ "fileName": "students-list-<?= date('d-m-y') ?>" ,"ignoreColumn": ["operate"]}' data-query-params="studentDetailsQueryParams"
                                       data-check-on-init="true" data-escape="true">
                                    <thead>
                                    <tr>
                                        <th data-field="state" data-checkbox="true"></th>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="user.id" data-visible="false">{{ __('User Id') }}</th>
                                        <th scope="col" data-field="user.full_name" data-formatter="StudentNameFormatter">{{ __('name') }}</th>
                                        <th scope="col" data-field="user.dob" >{{ __('dob') }}</th>
                                        <th scope="col" data-field="class_section.full_name">{{ __('class_section') }}</th>
                                        <th scope="col" data-field="roll_number">{{ __('roll_no') }}</th>
                                        <th scope="col" data-field="user.gender">{{ __('gender') }}</th>
                                        <th scope="col" data-field="admission_date" >{{ __('admission_date') }}</th>
                                        <th scope="col" data-field="guardian.email">{{ __('guardian') . ' ' . __('email') }}</th>
                                        <th scope="col" data-field="guardian.full_name">{{ __('guardian') . ' ' . __('name') }}</th>
                                        <th scope="col" data-field="guardian.mobile">{{ __('guardian') . ' ' . __('mobile') }}</th>
                                        <th scope="col" data-field="guardian.gender">{{ __('guardian') . ' ' . __('gender') }}</th>

                                        {{-- Admission form fields --}}
                                        @foreach ($extraFields as $field)
                                            <th scope="col" data-visible="false" data-escape="false" data-field="{{ $field->name }}">{{ $field->name }}</th>
                                        @endforeach
                                        {{-- End admission form fields --}}

                                        @canany(['student-edit','student-delete'])
                                            <th data-events="studentEvents" class="align-button text-center" scope="col" data-field="operate" data-escape="false">{{ __('action') }}</th>
                                        @endcanany
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('student-edit')
        <div class="modal fade zolo-compact-modal" id="editModal" data-backdrop="static" tabindex="-1" role="dialog"
             aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title m-0 font-weight-bold d-flex align-items-center" id="exampleModalLabel">
                            <i class="fa fa-user-circle text-primary mr-2"></i>{{ __('edit') . ' ' . __('students') }}
                        </h5>
                        <button type="button" class="close text-dark opacity-75 p-2 m-0" data-dismiss="modal" aria-label="Close" style="outline: none;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form id="edit-form" class="edit-student-registration-form" novalidate="novalidate" action="{{ url('students') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <!-- Section 1: Student Information -->
                            <div class="zolo-section-divider mt-0">
                                <i class="fa fa-id-card"></i> {{ __('Student Information') }}
                            </div>

                            <div class="row">
                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('admission_no') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('admission_no', null, ['placeholder' => __('admission_no'), 'class' => 'form-control', 'id' => 'edit_admission_no' ,'readonly'=>true]) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('Session Year') }} <span class="text-danger">*</span></label>
                                    <select required name="session_year_id" class="form-control" id="session_year_id">
                                        @foreach ($sessionYears as $sessionYear)
                                            <option value="{{ $sessionYear->id }}">{{$sessionYear->name}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('Class Section') }} <span class="text-danger">*</span></label>
                                    <select required name="class_section_id" class="form-control" id="edit_student_class_section_id">
                                        <option value="">{{ __('select_class_section') }}</option>
                                        @foreach ($class_sections as $class_section)
                                            <option value={{ $class_section->id }}>{{$class_section->full_name}}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('dob') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('dob', null, ['placeholder' => __('dob'), 'class' => 'datepicker-popup-no-future form-control', 'id' => 'edit_dob']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('first_name', null, ['placeholder' => __('first_name'), 'class' => 'form-control', 'id' => 'edit_first_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('last_name', null, ['placeholder' => __('last_name'), 'class' => 'form-control', 'id' => 'edit_last_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('gender') }} <span class="text-danger">*</span></label>
                                    <div class="zolo-segmented-control" role="group" aria-label="Gender">
                                        <label class="zolo-segment-btn" for="male">
                                            <input type="radio" name="gender" value="male" id="male" class="zolo-segment-input" required>
                                            <span class="zolo-segment-content"><i class="fa fa-mars segment-icon icon-male"></i> {{ __('male') }}</span>
                                        </label>
                                        <label class="zolo-segment-btn" for="female">
                                            <input type="radio" name="gender" value="female" id="female" class="zolo-segment-input" required>
                                            <span class="zolo-segment-content"><i class="fa fa-venus segment-icon icon-female"></i> {{ __('female') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('mobile') }}</label>
                                    {!! Form::number('mobile', null, ['placeholder' => __('mobile'), 'min' => 1 , 'class' => 'form-control remove-number-increment', 'id' => 'edit_mobile']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-4">
                                    <label>{{ __('current_address') }} <span class="text-danger">*</span></label>
                                    {!! Form::textarea('current_address', null, ['required', 'placeholder' => __('current_address'), 'class' => 'form-control', 'rows' => 1, 'id'=>'edit-current-address']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-4">
                                    <label>{{ __('permanent_address') }} <span class="text-danger">*</span></label>
                                    {!! Form::textarea('permanent_address', null, ['required', 'placeholder' => __('permanent_address'), 'class' => 'form-control', 'rows' => 1, 'id'=>'edit-permanent-address']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('image') }}</label>
                                    <div class="d-flex align-items-center" style="gap: 6px;">
                                        <img src="" id="edit-student-image-tag" class="rounded border shadow-2xs" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0; background: #F1F5F9;" alt="" onerror="this.src='{{ asset('assets/images/faces/1.jpg') }}'"/>
                                        <input type="file" name="image" class="file-upload-default d-none" id="student_image_input" accept="image/*"/>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <input type="text" class="form-control file-upload-info" disabled placeholder="{{ __('image') }}" id="edit_image"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-sm btn-outline-secondary px-2" type="button" onclick="$('#student_image_input').click()"><i class="fa fa-folder-open"></i></button>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-1 d-flex align-items-end">
                                    <div class="custom-control custom-checkbox pb-1" title="{{ __('reset_password') }}">
                                        <input type="checkbox" class="custom-control-input" name="reset_password" value="1" id="reset_password_chk">
                                        <label class="custom-control-label small font-weight-bold text-dark cursor-pointer" for="reset_password_chk" style="white-space: nowrap;">
                                            <i class="fa fa-key text-warning mr-1"></i>{{ __('reset') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($extraFields))
                                <div class="row other-details mt-1">
                                    @foreach ($extraFields as $key => $data)
                                        @if($data->user_type == 1)
                                            @php $fieldName = str_replace(' ', '_', $data->name) @endphp
                                            {{ Form::hidden('extra_fields['.$key.'][id]', '', ['id' => $fieldName.'_id']) }}
                                            {{ Form::hidden('extra_fields['.$key.'][form_field_id]', $data->id) }}
                                            {{ Form::hidden('extra_fields['.$key.'][input_type]', $data->type) }}

                                            <div class='form-group col-sm-12 col-md-6 col-lg-3'>
                                                @if($data->type != 'radio' && $data->type != 'checkbox')
                                                    <label>{{$data->name}} @if($data->is_required)<span class="text-danger">*</span>@endif</label>
                                                @endif

                                                @if($data->type == 'text')
                                                    {{ Form::text('extra_fields['.$key.'][data]', '', ['class' => 'form-control text-fields', 'id' => $fieldName, 'placeholder' => $data->name, ($data->is_required == 1 ? 'required' : '')]) }}
                                                @elseif($data->type == 'number')
                                                    {{ Form::number('extra_fields['.$key.'][data]', '', ['min' => 0, 'class' => 'form-control number-fields', 'id' => $fieldName, 'placeholder' => $data->name, ($data->is_required == 1 ? 'required' : '')]) }}
                                                @elseif($data->type == 'dropdown')
                                                    @php $dropdownOptions = array_combine($data->default_values, $data->default_values); @endphp
                                                    {{ Form::select('extra_fields['.$key.'][data]',$dropdownOptions, null, ['id' => $fieldName, 'class' => 'form-control select-fields', ($data->is_required == 1 ? 'required' : ''), 'placeholder' => 'Select '.$data->name]) }}
                                                @elseif($data->type == 'radio')
                                                    <label class="d-block">{{$data->name}} @if($data->is_required)<span class="text-danger">*</span>@endif</label>
                                                    <div class="d-flex flex-wrap">
                                                        @foreach ($data->default_values as $keyRadio => $value)
                                                            <div class="form-check form-check-inline">
                                                                <label class="form-check-label">
                                                                    {{ Form::radio('extra_fields['.$key.'][data]', $value, null, ['id' => $fieldName.'_'.$keyRadio, 'class' => 'radio-fields', ($data->is_required == 1 ? 'required' : '')]) }}
                                                                    {{$value}}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif($data->type == 'checkbox')
                                                    <label class="d-block">{{$data->name}} @if($data->is_required)<span class="text-danger">*</span>@endif</label>
                                                    <div class="d-flex flex-wrap">
                                                        @foreach ($data->default_values as $chkKey => $value)
                                                            <div class="form-check form-check-inline">
                                                                <label class="form-check-label">
                                                                    {{ Form::checkbox('extra_fields['.$key.'][data][]', $value, null, ['id' => $fieldName.'_'.$chkKey, 'class' => 'form-check-input chkclass checkbox-fields', ($data->is_required == 1 ? 'required' : '')]) }}
                                                                    {{ $value }}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @elseif($data->type == 'textarea')
                                                    {{ Form::textarea('extra_fields['.$key.'][data]', '', ['placeholder' => $data->name, 'id' => $fieldName, 'class' => 'form-control textarea-fields', ($data->is_required ? 'required' : '') , 'rows' => 1]) }}
                                                @elseif($data->type == 'file')
                                                    <div class="input-group input-group-sm">
                                                        {{ Form::file('extra_fields['.$key.'][data]', ['class' => 'file-upload-default d-none', 'id' => $fieldName]) }}
                                                        {{ Form::text('', '', ['class' => 'form-control file-upload-info', 'disabled' => '', 'placeholder' => __('image')]) }}
                                                        <span class="input-group-append">
                                                            <button class="file-upload-browse btn btn-sm btn-outline-secondary" type="button" onclick="$('#{{$fieldName}}').click()">{{ __('upload') }}</button>
                                                        </span>
                                                    </div>
                                                    <div id="file_div_{{$fieldName}}" class="mt-1 d-none file-div">
                                                        <a href="" id="file_link_{{$fieldName}}" target="_blank" class="small text-primary">{{$data->name}}</a>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <!-- Section 2: Guardian Details -->
                            <div class="zolo-section-divider">
                                <i class="fa fa-users"></i> {{ __('Guardian Details') }}
                            </div>

                            <div class="row">
                                <div class="form-group col-sm-12 col-md-6 col-lg-5">
                                    <label>{{ __('guardian') . ' ' . __('email') }} <span class="text-danger">*</span></label>
                                    <select class="edit-guardian-search form-control" name="guardian_id"></select>
                                    <input type="hidden" id="edit_guardian_email" name="guardian_email">
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('guardian') . ' ' . __('mobile') }} <span class="text-danger">*</span></label>
                                    {!! Form::number('guardian_mobile', null, ['placeholder' => __('guardian') . ' ' . __('mobile'), 'class' => 'form-control remove-number-increment', 'min' => 1  ,'id' => 'edit_guardian_mobile']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-3">
                                    <label>{{ __('gender') }} <span class="text-danger">*</span></label>
                                    <div class="zolo-segmented-control" role="group" aria-label="Guardian Gender">
                                        <label class="zolo-segment-btn" for="edit-guardian-male">
                                            <input type="radio" name="guardian_gender" value="male" id="edit-guardian-male" class="zolo-segment-input" required>
                                            <span class="zolo-segment-content"><i class="fa fa-mars segment-icon icon-male"></i> {{ __('male') }}</span>
                                        </label>
                                        <label class="zolo-segment-btn" for="edit-guardian-female">
                                            <input type="radio" name="guardian_gender" value="female" id="edit-guardian-female" class="zolo-segment-input" required>
                                            <span class="zolo-segment-content"><i class="fa fa-venus segment-icon icon-female"></i> {{ __('female') }}</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-1 d-flex align-items-end">
                                    <div class="custom-control custom-checkbox pb-1" title="{{ __('reset_password') }}">
                                        <input type="checkbox" class="custom-control-input" name="parent_reset_password" value="1" id="parent_reset_pwd_chk">
                                        <label class="custom-control-label small font-weight-bold text-dark cursor-pointer" for="parent_reset_pwd_chk" style="white-space: nowrap;">
                                            <i class="fa fa-key text-warning mr-1"></i>{{ __('reset') }}
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-4">
                                    <label>{{ __('guardian') . ' ' . __('first_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_first_name', null, ['placeholder' => __('guardian') . ' ' . __('first_name'), 'class' => 'form-control', 'id' => 'edit_guardian_first_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-4">
                                    <label>{{ __('guardian') . ' ' . __('last_name') }} <span class="text-danger">*</span></label>
                                    {!! Form::text('guardian_last_name', null, ['placeholder' => __('guardian') . ' ' . __('last_name'), 'class' => 'form-control', 'id' => 'edit_guardian_last_name']) !!}
                                </div>

                                <div class="form-group col-sm-12 col-md-6 col-lg-4">
                                    <label>{{ __('image') }}</label>
                                    <div class="d-flex align-items-center" style="gap: 6px;">
                                        <img src="" id="edit-guardian-image-tag" class="rounded border shadow-2xs" style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0; background: #F1F5F9;" alt="" onerror="this.src='{{ asset('assets/images/faces/1.jpg') }}'"/>
                                        <input type="file" name="guardian_image" class="file-upload-default d-none" id="guardian_image_input" accept="image/*"/>
                                        <div class="input-group input-group-sm flex-grow-1">
                                            <input type="text" class="form-control file-upload-info" disabled placeholder="{{ __('image') }}" id="edit_guardian_image"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-sm btn-outline-secondary px-2" type="button" onclick="$('#guardian_image_input').click()"><i class="fa fa-folder-open"></i></button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2 px-3 d-flex justify-content-end bg-light" style="gap: 8px;">
                            <button type="button" class="btn btn-sm btn-secondary px-3" data-dismiss="modal">
                                <i class="fa fa-times mr-1"></i> {{ __('Cancel') }}
                            </button>
                            <button class="btn btn-sm btn-primary px-4 font-weight-bold" type="submit">
                                <i class="fa fa-check mr-1"></i> {{ __('submit') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection
@section('script')
    <script>
        let originalFormat = "{{ $schoolSettings['date_format'] }}"; // PHP value
        let userIds;
        $('.table-list-type').on('click', function (e) {
            let value = $(this).data('id');
            let ActiveLang = window.trans['Active'];
            let DeactiveLang = window.trans['Inactive'];
            if (value === "" || value === 0 || value == null) {
                $("#update-status").data("id")
                $('.update-status-btn-name').html(DeactiveLang);
            } else {
                $('.update-status-btn-name').html(ActiveLang);
            }
        })


        function updateUserStatus(tableId, buttonClass) {
            let selectedRows = $(tableId).bootstrapTable('getSelections');
            let selectedRowsValues = selectedRows.map(function (row) {
                return row.user_id;
            });
            userIds = JSON.stringify(selectedRowsValues);

            if (buttonClass != null) {
                if (selectedRowsValues.length) {
                    $(buttonClass).prop('disabled', false);
                } else {
                    $(buttonClass).prop('disabled', true);
                }
            }
        }

        $('#table_list').bootstrapTable({
            onCheck: function (row) {
                updateUserStatus("#table_list", '#update-status');
            },
            onUncheck: function (row) {
                updateUserStatus("#table_list", '#update-status');
            },
            onCheckAll: function (rows) {
                updateUserStatus("#table_list", '#update-status');
            },
            onUncheckAll: function (rows) {
                updateUserStatus("#table_list", '#update-status');
            }
        });
        $("#update-status").on('click', function (e) {
            Swal.fire({
                title: window.trans["Are you sure"],
                text: window.trans["Change Status For Selected Users"],
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: window.trans["Yes, Change it"],
                cancelButtonText: window.trans["Cancel"]
            }).then((result) => {
                if (result.isConfirmed) {
                    let url = baseUrl + '/students/change-status-bulk';
                    let data = new FormData();
                    data.append("ids", userIds)

                    function successCallback(response) {
                        $('#table_list').bootstrapTable('refresh');
                        $('#update-status').prop('disabled', true);
                        userIds = null;
                        showSuccessToast(response.message);
                    }

                    function errorCallback(response) {
                        showErrorToast(response.message);
                    }

                    ajaxRequest('POST', url, data, null, successCallback, errorCallback);
                }
            })
        })
    </script>
@endsection
