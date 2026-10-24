@extends('layouts.master')

@section('title')
    {{ __('general_settings') }}
@endsection


@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('general_settings') }}
            </h3>
        </div>
        <div class="row grid-margin">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form class="create-form-without-reset" action="{{ route('school-settings.store') }}" method="POST" novalidate="novalidate" enctype="multipart/form-data" data-success-function="formSuccessFunction">
                            @csrf
                            <div class="border border-secondary rounded-lg mb-2">
                                <div class="row my-4 mx-1">
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="school_name">{{ __('school_name') }} <span class="text-danger">*</span></label>
                                        <input name="school_name" id="school_name" value="{{ $settings['school_name'] ?? '' }}" type="text" maxlength="73" required placeholder="{{ __('school_name') }}" class="form-control"/>
                                    </div>
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="school_email">{{ __('school_email') }} <span class="text-danger">*</span></label>
                                        <input name="school_email" id="school_email" value="{{ $settings['school_email'] ?? '' }}" type="email" required placeholder="{{ __('school_email') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="school_code">{{ __('school_code') }}</label>
                                        <input name="school_code" id="school_code" value="{{ Auth::user()->school->code }}" type="text" disabled placeholder="{{ __('school_code') }}" class="form-control"/>
                                    </div>

                                    <div class="form-group col-md-6 col-sm-12">
                                        <label for="school_phone">{{ __('school_phone') }} <span class="text-danger">*</span></label>
                                        <input name="school_phone" id="school_phone" value="{{ $settings['school_phone'] ?? '' }}" type="number" required placeholder="{{ __('school_phone') }}" class="form-control remove-number-increment"/>
                                    </div>
                                    <div class="form-group col-md-6 col-sm-12">
                                        <label for="school_tagline">{{ __('school_tagline') }} <span class="text-danger">*</span></label>
                                        <textarea name="school_tagline" id="school_tagline" required placeholder="{{ __('school_tagline') }}" class="form-control">{{ $settings['school_tagline'] ?? '' }}</textarea>
                                    </div>
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="school_address">{{ __('school_address') }} <span class="text-danger">*</span></label>
                                        <textarea name="school_address" id="school_address" required placeholder="{{ __('school_address') }}" class="form-control">{{ $settings['school_address'] ?? '' }}</textarea>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="date_format">{{ __('date_format') }}</label>
                                        <select name="date_format" id="date_format" required class="form-control">
                                            @foreach ($getDateFormat as $key => $dateformat)
                                                <option value="{{ $key }}"{{ isset($settings['date_format']) && $settings['date_format'] == $key ? 'selected' : '' }}>{{ $dateformat }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="time_format">{{ __('time_format') }}</label>
                                        <select name="time_format" id="time_format" required class="form-control">
                                            @foreach ($getTimeFormat as $key => $timeFormat)
                                                <option value="{{ $key }}"{{ isset($settings['time_format']) && $settings['time_format'] == $key ? 'selected' : '' }}>{{ $timeFormat }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-12 col-lg-12 col-sm-12">
                                        <label for="theme_color">{{ __('School Theme Color') }}</label>
                                        <input name="theme_color" id="theme_color" value="{{ $settings['theme_color'] ?? $systemSettings['theme_color'] ?? '#22577A' }}" type="hidden"/>
                                        <div class="zolo-theme-color-grid" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                                            @php
                                            $presets = [
                                                ['#22577A', 'Deep Teal'],
                                                ['#1A535C', 'Dark Cyan'],
                                                ['#2E4057', 'Navy Slate'],
                                                ['#1B4332', 'Forest Green'],
                                                ['#3D405B', 'Charcoal Indigo'],
                                                ['#2D3142', 'Gunmetal Blue'],
                                                ['#264653', 'Dark Sea Green'],
                                                ['#4A5568', 'Warm Slate'],
                                                ['#285E61', 'Teal'],
                                                ['#553C9A', 'Royal Purple'],
                                                ['#0F172A', 'Midnight Navy (Dark)'],
                                                ['#1E293B', 'Dark Slate (Dark)'],
                                                ['#18181B', 'Jet Black (Dark)'],
                                                ['#171923', 'Obsidian (Dark)'],
                                                ['#1A202C', 'Dark Charcoal (Dark)'],
                                                ['#312E81', 'Deep Indigo (Dark)'],
                                                ['#701A75', 'Dark Magenta (Dark)'],
                                                ['#7C2D12', 'Burnt Sienna (Dark)'],
                                                ['#92400E', 'Deep Amber (Dark)'],
                                                ['#065F46', 'Emerald Dark (Dark)'],
                                            ];
                                            $activeColor = $settings['theme_color'] ?? $systemSettings['theme_color'] ?? '#22577A';
                                            @endphp
                                            @foreach ($presets as $preset)
                                                <button type="button"
                                                    class="zolo-color-swatch{{ $activeColor === $preset[0] ? ' active' : '' }}"
                                                    data-color="{{ $preset[0] }}"
                                                    title="{{ $preset[1] }} ({{ $preset[0] }})"
                                                    style="width:38px;height:38px;border-radius:8px;border:2px solid transparent;background:{{ $preset[0] }};cursor:pointer;transition:all 0.15s ease;position:relative;display:inline-flex;align-items:center;justify-content:center;"
                                                    onclick="document.getElementById('theme_color').value=this.dataset.color;
                                                             document.getElementById('custom_theme_color').value=this.dataset.color;
                                                             document.querySelectorAll('.zolo-color-swatch').forEach(s=>s.classList.remove('active'));
                                                             this.classList.add('active');">
                                                    <i class="fa fa-check" style="color:#fff;font-size:13px;opacity:{{ $activeColor === $preset[0] ? '1' : '0' }};transition:opacity 0.15s;"></i>
                                                </button>
                                            @endforeach
                                            {{-- Custom Color Picker --}}
                                            <div style="position:relative;width:38px;height:38px;">
                                                <input type="color" id="custom_theme_color" value="{{ $activeColor }}"
                                                    style="position:absolute;inset:0;width:100%;height:100%;border:2px dashed #CBD5E1;border-radius:8px;padding:2px;cursor:pointer;background:#F8FAFC;"
                                                    title="Custom Color"
                                                    oninput="document.getElementById('theme_color').value=this.value;
                                                             document.querySelectorAll('.zolo-color-swatch').forEach(s=>s.classList.remove('active'));">
                                            </div>
                                        </div>
                                        <style>
                                            .zolo-color-swatch:hover { transform: scale(1.12); box-shadow: 0 2px 8px rgba(0,0,0,0.22); }
                                            .zolo-color-swatch.active { border-color: #FFFFFF !important; box-shadow: 0 0 0 2px var(--zolo-ink, #0F172A), 0 2px 8px rgba(0,0,0,0.25) !important; transform: scale(1.08); }
                                            .zolo-color-swatch.active .fa-check { opacity: 1 !important; }
                                        </style>
                                    </div>
                                </div>

                                <div class="row my-4 mx-1">
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="favicon">{{ __('favicon') }} <span class="text-danger">*</span> <small class="text-muted">(32x32 pixels)</small></label>
                                        <input type="file" name="favicon" id="favicon-input" class="file-upload-default" accept="image/*"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" id="favicon" class="form-control file-upload-info" disabled="" placeholder="{{ __('favicon') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <img id="favicon-preview" height="32px" width="32px"
                                                 src='{{ !empty($settings['favicon']) ? url($settings['favicon']) : asset('assets/no_image_available.jpg') }}' 
                                                 alt="Favicon" class="">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="horizontal_logo">{{ __('horizontal_logo') }} <span class="text-danger">*</span> <small class="text-muted">(250x50 pixels)</small></label>
                                        <input type="file" name="horizontal_logo" id="horizontal-logo-input" class="file-upload-default" accept="image/*"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" id="horizontal_logo" class="form-control file-upload-info" disabled="" placeholder="{{ __('horizontal_logo') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <img id="horizontal-logo-preview" height="60px" width="250px"
                                                 src='{{ !empty($settings['horizontal_logo']) ? url($settings['horizontal_logo']) : asset('assets/no_image_available.jpg') }}' 
                                                 alt="Horizontal Logo" class="">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group col-md-6 col-lg-6 col-xl-4 col-sm-12">
                                        <label for="vertical_logo">{{ __('vertical_logo') }} <span class="text-danger">*</span> <small class="text-muted">(100x100 pixels)</small></label>
                                        <input type="file" name="vertical_logo" id="vertical-logo-input" class="file-upload-default" accept="image/*"/>
                                        <div class="input-group col-xs-12">
                                            <input type="text" class="form-control file-upload-info" id="vertical_logo" disabled="" placeholder="{{ __('vertical_logo') }}"/>
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-theme" type="button">{{ __('upload') }}</button>
                                            </span>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <img id="vertical-logo-preview" height="100px" width="100px"
                                                 src='{{ !empty($settings['vertical_logo']) ? url($settings['vertical_logo']) : asset('assets/no_image_available.jpg') }}' 
                                                 alt="Vertical Logo" class="">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-12 col-sm-12 mt-3">
                                        <label for="school_google_map_link">{{ __('google_map_link')}} <span class="text-danger">*</span><span class="text-small text-info">({{ __('html_code') }})</span></label>
                                        <div class="input-group mb-3 w-100 d-flex flex-column">
                                            <input type="text" class="form-control w-100" required name="google_map_link" placeholder="{{ __('google_map_link') }}" value="{{ $settings['google_map_link'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="fees_remainder_duration">{{ __('fees_remainder_duration')}}  <span class="text-danger">*</span> <span class="text-small text-info">{{ __('reminder_days_before_due') }}</span></label>
                                        <div class="input-group mb-3 d-flex flex-column">
                                            <input type="number" class="form-control w-100" required name="fees_remainder_duration" placeholder="{{ __('fees_remainder_duration') }}" value="{{ $settings['fees_remainder_duration'] ?? '' }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border border-secondary rounded-lg my-4 mx-1">
                                <div class="col-md-12 mt-3"><h4>{{__("Domain Settings")}}</h4></div>
                                <div class="col-12 mb-3">
                                    <hr class="mt-0">
                                </div> 
                                <div class="row my-4 mx-1">  
                                    <div class="form-group col-sm-12 col-md-4">
                                        <label>{{ __('domain').' '. __('type') }} <span class="text-danger">*</span></label><br>
                                        <div class="d-flex">
                                            <div class="form-check form-check-inline">
                                                <label class="form-check-label">
                                                    {!! Form::radio('domain_type', 'default', false, ['class' => 'default' , ($domain_type ==  "default") ? "checked" : "" ]) !!}{{ __('default') }}
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <label class="form-check-label">
                                                    {!! Form::radio('domain_type', 'custom', false, ['class' => 'custom', ($domain_type ==  "custom") ? "checked" : "" ]) !!}{{ __('custom') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-4 defaultDomain" style="display: none">
                                        <label for="school_domain">{{ __('default_domain')}}</label>
                                        <div class="input-group mb-3">
                                                <input type="text" class="form-control domain-pattern" name="domain" placeholder="{{ __('domain') }}" aria-label="Recipient's username" aria-describedby="basic-addon2" disabled value="{{ ($domain_type ==  "default" && $settings['domain']) ? $settings['domain'] : "" }}">
                                            <div class="input-group-append">
                                                <span class="input-group-text text-body" id="basic-addon2">.{{ $baseUrlWithoutScheme }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group col-sm-12 col-md-4 customDomain" style="display: none">
                                        <label for="school_domain">{{ __('custom_domain')}}</label>
                                        <div class="input-group mb-3">
                                                <input type="text" class="form-control domain-pattern" name="domain" placeholder="{{ __('domain') }}" aria-label="Recipient's username" aria-describedby="basic-addon2" disabled value="{{ ($domain_type ==  "custom" && $settings['domain']) ? $settings['domain'] : "" }}">
                                        </div>
                                    </div>
                                    @if (!config('app.demo_mode'))
                                        <div class="form-group col-sm-12 col-md-4 serverinfo" style="display: none">
                                            <label for="serinfo">{{ __('server_info')}}</label>
                                            <div class="input-group mb-3">
                                                    <input type="text" class="form-control" name="server_ip" placeholder="{{ __('domain') }}" aria-describedby="basic-addon2" disabled value="{{ $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'; }}">
                                            </div>
                                        </div>
                                    @endif
                                    <div class="mx-4 text-justify text-uppercase">
                                        <small
                                            class="text-danger">{{ __('Note : If You are using Custom Domain then you have add a dns entry with pointing the server ip address.') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="border border-secondary rounded-lg my-4 mx-1 p-3">
                                <div class="col-md-12 mt-1"><h4>{{__("Roll Number Settings & Formatting")}}</h4></div>
                                <div class="col-12 mb-3">
                                    <hr class="mt-0">
                                </div>
                                <div class="row px-2">
                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="roll_number_prefix">{{__("Roll Number Prefix (Optional)")}}</label>
                                        <input type="text" id="roll_number_prefix" name="roll_number_prefix" value="{{ $settings['roll_number_prefix'] ?? '' }}" class="form-control" placeholder="e.g. CL{class}{section} or R-">
                                        <small class="text-muted">{{ __('Leave blank or use CL{class}{section} for class-specific prefixes (e.g. CL1A, CL2A). Supports {class}, {section}, {year}.') }}</small>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="roll_number_digits">{{__("Digit Length (Padding)")}}</label>
                                        @php $currentDigits = $settings['roll_number_digits'] ?? '0'; @endphp
                                        <select name="roll_number_digits" id="roll_number_digits" class="form-control">
                                            <option value="0" {{ $currentDigits == '0' ? 'selected' : '' }}>{{ __('Auto / No Padding (1, 2, 3...)') }}</option>
                                            <option value="2" {{ $currentDigits == '2' ? 'selected' : '' }}>{{ __('2 Digits (01, 02, 03...)') }}</option>
                                            <option value="3" {{ $currentDigits == '3' ? 'selected' : '' }}>{{ __('3 Digits (001, 002, 003...)') }}</option>
                                            <option value="4" {{ $currentDigits == '4' ? 'selected' : '' }}>{{ __('4 Digits (0001, 0002...)') }}</option>
                                            <option value="5" {{ $currentDigits == '5' ? 'selected' : '' }}>{{ __('5 Digits (00001...)') }}</option>
                                        </select>
                                        <small class="text-muted">{{ __('Pads numbers with leading zeros.') }}</small>
                                    </div>

                                    <div class="form-group col-md-4 col-sm-12">
                                        <label for="roll_number_start">{{__("Starting Number")}}</label>
                                        <input type="number" id="roll_number_start" name="roll_number_start" value="{{ $settings['roll_number_start'] ?? '1' }}" min="1" class="form-control" placeholder="1">
                                        <small class="text-muted">{{ __('Sequence begins from this number.') }}</small>
                                    </div>
                                </div>

                                <div class="row px-2">
                                    <div class="form-group col-md-12 col-sm-12">
                                        <label for="roll-number-order">{{__("Roll Number Sorting")}}</label>
                                        <input type="hidden" id="roll-number-sort-column" name="roll_number_sort_column" value="{{ $settings['roll_number_sort_column'] ?? "" }}">
                                        <input type="hidden" id="roll-number-sort-order" name="roll_number_sort_order" value="{{ $settings['roll_number_sort_order'] ?? "" }}">
                                        @php $currentSortOrder = ($settings['roll_number_sort_column'] ?? '') . ',' . ($settings['roll_number_sort_order'] ?? ''); @endphp
                                        <select name="" id="roll-number-order" class="form-control">
                                            <option value="" hidden="">-- {{__('Select')}} --</option>
                                            <option value="first_name,asc" {{ $currentSortOrder == 'first_name,asc' ? 'selected' : '' }}>{{__("First Name - Ascending")}}</option>
                                            <option value="first_name,desc" {{ $currentSortOrder == 'first_name,desc' ? 'selected' : '' }}>{{__("First Name - Descending")}}</option>
                                            <option value="last_name,asc" {{ $currentSortOrder == 'last_name,asc' ? 'selected' : '' }}>{{__("Last Name - Ascending")}}</option>
                                            <option value="last_name,desc" {{ $currentSortOrder == 'last_name,desc' ? 'selected' : '' }}>{{__("Last Name - Descending")}}</option>
                                        </select>

                                        <div class="form-check mt-3">
                                            <label class="form-check-label"> <input type="checkbox" class="form-check-input" name="change_roll_number" id="change-roll-ckh-settings" value="1"> {{ __('Regenerate Roll Numbers for All Classes using this format') }} <i class="input-helper"></i></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

{{--                            <div class="border border-secondary rounded-lg mb-3">--}}
{{--                                <h3 class="col-12 page-title mt-3 ">--}}
{{--                                    {{ __('Currency Settings') }}--}}
{{--                                </h3>--}}
{{--                                <div class="row my-4 mx-1">--}}
{{--                                    <div class="form-group col-md-3 col-sm-12">--}}
{{--                                        <label for="currency_code">{{__('currency_code')}} <span class="text-danger">*</span></label>--}}
{{--                                        <input name="currency_code" id="currency_code" value="{{ $settings['currency_code'] ?? ''}}" type="text" placeholder="{{__('currency_code')}}" class="form-control" required/>--}}
{{--                                    </div>--}}
{{--                                    <div class="form-group col-md-3 col-sm-12">--}}
{{--                                        <label for="currency_symbol">{{__('currency_symbol')}} <span class="text-danger">*</span></label>--}}
{{--                                        <input name="currency_symbol" id="currency_symbol" value="{{$settings['currency_symbol'] ??  ''}}" type="text" placeholder="{{__('currency_symbol')}}" class="form-control" required/>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}

                            {{-- <input class="btn btn-theme" type="submit" value="{{ __('submit') }}"> --}}
                            <input class="btn btn-theme float-right ml-3" id="create-btn" type="submit" value={{ __('submit') }}>
                            <input class="btn btn-secondary float-right" type="reset" value={{ __('reset') }}>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
<script>
    function formSuccessFunction(response) {
        setTimeout(() => {
            window.location.reload();
        }, 4000);
    }

    $(document).ready(function () {
        // Domain settings toggle functionality
        function toggleFields() {
            if ($('.default').is(':checked')) {
                $('.defaultDomain').show().find('input').prop('disabled', false);
                $('.customDomain').hide().find('input').prop('disabled', true);
                $('.serverinfo').hide().find('input');
            } else if ($('.custom').is(':checked')) {
                $('.customDomain').show().find('input').prop('disabled', false);
                $('.serverinfo').show().find('input');
                $('.defaultDomain').hide().find('input').prop('disabled', true);
            }
        }  
        $("input[name='domain_type']").on('change', toggleFields);
        toggleFields();

        // Image preview functionality
        $('#favicon-input').on('change', function() {
            previewImage(this, '#favicon-preview');
        });

        $('#horizontal-logo-input').on('change', function() {
            previewImage(this, '#horizontal-logo-preview');
        });

        $('#vertical-logo-input').on('change', function() {
            previewImage(this, '#vertical-logo-preview');
        });

        function previewImage(input, previewSelector) {
            if (input.files && input.files[0]) {
                var file = input.files[0];
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    $(previewSelector).attr('src', e.target.result);
                    
                    // Update the file name in the input field
                    $(input).closest('.form-group').find('.file-upload-info').val(file.name);
                }
                reader.readAsDataURL(file);
            }
        }
    });
</script>
@endsection
