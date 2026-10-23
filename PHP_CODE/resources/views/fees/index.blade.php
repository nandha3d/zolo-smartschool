@extends('layouts.master')

@section('title')
    {{ __('Manage Fees')}}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                    <i class="fa fa-money"></i>
                </span>
                {{ __('Manage Fees')}}
            </h3>
        </div>

        <div class="row">
            {{-- Create Fees Form Card --}}
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="card-title text-dark font-weight-bold mb-1">
                                    {{ __('Create Fees Structure') }}
                                </h4>
                                <p class="card-description text-muted small mb-0">
                                    {{ __('Configure academic fees, term schedules, quarterly or semi-annual installments, and optional facilities.') }}
                                </p>
                            </div>
                        </div>

                        <form id="create-form" class="create-form common-validation-rules"
                            action="{{ route('fees.store') }}" method="POST" novalidate="novalidate"
                            data-success-function="successFunction">

                            {{-- BOX 1: Basic Setup (Prefix & Classes) --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <h4 class="zolo-box-title">
                                        <i class="fa fa-file-text-o text-primary mr-2"></i>
                                        <span>{{ __('Basic Fee Setup') }}</span>
                                    </h4>
                                    <span class="badge badge-light border text-muted py-1 px-2 font-weight-normal" style="font-size: 11px;">
                                        {{ __('Required Information') }}
                                    </span>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="row">
                                        <div class="form-group col-sm-12 col-md-6">
                                            <label class="font-weight-bold text-dark mb-1">
                                                {{ __('Prefix Name') }} <span class="text-danger">*</span>
                                                <span class="fa fa-info-circle text-primary ml-1" data-toggle="tooltip" data-placement="right"
                                                    title="{{__('Fees names will be created based on the Classes Prefix appended before Class Name. e.g. Prefix Name - Class Name')}}"></span>
                                            </label>
                                            {!! Form::text('name', null, ['placeholder' => __('e.g. Academic Year 2026-27, Term Fee, Tuition'), 'class' => 'form-control', 'required']) !!}
                                        </div>

                                        <div class="form-group col-sm-12 col-md-6">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label for="class-id" class="font-weight-bold text-dark mb-0">
                                                    {{ __('Classes') }} <span class="text-danger">*</span>
                                                </label>
                                                <div class="custom-control custom-checkbox custom-control-inline mr-0">
                                                    <input type="checkbox" class="custom-control-input" id="select-all" value="1">
                                                    <label class="custom-control-label small font-weight-bold text-primary" for="select-all" style="cursor: pointer;">
                                                        {{ __('Select All Classes') }}
                                                    </label>
                                                </div>
                                            </div>
                                            <select name="class_id[]" id="class-id"
                                                class="class-id form-control select2-dropdown select2-hidden-accessible"
                                                tabindex="-1" aria-hidden="true" required multiple data-placeholder="{{ __('Choose target classes') }}">
                                                @foreach ($classes as $item)
                                                    <option value="{{ $item->id }}">{{ $item->full_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 2: Compulsory Fees (Table Box) --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <h4 class="zolo-box-title">
                                        <i class="fa fa-calculator text-primary mr-2"></i>
                                        <span>{{ __('Compulsory Fees') }}</span>
                                    </h4>
                                    <span class="badge badge-primary py-1 px-2 font-weight-bold" style="font-size: 11px;">
                                        {{ __('Mandatory for all students') }}
                                    </span>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="compulsory-fees-types">
                                        {{-- Table-like header --}}
                                        <div class="zolo-repeater-header rounded-top d-none d-md-flex">
                                            <div class="col-md-5 pl-2">{{ __('Fees Type') }} <span class="text-danger">*</span></div>
                                            <div class="col-md-5">{{ __('Amount') }} (₹) <span class="text-danger">*</span></div>
                                            <div class="col-md-2 text-center pr-2">{{ __('Action') }}</div>
                                        </div>

                                        <div data-repeater-list="compulsory_fees_type" class="zolo-repeater-table border-top-0">
                                            <div class="zolo-repeater-row" data-repeater-item>
                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Fees Type') }}</label>
                                                    <select name="fees_type_id" id="fees_type_id" class="form-control fees_type"
                                                        aria-label="Fees Type" required>
                                                        <option value="">{{ __('--- Select Fees Type ---')}}</option>
                                                        @foreach ($feesTypeData as $feesType)
                                                            <option value="{{ $feesType->id }}">{{ $feesType->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Amount (₹)') }}</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-light text-muted font-weight-bold">₹</span>
                                                        </div>
                                                        {!! Form::text('amount', null, ['class' => 'form-control amount font-weight-bold', 'placeholder' => '0.00', 'id' => 'amount', 'required' => true, 'min' => 0, "data-convert" => "number"]) !!}
                                                    </div>
                                                </div>

                                                <div class="col-md-2 col-12 text-center">
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm btn-icon remove-fees-type"
                                                        data-repeater-delete title="{{ __('Remove Row') }}">
                                                        <i class="fa fa-trash-o"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <button class="btn btn-outline-primary btn-sm font-weight-bold" type="button" data-repeater-create>
                                                <i class="fa fa-plus-circle mr-1"></i>
                                                {{ __('Add Fee Head') }}
                                            </button>
                                            <div class="bg-white px-3 py-1 rounded border shadow-sm text-dark font-weight-bold">
                                                <span class="text-muted small font-weight-normal mr-2">{{ __('Total Compulsory Amount') }}:</span>
                                                <span class="text-primary font-size-16" id="compulsory-subtotal-badge">₹ 0.00</span>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    {{-- Primary Due Date & Due Charges --}}
                                    <div class="row pt-2">
                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-calendar text-primary mr-1"></i>
                                                {{ __('due_date')}} <span class="text-danger">*</span>
                                            </label>
                                            {{ Form::text('due_date', null, ['id' => 'primary_due_date', 'class' => 'datepicker-popup-no-past form-control', 'placeholder' => __('due_date'), 'required', 'autocomplete' => 'off']) }}
                                            <small class="text-muted">{{ __('Base due date for full payment or Term 1') }}</small>
                                        </div>

                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-percent text-info mr-1"></i>
                                                {{ __('due_charges')}} <span class="text-danger">*</span>
                                                <span class="badge badge-light border text-muted py-0 px-1 ml-1" style="font-size: 11px;">{{ __('in_percentage') }}</span>
                                            </label>
                                            {{ Form::number('due_charges_percentage', null, ['id' => 'due_charges_percentage', 'class' => 'form-control', 'placeholder' => __('e.g. 5'), 'required', 'min' => 0]) }}
                                            <small class="text-muted">{{ __('Late penalty percentage if overdue') }}</small>
                                        </div>

                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-money text-success mr-1"></i>
                                                {{ __('due_charges')}} <span class="text-danger">*</span>
                                                <span class="badge badge-light border text-muted py-0 px-1 ml-1" style="font-size: 11px;">{{ __('Amount Fixed') }}</span>
                                            </label>
                                            {{ Form::number('due_charges_amount', null, ['id' => 'due_charges_amount', 'class' => 'form-control', 'placeholder' => __('e.g. 100'), 'required', 'min' => 0]) }}
                                            <small class="text-muted">{{ __('Fixed late penalty fee if overdue') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 3: Fees Installment & Term Structure (Table Box) --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <div class="d-flex align-items-center">
                                        <h4 class="zolo-box-title mb-0">
                                            <i class="fa fa-calendar-check-o text-primary mr-2"></i>
                                            <span>{{ __('Term Fees & Installment Schedules') }}</span>
                                        </h4>
                                        <span class="badge badge-info ml-2 font-weight-normal py-1 px-2" style="font-size: 11px;">
                                            {{ __('Configurable Structure') }}
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fa fa-info-circle mr-1"></i>
                                        {{ __('Supports 2 Terms, 3 Terms, Quarterly, or 50% Upfront + Monthly') }}
                                    </div>
                                </div>
                                <div class="zolo-box-body">
                                    {{-- Mode Selector (Templated Pills) --}}
                                    <div class="mb-4">
                                        <label class="font-weight-bold text-dark d-block mb-2">
                                            {{ __('Fee Collection Model') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="d-flex flex-wrap align-items-center">
                                            <label class="zolo-selection-pill active mr-3 mb-2" id="pill-opt-disable" style="cursor: pointer;">
                                                <input type="radio" name="include_fee_installments" class="fees-installment-toggle zolo-pill-input" value="0" checked style="position: absolute; opacity: 0; pointer-events: none;">
                                                <div class="pill-content d-inline-flex align-items-center py-2 px-3">
                                                    <span class="pill-indicator mr-2"><span class="radio-dot"></span></span>
                                                    <i class="fa fa-check-circle mr-2 text-muted"></i>
                                                    <div>
                                                        <span class="font-weight-bold d-block">{{ __('Single Full Payment') }}</span>
                                                        <small class="text-muted">{{ __('100% upfront on due date, no term installments') }}</small>
                                                    </div>
                                                </div>
                                            </label>
                                            <label class="zolo-selection-pill mr-3 mb-2" id="pill-opt-enable" style="cursor: pointer;">
                                                <input type="radio" name="include_fee_installments" class="fees-installment-toggle zolo-pill-input" value="1" style="position: absolute; opacity: 0; pointer-events: none;">
                                                <div class="pill-content d-inline-flex align-items-center py-2 px-3">
                                                    <span class="pill-indicator mr-2"><span class="radio-dot"></span></span>
                                                    <i class="fa fa-calendar mr-2 text-muted"></i>
                                                    <div>
                                                        <span class="font-weight-bold d-block">{{ __('Term / Installment Structure') }}</span>
                                                        <small class="text-muted">{{ __('Divide into 2 terms, 3 terms, quarterly, or upfront + monthly') }}</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- Repeater and Presets (Shown when enabled) --}}
                                    <div class="fees-installment-repeater" style="display: none">
                                        {{-- Term Fee Structure Presets Bar --}}
                                        <div class="bg-white p-3 rounded-lg border mb-3 shadow-sm">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="font-weight-bold text-dark mb-0">
                                                    <i class="fa fa-magic text-primary mr-1"></i>
                                                    {{ __('Term Fee Presets (1-Click Setup)') }}
                                                </h6>
                                                <span class="text-muted small">
                                                    {{ __('Click any structure to auto-generate term names, splits, and dates') }}
                                                </span>
                                            </div>

                                            <div class="d-flex flex-wrap align-items-center">
                                                {{-- Preset 1: 2 Terms (50% / 50%) --}}
                                                <div class="term-preset-card mr-2 mb-2" data-preset="bi-annual" title="{{ __('2 payments: 50% each, 6 months apart') }}">
                                                    <div class="preset-icon"><i class="fa fa-pie-chart"></i></div>
                                                    <div>
                                                        <div class="preset-title">{{ __('2 Terms (Half-Yearly)') }}</div>
                                                        <div class="preset-desc">{{ __('50% & 50% (2 times a year)') }}</div>
                                                    </div>
                                                </div>

                                                {{-- Preset 2: 3 Terms (Trimester) --}}
                                                <div class="term-preset-card mr-2 mb-2" data-preset="trimester" title="{{ __('3 terms: 33.3% each, 4 months apart') }}">
                                                    <div class="preset-icon"><i class="fa fa-th-large"></i></div>
                                                    <div>
                                                        <div class="preset-title">{{ __('3 Terms (Trimester)') }}</div>
                                                        <div class="preset-desc">{{ __('3 terms (every 4 months)') }}</div>
                                                    </div>
                                                </div>

                                                {{-- Preset 3: 4 Terms (Quarterly) --}}
                                                <div class="term-preset-card mr-2 mb-2" data-preset="quarterly" title="{{ __('4 terms: 25% each, every 3 months') }}">
                                                    <div class="preset-icon"><i class="fa fa-calendar"></i></div>
                                                    <div>
                                                        <div class="preset-title">{{ __('4 Terms (Quarterly)') }}</div>
                                                        <div class="preset-desc">{{ __('3 months once term (Q1-Q4)') }}</div>
                                                    </div>
                                                </div>

                                                {{-- Preset 4: 50% Upfront + Monthly --}}
                                                <div class="term-preset-card mr-2 mb-2" data-preset="upfront_monthly" title="{{ __('Half of fees at admission, remainder in monthly payments') }}">
                                                    <div class="preset-icon"><i class="fa fa-hand-holding-usd"></i></div>
                                                    <div>
                                                        <div class="preset-title">{{ __('50% Upfront + Monthly') }}</div>
                                                        <div class="preset-desc">{{ __('Half at admission, rest monthly') }}</div>
                                                    </div>
                                                </div>

                                                {{-- Preset 5: 10 Monthly Installments --}}
                                                <div class="term-preset-card mr-2 mb-2" data-preset="monthly" title="{{ __('10 equal monthly payments across school year') }}">
                                                    <div class="preset-icon"><i class="fa fa-clock-o"></i></div>
                                                    <div>
                                                        <div class="preset-title">{{ __('10 Monthly Installments') }}</div>
                                                        <div class="preset-desc">{{ __('10 monthly installments (10% each)') }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Live Balance & Allocation Bar --}}
                                        <div class="fee-balance-bar mb-3">
                                            <div class="d-flex align-items-center flex-wrap">
                                                <div class="mr-4 my-1">
                                                    <span class="text-muted small d-block font-weight-bold">{{ __('COMPULSORY TOTAL') }}</span>
                                                    <span class="font-weight-bold text-dark font-size-16" id="compulsory-total-text">₹ 0.00</span>
                                                </div>
                                                <div class="mr-4 my-1">
                                                    <span class="text-muted small d-block font-weight-bold">{{ __('INSTALLMENTS ALLOCATED') }}</span>
                                                    <span class="font-weight-bold text-primary font-size-16" id="installments-total-text">₹ 0.00</span>
                                                </div>
                                                <div class="my-1">
                                                    <span class="text-muted small d-block font-weight-bold">{{ __('ALLOCATION STATUS') }}</span>
                                                    <span id="balance-badge" class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 12px;">
                                                        <i class="fa fa-check-circle mr-1"></i> {{ __('100% Balanced') }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="my-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="btn-auto-balance" style="display: none;">
                                                    <i class="fa fa-balance-scale mr-1"></i> {{ __('Auto-Balance Remaining') }}
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Table Header for Installments --}}
                                        <div class="zolo-repeater-header rounded-top d-none d-xl-flex">
                                            <div class="col-xl-3 pl-2">{{ __('Term / Installment Name') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-2">{{ __('Amount') }} (₹) <span class="text-danger">*</span></div>
                                            <div class="col-xl-3">{{ __('Due Date') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-2">{{ __('Due Penalty Type') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-1">{{ __('Charges') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-1 text-center pr-2">{{ __('Action') }}</div>
                                        </div>

                                        {{-- Repeater Items Container --}}
                                        <div data-repeater-list="fees_installments" class="zolo-repeater-table border-top-0">
                                            <div data-repeater-item class="zolo-repeater-row row mx-0 py-2">
                                                <div class="form-group col-lg-12 col-xl-3 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Term / Installment Name') }}</label>
                                                    {{ Form::text('name', null, ['class' => 'form-control installment-name font-weight-bold', 'placeholder' => __('e.g. Term 1, Q1, Admission Fee'), 'required']) }}
                                                </div>

                                                <div class="form-group col-lg-12 col-xl-2 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Amount (₹)') }}</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-light text-muted font-weight-bold px-2">₹</span>
                                                        </div>
                                                        {{ Form::number('amount', null, ['class' => 'form-control installment-amount font-weight-bold', 'placeholder' => '0.00', 'required', 'min' => 0, 'step' => 'any', "data-convert" => "number"]) }}
                                                    </div>
                                                </div>

                                                <div class="form-group col-lg-12 col-xl-3 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Due Date') }}</label>
                                                    {{ Form::text('due_date', null, ['class' => 'datepicker-popup-no-past form-control installment-due-date', 'placeholder' => __('due_date'), 'autocomplete' => 'off', 'required']) }}
                                                </div>

                                                <div class="form-group col-lg-12 col-xl-2 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Due Penalty Type') }}</label>
                                                    <div class="d-flex align-items-center pt-2">
                                                        <div class="custom-control custom-radio custom-control-inline mr-2">
                                                            <input type="radio" id="due_charges_type_pct_{{ rand(1000, 9999) }}" name="due_charges_type" value="percentage" class="custom-control-input" checked required>
                                                            <label class="custom-control-label small font-weight-bold" for="due_charges_type_pct_{{ rand(1000, 9999) }}" style="cursor: pointer;">%</label>
                                                        </div>
                                                        <div class="custom-control custom-radio custom-control-inline mr-0">
                                                            <input type="radio" id="due_charges_type_fix_{{ rand(1000, 9999) }}" name="due_charges_type" value="fixed" class="custom-control-input" required>
                                                            <label class="custom-control-label small font-weight-bold" for="due_charges_type_fix_{{ rand(1000, 9999) }}" style="cursor: pointer;">₹</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group col-lg-12 col-xl-1 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Penalty Value') }}</label>
                                                    {!! Form::number("due_charges", null, ["class" => "installment-due-charges form-control px-2", "placeholder" => "0", "required" => true, "data-convert" => "number", "min" => 0]) !!}
                                                </div>

                                                <div class="form-group col-lg-12 col-xl-1 mb-0 px-2 text-center d-flex align-items-center justify-content-center">
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm btn-icon remove-installment-fee"
                                                        data-repeater-delete title="{{ __('Remove Installment') }}">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Add Installment Button --}}
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button id="add-installment" class="btn btn-outline-primary btn-sm font-weight-bold" type="button" data-repeater-create>
                                                <i class="fa fa-plus-circle mr-1"></i>
                                                {{ __('Add Custom Term Row') }}
                                            </button>
                                            <small class="text-muted font-italic">
                                                {{ __('All term installment amounts must sum to the total compulsory fee.') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 4: Optional Fees (Table Box) --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <div class="d-flex align-items-center">
                                        <h4 class="zolo-box-title">
                                            <i class="fa fa-tags text-primary mr-2"></i>
                                            <span>{{ __('Optional Fees') }}</span>
                                        </h4>
                                        <span class="badge badge-light border text-muted ml-2 font-weight-normal py-1 px-2" style="font-size: 11px;">
                                            {{ __('Optional Facility') }}
                                        </span>
                                    </div>
                                    <small class="text-danger font-weight-bold">
                                        * {{ __('Optional Fees do not support Due charges & Installment Facility') }}
                                    </small>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="optional-fees-types">
                                        <div class="zolo-repeater-header rounded-top d-none d-md-flex">
                                            <div class="col-md-5 pl-2">{{ __('Fees Type') }} <span class="text-danger">*</span></div>
                                            <div class="col-md-5">{{ __('Amount') }} (₹) <span class="text-danger">*</span></div>
                                            <div class="col-md-2 text-center pr-2">{{ __('Action') }}</div>
                                        </div>

                                        <div data-repeater-list="optional_fees_type" class="zolo-repeater-table border-top-0">
                                            <div class="zolo-repeater-row" data-repeater-item>
                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Fees Type') }}</label>
                                                    <select name="fees_type_id" id="fees_type_id" class="form-control fees_type"
                                                        aria-label="Fees Type" required>
                                                        <option value="">{{ __('--- Select Optional Fees Type ---')}}</option>
                                                        @foreach ($feesTypeData as $feesType)
                                                            <option value="{{ $feesType->id }}">{{ $feesType->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Amount (₹)') }}</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-light text-muted font-weight-bold">₹</span>
                                                        </div>
                                                        {!! Form::text('amount', null, ['class' => 'form-control amount font-weight-bold', 'placeholder' => '0.00', 'id' => 'amount', 'required' => true, 'min' => 0, "data-convert" => "number"]) !!}
                                                    </div>
                                                </div>

                                                <div class="col-md-2 col-12 text-center">
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm btn-icon remove-fees-type"
                                                        data-repeater-delete title="{{ __('Remove Row') }}">
                                                        <i class="fa fa-trash-o"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-primary btn-sm font-weight-bold" type="button" data-repeater-create>
                                            <i class="fa fa-plus-circle mr-1"></i>
                                            {{ __('Add Optional Fee Head') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            {{-- Templated Buttons Row --}}
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

            {{-- List Fees Table Card --}}
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title text-dark font-weight-bold mb-0">
                                {{ __('List Fees')}}
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

                        <div class="row align-items-center mb-3" id="toolbar">
                            <div class="form-group col-sm-12 col-md-3 mb-0">
                                <label for="filter-session-year-id" class="filter-menu font-weight-bold mr-2 mb-1">{{__("session_year")}}:</label>
                                {!! Form::select('session_year_id', $sessionYear, $defaultSessionYear->id, ['class' => 'form-control', 'id' => 'filter_session_year_id']) !!}
                            </div>

                            <div class="form-group col-sm-12 col-md-3 mb-0">
                                <label for="filter-medium_id" class="filter-menu font-weight-bold mr-2 mb-1">{{__("medium")}}:</label>
                                {!! Form::select('medium_id', $mediums, null, ['class' => 'form-control', 'id' => 'filter_medium_id', 'placeholder' => __('all')]) !!}
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table table-striped' id='table_list' data-toggle="table"
                                    data-url="{{ route('fees.show', 1) }}" data-click-to-select="true"
                                    data-side-pagination="server" data-pagination="true"
                                    data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                    data-show-columns="true" data-show-refresh="true" data-trim-on-search="false"
                                    data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                    data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                    data-query-params="feesQueryParams" data-escape="true" data-escape-title="false">
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{__('id')}}</th>
                                        <th scope="col" data-field="no">{{__('no.')}}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{__('name')}}</th>
                                        <th scope="col" data-field="class.full_name" data-visible="false">{{__('Class')}}</th>
                                        <th scope="col" data-field="format_due_date" data-sortable="true">{{__('due_date')}}</th>
                                        <th scope="col" data-field="due_charges" data-align="center">{{__('due_charges')}} (%)</th>
                                        <th scope="col" data-field="installments" data-formatter="feesInstallmentFormatter">{{__('Fees Installment')}}</th>
                                        <th scope="col" data-field="fees_type" data-align="left" data-formatter="feesTypeFormatter">{{ __('Fees') }} {{__('type')}}</th>
                                        <th scope="col" data-field="compulsory_fees" data-align="center">{{ __('Compulsory Amount')}}</th>
                                        <th scope="col" data-field="total_fees" data-align="center">{{ __('Total Amount')}}</th>
                                        <th scope="col" data-events="feesEvents" data-field="operate" data-escape="false">{{__('action')}}</th>
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
@endsection

@section('js')
    <script>
        // Ensure at least 1 compulsory row on load
        if ($('.compulsory-fees-types [data-repeater-item]').length === 0) {
            $('.compulsory-fees-types').find('[data-repeater-create]').click();
        }

        function successFunction() {
            $('.compulsory-fees-types [data-repeater-item]').slice(1).remove();
            $('.fees-installment-repeater [data-repeater-item]').remove();
            $('.fees-installment-repeater').hide();
            $('#pill-opt-disable').click();
            updateBalanceDisplay();
        }

        $(document).ready(function () {
            // Function to calculate compulsory fees total ONLY (excluding optional fees)
            function calculateCompulsoryTotal() {
                var total = 0;
                $('.compulsory-fees-types .amount').each(function () {
                    var val = parseFloat($(this).val()) || 0;
                    total += val;
                });
                return Math.round(total * 100) / 100;
            }

            // Function to calculate current sum of all installment amounts
            function calculateInstallmentsTotal() {
                var total = 0;
                $('.fees-installment-repeater [data-repeater-item]').each(function () {
                    var val = parseFloat($(this).find('.installment-amount').val()) || 0;
                    total += val;
                });
                return Math.round(total * 100) / 100;
            }

            // Function to update the Live Balance & Allocation Display
            function updateBalanceDisplay() {
                var compTotal = calculateCompulsoryTotal();
                var instTotal = calculateInstallmentsTotal();
                var diff = Math.round((compTotal - instTotal) * 100) / 100;

                $('#compulsory-subtotal-badge').text('₹ ' + compTotal.toFixed(2));
                $('#compulsory-total-text').text('₹ ' + compTotal.toFixed(2));
                $('#installments-total-text').text('₹ ' + instTotal.toFixed(2));

                var $badge = $('#balance-badge');
                var $btnAuto = $('#btn-auto-balance');

                if (Math.abs(diff) < 0.01) {
                    $badge.removeClass('badge-warning badge-danger').addClass('badge-success')
                          .html('<i class="fa fa-check-circle mr-1"></i> 100% Balanced');
                    $btnAuto.hide();
                } else if (diff > 0) {
                    $badge.removeClass('badge-success badge-danger').addClass('badge-warning text-dark')
                          .html('<i class="fa fa-exclamation-triangle mr-1"></i> ₹ ' + diff.toFixed(2) + ' Unallocated');
                    $btnAuto.show().html('<i class="fa fa-magic mr-1"></i> Auto-Balance Remaining (+₹ ' + diff.toFixed(2) + ')');
                } else {
                    $badge.removeClass('badge-success badge-warning').addClass('badge-danger')
                          .html('<i class="fa fa-times-circle mr-1"></i> ₹ ' + Math.abs(diff).toFixed(2) + ' Exceeded');
                    $btnAuto.show().html('<i class="fa fa-magic mr-1"></i> Auto-Balance Overcharge (-₹ ' + Math.abs(diff).toFixed(2) + ')');
                }
            }

            // Auto-balance button: adjusts the last installment so sum matches compulsory total exactly
            $('#btn-auto-balance').on('click', function () {
                var compTotal = calculateCompulsoryTotal();
                var $rows = $('.fees-installment-repeater [data-repeater-item]');
                if ($rows.length === 0) return;

                var otherSum = 0;
                $rows.slice(0, -1).each(function () {
                    otherSum += parseFloat($(this).find('.installment-amount').val()) || 0;
                });

                var remaining = Math.max(0, Math.round((compTotal - otherSum) * 100) / 100);
                $rows.last().find('.installment-amount').val(remaining.toFixed(2));
                updateBalanceDisplay();
            });

            // Date helpers
            function parseBaseDate() {
                var raw = $('#primary_due_date').val();
                if (!raw) return new Date();
                var parts = raw.split('-');
                if (parts.length === 3) {
                    if (parts[0].length === 4) { // YYYY-MM-DD
                        return new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
                    } else { // DD-MM-YYYY
                        return new Date(parts[2], parseInt(parts[1], 10) - 1, parts[0]);
                    }
                }
                return new Date();
            }

            function formatDDMMYYYY(d) {
                var dd = ('0' + d.getDate()).slice(-2);
                var mm = ('0' + (d.getMonth() + 1)).slice(-2);
                var yyyy = d.getFullYear();
                return dd + '-' + mm + '-' + yyyy;
            }

            function addMonths(date, m) {
                var d = new Date(date.getTime());
                d.setMonth(d.getMonth() + m);
                return d;
            }

            // Clear and populate installments
            function setInstallmentItems(items) {
                $('.fees-installment-repeater [data-repeater-item]').remove();

                items.forEach(function (item) {
                    $('#add-installment').trigger('click');
                    var $row = $('.fees-installment-repeater [data-repeater-item]').last();
                    $row.find('.installment-name').val(item.name);
                    $row.find('.installment-amount').val(item.amount.toFixed(2));
                    $row.find('.installment-due-date').val(item.due_date);
                    $row.find('.installment-due-charges').val(item.due_charges || 0);
                    if (item.due_charges_type === 'fixed') {
                        $row.find('input[value="fixed"]').prop('checked', true);
                    } else {
                        $row.find('input[value="percentage"]').prop('checked', true);
                    }
                });

                updateBalanceDisplay();
            }

            // Handle Term Presets Click
            $('.term-preset-card').on('click', function () {
                var preset = $(this).data('preset');
                $('.term-preset-card').removeClass('active');
                $(this).addClass('active');

                var total = calculateCompulsoryTotal();
                if (total <= 0) {
                    Swal.fire({
                        icon: 'info',
                        title: "{{ __('Enter Compulsory Fee Amount First') }}",
                        text: "{{ __('Please fill in at least one compulsory fee amount above so we can calculate term splits.') }}",
                        timer: 3500
                    });
                    return;
                }

                var baseDate = parseBaseDate();
                var items = [];

                if (preset === 'bi-annual') {
                    // 2 Terms (50% / 50%) - 2 times a year (6 months apart)
                    var term1 = Math.floor((total / 2) * 100) / 100;
                    var term2 = Math.round((total - term1) * 100) / 100;
                    items = [
                        { name: "{{ __('Term 1 (50%)') }}", amount: term1, due_date: formatDDMMYYYY(baseDate), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 2 (50%)') }}", amount: term2, due_date: formatDDMMYYYY(addMonths(baseDate, 6)), due_charges_type: 'percentage', due_charges: 5 }
                    ];
                } else if (preset === 'trimester') {
                    // 3 Terms (Every 4 Months)
                    var t1 = Math.floor((total / 3) * 100) / 100;
                    var t2 = t1;
                    var t3 = Math.round((total - (t1 + t2)) * 100) / 100;
                    items = [
                        { name: "{{ __('Term 1 (First Term)') }}", amount: t1, due_date: formatDDMMYYYY(baseDate), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 2 (Second Term)') }}", amount: t2, due_date: formatDDMMYYYY(addMonths(baseDate, 4)), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 3 (Third Term)') }}", amount: t3, due_date: formatDDMMYYYY(addMonths(baseDate, 8)), due_charges_type: 'percentage', due_charges: 5 }
                    ];
                } else if (preset === 'quarterly') {
                    // 4 Terms (Quarterly - Every 3 Months)
                    var q1 = Math.floor((total / 4) * 100) / 100;
                    var q2 = q1;
                    var q3 = q1;
                    var q4 = Math.round((total - (q1 * 3)) * 100) / 100;
                    items = [
                        { name: "{{ __('Term 1 (Q1)') }}", amount: q1, due_date: formatDDMMYYYY(baseDate), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 2 (Q2)') }}", amount: q2, due_date: formatDDMMYYYY(addMonths(baseDate, 3)), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 3 (Q3)') }}", amount: q3, due_date: formatDDMMYYYY(addMonths(baseDate, 6)), due_charges_type: 'percentage', due_charges: 5 },
                        { name: "{{ __('Term 4 (Q4)') }}", amount: q4, due_date: formatDDMMYYYY(addMonths(baseDate, 9)), due_charges_type: 'percentage', due_charges: 5 }
                    ];
                } else if (preset === 'upfront_monthly') {
                    // 50% Upfront Admission + 5 Monthly Installments (10% each)
                    var upfront = Math.floor((total * 0.50) * 100) / 100;
                    var remaining = Math.round((total - upfront) * 100) / 100;
                    var monthCount = 5;
                    var monthlyAmt = Math.floor((remaining / monthCount) * 100) / 100;

                    items.push({
                        name: "{{ __('Initial Admission & Term 1 (50%)') }}",
                        amount: upfront,
                        due_date: formatDDMMYYYY(baseDate),
                        due_charges_type: 'percentage',
                        due_charges: 5
                    });

                    var runningSum = upfront;
                    for (var i = 1; i <= monthCount; i++) {
                        var amt = (i === monthCount) ? Math.round((total - runningSum) * 100) / 100 : monthlyAmt;
                        runningSum += amt;
                        items.push({
                            name: "{{ __('Month') }} " + i + " {{ __('Installment') }}",
                            amount: amt,
                            due_date: formatDDMMYYYY(addMonths(baseDate, i)),
                            due_charges_type: 'percentage',
                            due_charges: 5
                        });
                    }
                } else if (preset === 'monthly') {
                    // 10 Monthly Installments (10% each)
                    var mCount = 10;
                    var mBase = Math.floor((total / mCount) * 100) / 100;
                    var mSum = 0;
                    for (var j = 1; j <= mCount; j++) {
                        var mAmt = (j === mCount) ? Math.round((total - mSum) * 100) / 100 : mBase;
                        mSum += mAmt;
                        items.push({
                            name: "{{ __('Month') }} " + j + " {{ __('Fee') }}",
                            amount: mAmt,
                            due_date: formatDDMMYYYY(addMonths(baseDate, j - 1)),
                            due_charges_type: 'percentage',
                            due_charges: 5
                        });
                    }
                }

                setInstallmentItems(items);
            });

            // Toggle Installment Pills & Visibility
            $('.fees-installment-toggle').on('change', function () {
                var isEnabled = $(this).val() == '1';
                if (isEnabled) {
                    $('#pill-opt-enable').addClass('active');
                    $('#pill-opt-disable').removeClass('active');
                    $('.fees-installment-repeater').slideDown(300);

                    // If empty, auto-click first preset or add 1 row
                    if ($('.fees-installment-repeater [data-repeater-item]').length === 0) {
                        var total = calculateCompulsoryTotal();
                        if (total > 0) {
                            $('.term-preset-card[data-preset="bi-annual"]').trigger('click');
                        } else {
                            $('#add-installment').trigger('click');
                        }
                    }
                } else {
                    $('#pill-opt-disable').addClass('active');
                    $('#pill-opt-enable').removeClass('active');
                    $('.fees-installment-repeater').slideUp(200);
                }
                updateBalanceDisplay();
            });

            // Make clicking on the entire pill trigger change
            $('#pill-opt-disable').on('click', function () {
                $(this).find('input').prop('checked', true).trigger('change');
            });
            $('#pill-opt-enable').on('click', function () {
                $(this).find('input').prop('checked', true).trigger('change');
            });

            // Live updates on amount inputs
            $(document).on('input', '.compulsory-fees-types .amount', function () {
                updateBalanceDisplay();
            });

            $(document).on('input', '.fees-installment-repeater .installment-amount', function () {
                updateBalanceDisplay();
            });

            $(document).on('click', '#add-installment', function () {
                setTimeout(updateBalanceDisplay, 100);
            });

            $(document).on('click', '.remove-installment-fee, [data-repeater-delete]', function () {
                setTimeout(updateBalanceDisplay, 300);
            });

            // Initial calculation
            updateBalanceDisplay();
        });
    </script>
@endsection