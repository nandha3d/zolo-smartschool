@extends('layouts.master')

@section('title')
    {{ __('timetable') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('create') . ' ' . __('timetable') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="card-title mb-1">
                                    <i class="fa fa-clock-o text-primary mr-1"></i> {{ __('Timetable Operating Hours & Default Duration') }}
                                </h4>
                                <p class="text-muted small mb-0">{{ __('Configure school operating hours and default timeslot duration. To generate or view timetables for each class, use the Auto-Generate buttons in the table below.') }}</p>
                            </div>
                        </div>
                        <form class="edit-form edit-form-without-reset timetable-settings-form" action="{{ route('timetable.settings') }}" method="POST">
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="starting_time">{{ __('Starting Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_start_time" id="starting_time" class="form-control" value="{{ $timetableData['timetable_start_time'] ?? "09:00:00"}}" required/>
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="ending_time">{{ __('Ending Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_end_time" id="ending_time" class="form-control" value="{{ $timetableData['timetable_end_time'] ?? "18:00:00"}}" required/>
                                </div>
                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="duration">{{ __('Timeslot Duration') }} <small>({{__('in Minutes')}})</small><span class="text-danger">*</span></label>
                                    <input type="number" name="timetable_duration" id="duration" class="form-control" min="1" max="180" value="{{ $timetableData['timetable_duration'] ?? 45 }}" required/>
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <button type="submit" id="save_settings" class="btn btn-theme">
                                    <i class="fa fa-save mr-1"></i> {{__('Save Timetable Settings')}}
                                </button>
                            </div>
                        </form>
                        <hr class="my-4">
                        <div class="row">
                            <div class="row w-100 align-items-center" id="toolbar">
                                <div class="form-group col-sm-12 col-md-4 mb-0">
                                    <label class="filter-menu" for="">{{ __('medium') }}</label>
                                    {!! Form::select('medium_id', $mediums, null, ['class' => 'form-control','id' => 'filter_medium_id', 'placeholder' => __('select_medium')]) !!}
                                </div>
                                <div class="col-sm-12 col-md-8 text-right mt-3 mt-md-0">
                                    <button type="button" class="btn btn-gradient-primary" data-toggle="modal" data-target="#chooseClassForAutoGenerateModal">
                                        <i class="fa fa-magic mr-1"></i> {{ __('Auto-Generate Timetable') }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list'
                                       data-toggle="table" data-url="{{ route('timetable.show',[1]) }}"
                                       data-click-to-select="true" data-side-pagination="server"
                                       data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                                       data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true"
                                       data-fixed-columns="false" data-fixed-number="2"
                                       data-fixed-right-number="1" data-trim-on-search="false"
                                       data-mobile-responsive="true" data-sort-name="id"
                                       data-query-params="timetableQueryParams" data-sort-order="desc"
                                       data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                       data-export-options='{ "fileName": "timetable-list-<?= date(' d-m-y') ?>" }'>
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="full_name">{{ __('Class Section') }}</th>
                                        <th scope="col" data-field="Monday" data-formatter="timetableDayFormatter">{{ __('Monday') }}</th>
                                        <th scope="col" data-field="Tuesday" data-formatter="timetableDayFormatter">{{ __('Tuesday') }}</th>
                                        <th scope="col" data-field="Wednesday" data-formatter="timetableDayFormatter">{{ __('Wednesday') }}</th>
                                        <th scope="col" data-field="Thursday" data-formatter="timetableDayFormatter">{{ __('Thursday') }}</th>
                                        <th scope="col" data-field="Friday" data-formatter="timetableDayFormatter">{{ __('Friday') }}</th>
                                        <th scope="col" data-field="Saturday" data-formatter="timetableDayFormatter">{{ __('Saturday') }}</th>
                                        <th scope="col" data-field="Sunday" data-formatter="timetableDayFormatter">{{ __('Sunday') }}</th>
                                        <th scope="col" data-field="operate" data-escape="false">{{ __('action') }}</th>
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

    <!-- Modal to Select Class for Auto-Generation -->
    <div class="modal fade" id="chooseClassForAutoGenerateModal" tabindex="-1" role="dialog" aria-labelledby="chooseClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark" id="chooseClassModalLabel">
                        <i class="fa fa-magic text-primary mr-1"></i> {{ __('Auto-Generate Timetable') }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        {{ __('Select a class section below to open its Intelligent Auto-Generator wizard. The system will build a conflict-free schedule, balancing teacher workloads and preventing clashes.') }}
                    </p>
                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Select Class Section') }} <span class="text-danger">*</span></label>
                        <select id="select_class_section_for_gen" class="form-control" style="height: 45px;">
                            <option value="">-- {{ __('Choose Class & Section') }} --</option>
                            @if(!empty($classSections))
                                @foreach($classSections as $cs)
                                    <option value="{{ $cs->id }}">{{ $cs->full_name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-gradient-primary" onclick="launchAutoGeneratorForSelected()">
                        <i class="fa fa-arrow-right mr-1"></i> {{ __('Launch Auto-Generator') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    function launchAutoGeneratorForSelected() {
        var id = $('#select_class_section_for_gen').val();
        if (!id) {
            alert("{{ __('Please select a class section.') }}");
            return;
        }
        window.location.href = "{{ url('timetable') }}/" + id + "/edit?autogen=1";
    }
</script>
@endsection
