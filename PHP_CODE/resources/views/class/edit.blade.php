@extends('layouts.master')

@section('title')
    {{ __('Class')}}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                    <i class="fa fa-pencil-square-o"></i>
                </span>
                {{ __('manage') . ' ' . __('Class')}}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <h4 class="card-title text-dark font-weight-bold mb-0">
                                {{ __('edit') . ' ' . __('Class') }}
                            </h4>
                        </div>
                        <p class="card-description text-muted small mb-4">
                            {{ __('Update class details, building/block configuration, shift, and assigned sections.') }}
                        </p>

                        <form class="pt-2 edit-class-subject-validate-form" data-success-function="formSuccessFunction" data-pre-submit-function="classValidation" id="edit-form" action="{{ route('class.update',[$id]) }}" novalidate="novalidate">
                            <div class="modal-body p-0">
                                {{-- Medium Badge --}}
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark d-flex align-items-center mb-2">
                                        <i class="fa fa-language text-primary mr-2"></i>
                                        <span>{{ __('medium') }}</span>
                                    </label>
                                    <div class="d-inline-flex align-items-center px-3 py-2 rounded border bg-light">
                                        <i class="fa fa-book text-muted mr-2"></i>
                                        <span class="font-weight-bold text-dark">{{ $class->medium->name }}</span>
                                    </div>
                                    <input type="hidden" name="medium_id" value="{{ $class->medium_id }}">
                                </div>

                                <div class="row">
                                    {{-- Class Name --}}
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_name" class="font-weight-bold text-dark d-flex align-items-center mb-1">
                                                <i class="fa fa-graduation-cap text-primary mr-2"></i>
                                                <span>{{ __('name') }}</span>
                                                <span class="text-danger ml-1">*</span>
                                            </label>
                                            <input name="name" id="edit_name" type="text" placeholder="{{ __('name') }}" class="form-control" value="{{$class->name}}" required/>
                                        </div>
                                    </div>

                                    {{-- Building / Block Assignment --}}
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_block" class="font-weight-bold text-dark d-flex align-items-center mb-1">
                                                <i class="fa fa-building-o text-primary mr-2"></i>
                                                <span>{{ __('Building / Block') }}</span>
                                                <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
                                            </label>
                                            <input name="block" id="edit_block" type="text" list="available-blocks-datalist" value="{{ $default_block }}" placeholder="{{ __('e.g. Block C, Block B, Commerce Block, Science Wing') }}" class="form-control" autocomplete="off"/>
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
                                            </datalist>
                                            <small class="form-text text-muted mt-1">
                                                <i class="fa fa-info-circle mr-1"></i>
                                                {{ __('Default campus building/block for this class.') }}
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
                                                <option value="{{ $shift->id }}" {{ $shift->id == $class->shift_id ? 'selected' : '' }}>{{ $shift->name }}</option>
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
                                            placeholder="--- {{ __('No Stream') }} ---"
                                            selectClass="select2-dropdown"
                                        >
                                            @foreach ($streams as $stream)
                                                <option value="{{ $stream->id }}" {{ $stream->id == $class->stream_id ? 'selected' : '' }}>{{ $stream->name }}</option>
                                            @endforeach
                                        </x-dropdown>
                                    </div>
                                </div>

                                {{-- Sections Configuration --}}
                                <div class="form-group mb-4">
                                    <label class="font-weight-bold text-dark d-flex align-items-center mb-2">
                                        <i class="fa fa-th-large text-primary mr-2"></i>
                                        <span>{{ __('section') }}</span>
                                        <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
                                    </label>
                                    <p class="text-muted small mb-2">
                                        {{ __('Manage sections and their individual building/block location.') }}
                                    </p>
                                    <div class="d-flex flex-wrap align-items-start">
                                        @foreach ($sections as $section)
                                            @php
                                                $isAssigned = $class_section->contains($section->id);
                                                $sectionPivot = $isAssigned ? $class->sections->find($section->id)?->pivot : null;
                                                $sectionClassObj = $isAssigned && isset($class_sections_by_section[$section->id]) ? $class_sections_by_section[$section->id] : null;
                                                $assignedBlock = $sectionClassObj ? $sectionClassObj->block : '';
                                            @endphp
                                            <div class="zolo-section-card mr-3 mb-2 p-2 rounded border bg-white shadow-sm {{ $isAssigned ? 'active' : '' }}" style="min-width: 155px; transition: all 0.2s ease;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="custom-control custom-checkbox d-flex align-items-center">
                                                        <input type="checkbox" 
                                                               class="custom-control-input section-checkbox" 
                                                               id="edit_section_{{ $section->id }}" 
                                                               name="section_id[]" 
                                                               value="{{ $section->id }}" 
                                                               {{ $isAssigned ? 'checked' : '' }}
                                                               onchange="toggleSectionCard(this)">
                                                        <label class="custom-control-label font-weight-bold ml-1 mb-0" for="edit_section_{{ $section->id }}" style="cursor: pointer;">
                                                            {{ __('Section') }} {{ $section->name }}
                                                        </label>
                                                    </div>

                                                    @if($isAssigned && $sectionPivot)
                                                        <div class="d-flex align-items-center ml-2">
                                                            <a href="{{ route('class-section.edit', [$sectionPivot->id]) }}" class="text-primary mr-1" title="{{ __('Assign Class Teacher & Subject Teacher') }}">
                                                                <i class="fa fa-pencil"></i>
                                                            </a>
                                                            <a href="{{ route('class-section.destroy', [$sectionPivot->id]) }}" class="text-danger delete-class-section" title="{{ __('Delete') }}">
                                                                <i class="fa fa-times-circle"></i>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="section-block-input-wrap mt-2" style="{{ $isAssigned ? 'display: block;' : 'display: none;' }}">
                                                    <input type="text" 
                                                           name="section_block[{{ $section->id }}]" 
                                                           list="available-blocks-datalist" 
                                                           value="{{ $assignedBlock }}" 
                                                           class="form-control form-control-sm section-block-input" 
                                                           placeholder="{{ __('Block (Opt)') }}" 
                                                           style="font-size: 11px; height: 28px; padding: 2px 6px;">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <hr class="my-4">

                                {{-- Semesters Warning & Toggle --}}
                                <div class="form-group mb-4">
                                    @if (count($semesters))
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input include_semesters" id="edit_include_semesters" name="include_semesters" value="1" {{ $class->include_semesters ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold text-dark" for="edit_include_semesters" style="cursor: pointer;">
                                                <i class="fa fa-calendar-check-o mr-1 text-primary"></i> {{ __('Include Semesters') }}
                                            </label>
                                        </div>
                                    @endif
                                    
                                    <div class="alert alert-warning py-2 px-3 small mt-2 mb-0" style="font-size: 12px;">
                                        <i class="fa fa-exclamation-triangle mr-1"></i>
                                        <strong>{{ __("Note") }}:</strong> {{ __("By Changing this Semester setting, your existing data related to this class (Class Subject, Timetable, Lesson & Topic, Exam & Marks, Announcement) will be Auto Deleted.") }}
                                    </div>
                                </div>

                                <hr class="my-4">

                                {{-- Templated Buttons --}}
                                <div class="row">
                                    <div class="col-12 d-flex justify-content-end align-items-center">
                                        <x-button href="{{ route('class.index') }}" type="secondary" icon="fa fa-arrow-left" label="{{ __('Back to List') }}" class="mr-2" />
                                        <x-button buttonType="submit" id="create-btn" type="primary" icon="fa fa-check" label="{{ __('submit') }}" />
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function toggleSectionCard(checkbox) {
            var card = checkbox.closest('.zolo-section-card');
            if (!card) return;
            var blockWrap = card.querySelector('.section-block-input-wrap');
            if (checkbox.checked) {
                card.classList.add('active');
                if (blockWrap) blockWrap.style.display = 'block';
            } else {
                card.classList.remove('active');
                if (blockWrap) blockWrap.style.display = 'none';
            }
        }

        function formSuccessFunction() {
            setTimeout(() => {
                window.location.href = "{{route('class.index')}}"
            }, 1000);
        }
    </script>
@endsection
