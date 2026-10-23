@extends('layouts.master')

@section('title')
    {{ __('Class') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                    <i class="fa fa-graduation-cap"></i>
                </span>
                {{ __('manage') . ' ' . __('Class') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <h4 class="card-title text-dark font-weight-bold mb-0">
                                {{ __('create') . ' ' . __('Class') }}
                            </h4>
                        </div>
                        <p class="card-description text-muted small mb-4">
                            {{ __('Configure class name, medium, sections, and building/block assignment for your campus.') }}
                        </p>

                        <form class="pt-2 class-create-form" id="create-form" action="{{ route('class.store') }}" method="POST" data-pre-submit-function="classValidation" data-success-function="successFunction" novalidate="novalidate">
                            {{-- Medium Selection (Templated Pills) --}}
                            <div class="form-group mb-4">
                                <label class="font-weight-bold text-dark d-flex align-items-center mb-2">
                                    <i class="fa fa-language text-primary mr-2"></i>
                                    <span>{{ __('medium') }}</span>
                                    <span class="text-danger ml-1">*</span>
                                </label>
                                <div class="d-flex flex-wrap align-items-center">
                                    @foreach ($mediums as $medium)
                                        <x-selection-pill 
                                            type="radio" 
                                            name="medium_id" 
                                            id="medium_{{ $medium->id }}" 
                                            value="{{ $medium->id }}" 
                                            label="{{ $medium->name }}" 
                                            icon="fa fa-book"
                                            required="true"
                                            size="md"
                                            class="mr-2 mb-2"
                                        />
                                    @endforeach
                                </div>
                            </div>

                            <div class="row">
                                {{-- Class Name --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="font-weight-bold text-dark d-flex align-items-center mb-1">
                                            <i class="fa fa-graduation-cap text-primary mr-2"></i>
                                            <span>{{ __('name') }}</span>
                                            <span class="text-danger ml-1">*</span>
                                        </label>
                                        <input name="name" id="name" type="text" placeholder="{{ __('e.g. Grade 1, Class 10, Standard 12') }}" class="form-control" required="required"/>
                                    </div>
                                </div>

                                {{-- Building / Block Assignment --}}
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="block" class="font-weight-bold text-dark d-flex align-items-center mb-1">
                                            <i class="fa fa-building-o text-primary mr-2"></i>
                                            <span>{{ __('Building / Block') }}</span>
                                            <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
                                        </label>
                                        <input name="block" id="block" type="text" list="available-blocks-datalist" placeholder="{{ __('e.g. Block C, Block B, Commerce Block, Science Wing') }}" class="form-control" autocomplete="off"/>
                                        <datalist id="available-blocks-datalist">
                                            @if(isset($availableBlocks))
                                                @foreach($availableBlocks as $blk)
                                                    <option value="{{ $blk }}">{{ $blk }}</option>
                                                @endforeach
                                            @endif
                                            <option value="Block A">Block A</option>
                                            <option value="Block B">Block B</option>
                                            <option value="Block C">Block C</option>
                                            <option value="Block D">Block D</option>
                                            <option value="Commerce Block">Commerce Block</option>
                                            <option value="Science Block">Science Block</option>
                                            <option value="Primary Wing">Primary Wing</option>
                                        </datalist>
                                        <small class="form-text text-muted mt-1">
                                            <i class="fa fa-info-circle mr-1"></i>
                                            {{ __('Applies as the campus block (e.g. Block C for English Medium, Block B for Tamil Medium). Can also be customized per section below.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                {{-- Shift Dropdown (Templated) --}}
                                <div class="col-md-6">
                                    <x-dropdown 
                                        name="shift_id" 
                                        id="shift_id" 
                                        label="{{ __('Shift') }}" 
                                        icon="fa fa-clock-o" 
                                        optional="true" 
                                        placeholder="--- {{ __('Select Shift') }} ---"
                                        selectClass="select2-dropdown"
                                    >
                                        @foreach($shifts as $shift)
                                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                                        @endforeach
                                    </x-dropdown>
                                </div>

                                {{-- Stream Dropdown (Templated) --}}
                                <div class="col-md-6">
                                    <x-dropdown 
                                        name="stream_id" 
                                        id="stream_id" 
                                        label="{{ __('Stream') }}" 
                                        icon="fa fa-code-fork" 
                                        optional="true" 
                                        multiple="true" 
                                        selectClass="stream_id select2-dropdown"
                                        data-placeholder=" --- {{ __('Select Stream') }} --- "
                                    >
                                        @foreach ($streams as $stream)
                                            <option value="{{ $stream->id }}">{{ $stream->name }}</option>
                                        @endforeach
                                    </x-dropdown>
                                </div>
                            </div>

                            {{-- Default Sections Selection (When no stream selected) --}}
                            <div class="form-group mb-4" id="default-section-div">
                                <label class="font-weight-bold text-dark d-flex align-items-center mb-2">
                                    <i class="fa fa-th-large text-primary mr-2"></i>
                                    <span>{{ __('section') }}</span>
                                    <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
                                </label>
                                <p class="text-muted small mb-2">
                                    {{ __('Click sections to activate them. You can optionally set a specific block override for each section.') }}
                                </p>
                                <div class="d-flex flex-wrap align-items-start">
                                    @foreach ($sections as $section)
                                        <div class="zolo-section-card mr-3 mb-2 p-2 rounded border bg-white shadow-sm" style="min-width: 135px; transition: all 0.2s ease;">
                                            <div class="custom-control custom-checkbox d-flex align-items-center">
                                                <input type="checkbox" 
                                                       class="custom-control-input section-checkbox" 
                                                       id="section_0_{{ $section->id }}" 
                                                       name="section_id[0][]" 
                                                       value="{{ $section->id }}"
                                                       onchange="toggleSectionCard(this)">
                                                <label class="custom-control-label font-weight-bold ml-1 mb-0" for="section_0_{{ $section->id }}" style="cursor: pointer;">
                                                    {{ __('Section') }} {{ $section->name }}
                                                </label>
                                            </div>
                                            <div class="section-block-input-wrap mt-2" style="display: none;">
                                                <input type="text" 
                                                       name="section_block[0][{{ $section->id }}]" 
                                                       list="available-blocks-datalist" 
                                                       class="form-control form-control-sm section-block-input" 
                                                       placeholder="{{ __('Block (Opt)') }}" 
                                                       style="font-size: 11px; height: 28px; padding: 2px 6px;">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if (count($semesters) > 0)
                                    <div class="mt-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input include_semesters[0][]" id="include_semesters_0" name="include_semesters[0]" value="1">
                                            <label class="custom-control-label text-muted font-weight-bold" for="include_semesters_0" style="cursor: pointer;">
                                                <i class="fa fa-calendar-check-o mr-1"></i> {{ __('Include Semesters') }}
                                            </label>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Stream-Wise Sections Selection (Revealed when streams selected) --}}
                            <div class="form-group mb-4" id="stream-wise-section-div" style="display: none;">
                                @foreach ($streams as $stream)
                                    <div id="{{ preg_replace('/\s+/', '-', trim($stream->name)) }}-section-div" class="stream-divs mb-3 p-3 rounded border bg-light" style="display: none;">
                                        <label class="font-weight-bold text-dark d-flex align-items-center mb-2">
                                            <i class="fa fa-code-fork text-primary mr-2"></i>
                                            <span>{{ __('section') . ' (' . $stream->name . ')' }}</span>
                                            <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
                                        </label>
                                        <div class="d-flex flex-wrap align-items-start">
                                            @foreach ($sections as $section)
                                                <div class="zolo-section-card mr-3 mb-2 p-2 rounded border bg-white shadow-sm" style="min-width: 135px; transition: all 0.2s ease;">
                                                    <div class="custom-control custom-checkbox d-flex align-items-center">
                                                        <input type="checkbox" 
                                                               class="custom-control-input section-checkbox" 
                                                               id="section_{{ $stream->id }}_{{ $section->id }}" 
                                                               name="section_id[{{ $stream->id }}][]" 
                                                               value="{{ $section->id }}"
                                                               onchange="toggleSectionCard(this)">
                                                        <label class="custom-control-label font-weight-bold ml-1 mb-0" for="section_{{ $stream->id }}_{{ $section->id }}" style="cursor: pointer;">
                                                            {{ __('Section') }} {{ $section->name }}
                                                        </label>
                                                    </div>
                                                    <div class="section-block-input-wrap mt-2" style="display: none;">
                                                        <input type="text" 
                                                               name="section_block[{{ $stream->id }}][{{ $section->id }}]" 
                                                               list="available-blocks-datalist" 
                                                               class="form-control form-control-sm section-block-input" 
                                                               placeholder="{{ __('Block (Opt)') }}" 
                                                               style="font-size: 11px; height: 28px; padding: 2px 6px;">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if (count($semesters) > 0)
                                            <div class="mt-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input include_semesters" id="include_semesters_{{ $stream->id }}" name="include_semesters[{{ $stream->id }}]" value="1">
                                                    <label class="custom-control-label text-muted font-weight-bold" for="include_semesters_{{ $stream->id }}" style="cursor: pointer;">
                                                        <i class="fa fa-calendar-check-o mr-1"></i> {{ __('Include Semesters') }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <hr class="my-4">

                            {{-- Templated Buttons --}}
                            <div class="row">
                                <div class="col-12 d-flex justify-content-end align-items-center">
                                    <x-button buttonType="reset" type="secondary" icon="fa fa-refresh" label="{{ __('reset') }}" class="mr-2" />
                                    <x-button buttonType="submit" id="create-btn" type="primary" icon="fa fa-check" label="{{ __('submit') }}" />
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Class List Table --}}
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title text-dark font-weight-bold mb-0">
                                {{ __('list') . ' ' . __('Class') }}
                            </h4>
                            <div class="btn-group btn-group-sm">
                                <a href="#" class="btn btn-outline-primary active table-list-type" data-id="0">
                                    <i class="fa fa-list mr-1"></i> {{ __('all') }}
                                </a>
                                <a href="#" class="btn btn-outline-secondary table-list-type" data-id="1">
                                    <i class="fa fa-trash mr-1"></i> {{ __('Trashed') }}
                                </a>
                            </div>
                        </div>

                        <div id="toolbar" class="d-flex align-items-center">
                            <label for="filter_medium_id" class="filter-menu font-weight-bold mr-2 mb-0">{{ __('Medium') }}:</label>
                            <select name="medium_id" id="filter_medium_id" class="form-control" style="width: 180px;">
                                <option value="">{{ __('all') }}</option>
                                @foreach ($mediums as $medium)
                                    <option value="{{ $medium->id }}">{{ $medium->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <table aria-describedby="mydesc" class='table' id='table_list' data-toggle="table"
                               data-url="{{ route('class.show',[1]) }}" data-click-to-select="true" data-side-pagination="server"
                               data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                               data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                               data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                               data-mobile-responsive="true" data-sort-name="id" data-toolbar="#toolbar" data-sort-order="desc"
                               data-maintain-selected="true" data-export-data-type='all'
                               data-export-options='{ "fileName": "class-list-<?= date('d-m-y') ?>" ,"ignoreColumn":["operate"]}'
                               data-show-export="true" data-query-params="classQueryParams"
                               data-escape="true">
                            <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="false" data-visible="false">{{ __('id') }}</th>
                                <th scope="col" data-field="no">{{ __('no.') }}</th>
                                <th scope="col" data-field="full_name" data-sortable="false">{{ __('name') }}</th>
                                <th scope="col" data-field="shift.name">{{ __('Shift') }}</th>
                                <th scope="col" data-field="include_semesters" data-formatter="yesAndNoStatusFormatter">{{ __('Semester') }}</th>
                                <th scope="col" data-field="section_names">{{ __('section') }}</th>
                                <th scope="col" data-field="blocks" data-formatter="classBlockFormatter" data-escape="false">{{ __('Building / Block') }}</th>
                                <th scope="col" data-field="created_at"  data-sortable="false" data-visible="false">{{ __('created_at') }}</th>
                                <th scope="col" data-field="updated_at"  data-sortable="false" data-visible="false">{{ __('updated_at') }}</th>
                                <th scope="col" data-field="operate" data-escape="false">{{ __('action') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function toggleSectionCard(checkbox) {
            var card = checkbox.closest('.zolo-section-card');
            if (!card) return;
            var blockWrap = card.querySelector('.section-block-input-wrap');
            if (checkbox.checked) {
                card.classList.add('active');
                if (blockWrap) {
                    blockWrap.style.display = 'block';
                    var input = blockWrap.querySelector('.section-block-input');
                    var classBlock = document.getElementById('block');
                    if (input && classBlock && classBlock.value && !input.value) {
                        input.placeholder = classBlock.value;
                    }
                }
            } else {
                card.classList.remove('active');
                if (blockWrap) blockWrap.style.display = 'none';
            }
        }

        // When the general class block is typed, update section block input placeholders
        document.getElementById('block')?.addEventListener('input', function() {
            var val = this.value;
            document.querySelectorAll('.section-block-input').forEach(function(inp) {
                if (!inp.value) {
                    inp.placeholder = val || "{{ __('Block (Opt)') }}";
                }
            });
        });

        // Form reset handler to uncheck cards
        document.getElementById('create-form')?.addEventListener('reset', function() {
            setTimeout(function() {
                document.querySelectorAll('.zolo-section-card').forEach(function(card) {
                    card.classList.remove('active');
                    var blockWrap = card.querySelector('.section-block-input-wrap');
                    if (blockWrap) blockWrap.style.display = 'none';
                });
                document.querySelectorAll('.zolo-selection-pill').forEach(function(pill) {
                    var r = pill.querySelector('input');
                    if (r && r.checked) pill.classList.add('active');
                    else pill.classList.remove('active');
                });
            }, 50);
        });

        function successFunction() {
            $('#default-section-div').show();
            $('#stream-wise-section-div').hide();
            document.querySelectorAll('.zolo-section-card').forEach(function(card) {
                card.classList.remove('active');
                var blockWrap = card.querySelector('.section-block-input-wrap');
                if (blockWrap) blockWrap.style.display = 'none';
            });
        }
    </script>
@endsection
