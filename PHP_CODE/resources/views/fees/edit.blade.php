@extends('layouts.master')

@section('title')
    {{ __('Edit Fees')}}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                    <i class="fa fa-pencil-square-o"></i>
                </span>
                {{ __('Edit Fees')}}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card search-container">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title text-dark font-weight-bold mb-0">
                                {{ __('Fees Structure') }}: <span class="text-primary">{{ $fees->name }}</span>
                            </h4>
                            <x-button href="{{ route('fees.index') }}" type="secondary" size="sm" icon="fa fa-arrow-left" label="{{ __('back') }}" />
                        </div>

                        @if($fees->fees_paid_count > 0)
                            <div class="col-12 alert alert-warning border shadow-sm mb-4">
                                <i class="fa fa-exclamation-triangle mr-2"></i>
                                {{__("Certain fee modifications are restricted because one or more students have already made payments.")}}
                            </div>
                        @endif

                        <form id="feesForm" class="common-validation" action="{{ route('fees.update', $fees->id) }}"
                            method="POST" novalidate="novalidate">
                            @csrf
                            <input type="hidden" name="_method" value="PUT" />

                            {{-- BOX 1: Class and Name --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <h4 class="zolo-box-title">
                                        <i class="fa fa-graduation-cap text-primary mr-2"></i>
                                        <span>{{ __('Fees')}}: {{ $fees->class->full_name }}</span>
                                    </h4>
                                    <span class="badge badge-light border text-muted py-1 px-2 font-weight-normal" style="font-size: 11px;">
                                        {{ __('Target Class') }}
                                    </span>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="row">
                                        <div class="form-group col-sm-12 col-md-6">
                                            <label class="font-weight-bold text-dark mb-1">{{ __('Name') }} <span class="text-danger">*</span></label>
                                            {!! Form::text('name', $fees->name, ['placeholder' => __('Name'), 'class' => 'form-control font-weight-bold', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 2: Compulsory Fees --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <h4 class="zolo-box-title">
                                        <i class="fa fa-calculator text-primary mr-2"></i>
                                        <span>{{ __('Compulsory Fees') }}</span>
                                    </h4>
                                    <span class="badge badge-primary py-1 px-2 font-weight-bold" style="font-size: 11px;">
                                        {{ __('Mandatory') }}
                                    </span>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="compulsory-fees-types">
                                        <div class="zolo-repeater-header rounded-top d-none d-md-flex">
                                            <div class="col-md-5 pl-2">{{ __('Fees Type') }} <span class="text-danger">*</span></div>
                                            <div class="col-md-5">{{ __('Amount') }} (₹) <span class="text-danger">*</span></div>
                                            <div class="col-md-2 text-center pr-2">{{ __('Action') }}</div>
                                        </div>

                                        <div data-repeater-list="compulsory_fees_type" class="zolo-repeater-table border-top-0">
                                            <div class="zolo-repeater-row" data-repeater-item>
                                                <input type="hidden" name="id" class="fees_class_type_id" />
                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Fees Type') }}</label>
                                                    <select name="fees_type_id" id="fees_type_id" class="form-control fees_type"
                                                        aria-label="Fees Type" required>
                                                        <option value="" hidden="">{{ __('Select Fees Type')}}</option>
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
                                                        data-repeater-delete title="{{ __('Remove') }}">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <button class="btn btn-outline-primary btn-sm add-fees-type font-weight-bold" type="button" data-repeater-create>
                                                <i class="fa fa-plus-circle mr-1"></i>
                                                {{__('Add Fee Head')}}
                                            </button>
                                            <div class="bg-white px-3 py-1 rounded border shadow-sm text-dark font-weight-bold">
                                                <span class="text-muted small font-weight-normal mr-2">{{ __('Total Compulsory Amount') }}:</span>
                                                <span class="text-primary font-size-16" id="compulsory-subtotal-badge">₹ 0.00</span>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    <div class="row pt-2">
                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-calendar text-primary mr-1"></i>
                                                {{ __('due_date')}} <span class="text-danger">*</span>
                                            </label>
                                            {{ Form::text('due_date', $fees->due_date, ['id' => 'due_date', 'class' => 'datepicker-popup form-control', 'placeholder' => __('due_date'), 'required', 'autocomplete' => 'off']) }}
                                        </div>
                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-percent text-info mr-1"></i>
                                                {{ __('due_charges')}} <span class="text-danger">*</span>
                                                <span class="badge badge-light border text-muted py-0 px-1 ml-1" style="font-size: 11px;">{{__('in_percentage')}}</span>
                                            </label>
                                            {{ Form::number('due_charges_percentage', $fees->due_charges, ['id' => 'due_charges_percentage', 'class' => 'form-control', 'placeholder' => __('due_charges'), 'required', 'min' => 0]) }}
                                        </div>
                                        <div class="form-group col-sm-12 col-md-4">
                                            <label class="font-weight-bold text-dark mb-1">
                                                <i class="fa fa-money text-success mr-1"></i>
                                                {{ __('due_charges')}} <span class="text-danger">*</span>
                                                <span class="badge badge-light border text-muted py-0 px-1 ml-1" style="font-size: 11px;">{{__('Amount Fixed')}}</span>
                                            </label>
                                            {{ Form::number('due_charges_amount', $fees->due_charges_amount, ['id' => 'due_charges_amount', 'class' => 'form-control', 'placeholder' => __('due_charges'), 'required', 'min' => 0]) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 3: Fees Installment & Term Structure --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <div class="d-flex align-items-center">
                                        <h4 class="zolo-box-title">
                                            <i class="fa fa-calendar-check-o text-primary mr-2"></i>
                                            <span>{{ __('Term Fees & Installments')}}</span>
                                        </h4>
                                        <span class="badge badge-info ml-2 font-weight-normal py-1 px-2" style="font-size: 11px;">
                                            {{ __('Configurable') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="zolo-box-body">
                                    <div class="mb-4">
                                        <label class="font-weight-bold text-dark d-block mb-2">
                                            {{ __('Payment Frequency') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="d-flex flex-wrap align-items-center">
                                            <label class="zolo-selection-pill {{ $fees->include_fee_installments == 0 ? 'active' : '' }} mr-3 mb-2" id="pill-opt-disable" style="cursor: pointer;">
                                                <input type="radio" name="include_fee_installments" id="disable-installment" class="fees-installment-toggle zolo-pill-input" value="0" {{ $fees->include_fee_installments == 0 ? 'checked' : '' }} style="position: absolute; opacity: 0; pointer-events: none;">
                                                <div class="pill-content d-inline-flex align-items-center py-2 px-3">
                                                    <span class="pill-indicator mr-2"><span class="radio-dot"></span></span>
                                                    <i class="fa fa-check-circle mr-2 text-muted"></i>
                                                    <div>
                                                        <span class="font-weight-bold d-block">{{ __('Single Full Payment') }}</span>
                                                        <small class="text-muted">{{ __('100% upfront on due date, no term installments') }}</small>
                                                    </div>
                                                </div>
                                            </label>
                                            <label class="zolo-selection-pill {{ $fees->include_fee_installments == 1 ? 'active' : '' }} mr-3 mb-2" id="pill-opt-enable" style="cursor: pointer;">
                                                <input type="radio" name="include_fee_installments" class="fees-installment-toggle zolo-pill-input" value="1" {{ $fees->include_fee_installments == 1 ? 'checked' : '' }} style="position: absolute; opacity: 0; pointer-events: none;">
                                                <div class="pill-content d-inline-flex align-items-center py-2 px-3">
                                                    <span class="pill-indicator mr-2"><span class="radio-dot"></span></span>
                                                    <i class="fa fa-calendar mr-2 text-muted"></i>
                                                    <div>
                                                        <span class="font-weight-bold d-block">{{ __('Term / Installment Structure') }}</span>
                                                        <small class="text-muted">{{ __('Split into 2 terms, 3 terms, quarterly, or upfront + monthly') }}</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="fees-installment-repeater" style="{{$fees->include_fee_installments == 0 ? 'display: none' : ''}}">
                                        @if($fees->fees_paid_count == 0)
                                            {{-- Presets Bar (Allowed when no payments made) --}}
                                            <div class="bg-white p-3 rounded-lg border mb-3 shadow-sm">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="font-weight-bold text-dark mb-0">
                                                        <i class="fa fa-magic text-primary mr-1"></i>
                                                        {{ __('Quick Term Presets') }}
                                                    </h6>
                                                    <span class="text-muted small">{{ __('Re-calculate terms and dates with 1-click') }}</span>
                                                </div>
                                                <div class="d-flex flex-wrap align-items-center">
                                                    <div class="term-preset-card mr-2 mb-2" data-preset="bi-annual">
                                                        <div class="preset-icon"><i class="fa fa-pie-chart"></i></div>
                                                        <div>
                                                            <div class="preset-title">{{ __('2 Terms (Half-Yearly)') }}</div>
                                                            <div class="preset-desc">{{ __('50% & 50% (2 times a year)') }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="term-preset-card mr-2 mb-2" data-preset="trimester">
                                                        <div class="preset-icon"><i class="fa fa-th-large"></i></div>
                                                        <div>
                                                            <div class="preset-title">{{ __('3 Terms (Trimester)') }}</div>
                                                            <div class="preset-desc">{{ __('3 terms (every 4 months)') }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="term-preset-card mr-2 mb-2" data-preset="quarterly">
                                                        <div class="preset-icon"><i class="fa fa-calendar"></i></div>
                                                        <div>
                                                            <div class="preset-title">{{ __('4 Terms (Quarterly)') }}</div>
                                                            <div class="preset-desc">{{ __('3 months once term (Q1-Q4)') }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="term-preset-card mr-2 mb-2" data-preset="upfront_monthly">
                                                        <div class="preset-icon"><i class="fa fa-hand-holding-usd"></i></div>
                                                        <div>
                                                            <div class="preset-title">{{ __('50% Upfront + Monthly') }}</div>
                                                            <div class="preset-desc">{{ __('Half at admission, rest monthly') }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Balance Bar --}}
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
                                                    <span class="text-muted small d-block font-weight-bold">{{ __('STATUS') }}</span>
                                                    <span id="balance-badge" class="badge badge-success px-2 py-1 font-weight-bold">
                                                        <i class="fa fa-check-circle mr-1"></i> {{ __('100% Balanced') }}
                                                    </span>
                                                </div>
                                            </div>
                                            @if($fees->fees_paid_count == 0)
                                                <div class="my-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="btn-auto-balance" style="display: none;">
                                                        <i class="fa fa-balance-scale mr-1"></i> {{ __('Auto-Balance Remaining') }}
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Table Header --}}
                                        <div class="zolo-repeater-header rounded-top d-none d-xl-flex">
                                            <div class="col-xl-3 pl-2">{{ __('Term / Installment Name') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-2">{{ __('Amount') }} (₹) <span class="text-danger">*</span></div>
                                            <div class="col-xl-3">{{ __('Due Date') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-2">{{ __('Due Penalty Type') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-1">{{ __('Charges') }} <span class="text-danger">*</span></div>
                                            <div class="col-xl-1 text-center pr-2">{{ __('Action') }}</div>
                                        </div>

                                        <div data-repeater-list="fees_installments" class="zolo-repeater-table border-top-0">
                                            <div data-repeater-item class="zolo-repeater-row row mx-0 py-2">
                                                <input type="hidden" name="id" class="installment_id" />
                                                <div class="form-group col-lg-12 col-xl-3 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Term / Installment Name') }}</label>
                                                    {{ Form::text('name', null, ['class' => 'form-control installment-name font-weight-bold', 'placeholder' => __('installment') . ' ' . __('name'), 'required']) }}
                                                </div>
                                                <div class="form-group col-lg-12 col-xl-2 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Amount (₹)') }}</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-light text-muted font-weight-bold px-2">₹</span>
                                                        </div>
                                                        {{ Form::text('amount', null, ['class' => 'form-control installment-amount font-weight-bold', 'placeholder' => '0.00', 'required', "data-convert" => "number"]) }}
                                                    </div>
                                                </div>
                                                <div class="form-group col-lg-12 col-xl-3 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Due Date') }}</label>
                                                    {{ Form::text('due_date', null, ['class' => 'datepicker-popup form-control installment-due-date', 'placeholder' => __('due_date'), 'autocomplete' => 'off', 'required']) }}
                                                </div>
                                                <div class="form-group col-lg-12 col-xl-2 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Penalty Type') }}</label>
                                                    <div class="d-flex align-items-center pt-2">
                                                        <div class="custom-control custom-radio custom-control-inline mr-2">
                                                            {!! Form::radio('due_charges_type', "percentage", true, ['id' => 'edit_pct_'.rand(100,999), 'class' => 'custom-control-input percentage_due_charges_type', 'required' => true]) !!}
                                                            <label class="custom-control-label small font-weight-bold" for="edit_pct_{{ rand(100,999) }}">%</label>
                                                        </div>
                                                        <div class="custom-control custom-radio custom-control-inline mr-0">
                                                            {!! Form::radio('due_charges_type', "fixed", false, ['id' => 'edit_fix_'.rand(100,999), 'class' => 'custom-control-input fixed_due_charges_type', 'required' => true]) !!}
                                                            <label class="custom-control-label small font-weight-bold" for="edit_fix_{{ rand(100,999) }}">₹</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group col-lg-12 col-xl-1 mb-2 mb-xl-0 px-2">
                                                    <label class="small font-weight-bold d-xl-none text-muted">{{ __('Charges') }}</label>
                                                    {!! Form::text("due_charges", null, ["class" => "installment-due-charges form-control px-2", "placeholder" => trans('due_charges'), "required" => true, "data-convert" => "number", "min" => 0]) !!}
                                                </div>
                                                <div class="form-group col-lg-12 col-xl-1 mb-0 px-2 text-center d-flex align-items-center justify-content-center">
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm btn-icon remove-installment-fee"
                                                        data-repeater-delete title="{{ __('Remove') }}">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-primary btn-sm add-installment font-weight-bold" type="button" data-repeater-create>
                                            <i class="fa fa-plus-circle mr-1"></i>
                                            {{__('Add Custom Term Row')}}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- BOX 4: Optional Fees --}}
                            <div class="zolo-form-box">
                                <div class="zolo-box-header">
                                    <div class="d-flex align-items-center">
                                        <h4 class="zolo-box-title">
                                            <i class="fa fa-tags text-primary mr-2"></i>
                                            <span>{{ __('Optional Fees') }}</span>
                                        </h4>
                                        <span class="badge badge-light border text-muted ml-2 font-weight-normal py-1 px-2" style="font-size: 11px;">
                                            {{ __('Optional') }}
                                        </span>
                                    </div>
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
                                                <input type="hidden" name="id" class="fees_class_type_id" />
                                                <div class="col-md-5 col-12 mb-2 mb-md-0">
                                                    <label class="small font-weight-bold d-md-none text-muted">{{ __('Fees Type') }}</label>
                                                    <select name="fees_type_id" id="fees_type_id" class="form-control fees_type"
                                                        aria-label="Fees Type" required>
                                                        <option value="" hidden="">{{ __('Select Fees Type')}}</option>
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
                                                        data-repeater-delete title="{{ __('Remove') }}">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-primary btn-sm add-fees-type font-weight-bold" type="button" data-repeater-create>
                                            <i class="fa fa-plus-circle mr-1"></i>
                                            {{__('Add Optional Fee Head')}}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-12 d-flex justify-content-end align-items-center">
                                    <x-button buttonType="submit" id="update-btn" type="primary" icon="fa fa-check" label="{{ __('submit') }}" />
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
        $(document).ready(function () {
            compulsoryFeesTypeRepeater.setList([
                @foreach($fees->compulsory_fees as $type)
                {
                    id: "{{$type->id}}",
                    fees_type_id: "{{$type->fees_type_id}}",
                    amount: "{{$type->amount}}",
                },
                @endforeach
            ]);

            optionalFeesTypeRepeater.setList([
                @foreach($fees->optional_fees as $type)
                {
                    id: "{{$type->id}}",
                    fees_type_id: "{{$type->fees_type_id}}",
                    amount: "{{$type->amount}}"
                },
                @endforeach
            ]);

            feesInstallmentRepeater.setList([
                @foreach($fees->installments as $installment)
                {
                    id: "{{$installment->id}}",
                    name: "{{$installment->name}}",
                    amount: "{{$installment->installment_amount}}",
                    due_date: "{{$installment->due_date}}",
                    due_charges: "{{$installment->due_charges}}",
                    due_charges_type: "{{$installment->due_charges_type}}"
                },
                @endforeach
            ]);

            @if(count($fees->installments) > 0)
                $('#disable-installment').attr('disabled', true);
            @endif

            @if($fees->fees_paid_count > 0)
                $('.fees-installment-toggle,.percentage_due_charges_type,.fixed_due_charges_type').attr('readonly', true).bind('click', function () {
                    return false;
                });
                $('.installment-name, .installment-amount, .installment-due-date,.installment-due-charges,.fees_type,.amount,#due_date,#due_charges_percentage,#due_charges_amount').attr('readonly', true);
                $('.fees_type option:not(:selected)').attr('disabled', true);
                $('.remove-fees-type,.remove-installment-fee').bind('click', function () {
                    return false;
                });
                $('.add-fees-type,.add-installment').prop('disabled', true);
                $('.term-preset-card').hide();
            @endif

            function calculateCompulsoryTotal() {
                var total = 0;
                $('.compulsory-fees-types .amount').each(function () {
                    total += parseFloat($(this).val()) || 0;
                });
                return Math.round(total * 100) / 100;
            }

            function calculateInstallmentsTotal() {
                var total = 0;
                $('.fees-installment-repeater [data-repeater-item]').each(function () {
                    total += parseFloat($(this).find('.installment-amount').val()) || 0;
                });
                return Math.round(total * 100) / 100;
            }

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
                    $btnAuto.show().html('<i class="fa fa-magic mr-1"></i> Auto-Balance (+₹ ' + diff.toFixed(2) + ')');
                } else {
                    $badge.removeClass('badge-success badge-warning').addClass('badge-danger')
                          .html('<i class="fa fa-times-circle mr-1"></i> ₹ ' + Math.abs(diff).toFixed(2) + ' Exceeded');
                    $btnAuto.show().html('<i class="fa fa-magic mr-1"></i> Auto-Balance (-₹ ' + Math.abs(diff).toFixed(2) + ')');
                }
            }

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

            $('.fees-installment-toggle').on('change', function () {
                if ($(this).val() == '1') {
                    $('#pill-opt-enable').addClass('active');
                    $('#pill-opt-disable').removeClass('active');
                    $('.fees-installment-repeater').slideDown(300);
                } else {
                    $('#pill-opt-disable').addClass('active');
                    $('#pill-opt-enable').removeClass('active');
                    $('.fees-installment-repeater').slideUp(200);
                }
                updateBalanceDisplay();
            });

            $('#pill-opt-disable').on('click', function () {
                if ($(this).find('input').is(':disabled')) return;
                $(this).find('input').prop('checked', true).trigger('change');
            });
            $('#pill-opt-enable').on('click', function () {
                if ($(this).find('input').is(':disabled')) return;
                $(this).find('input').prop('checked', true).trigger('change');
            });

            $(document).on('input', '.compulsory-fees-types .amount, .fees-installment-repeater .installment-amount', function () {
                updateBalanceDisplay();
            });

            updateBalanceDisplay();
        });

        $('#feesForm').on('submit', function (e) {
            const compulsoryTotal = $('.compulsory-fees-types .amount').toArray().reduce((sum, el) => {
                return sum + (parseFloat($(el).val()) || 0);
            }, 0);

            const installmentsTotal = $('.fees-installment-repeater [data-repeater-item] .installment-amount').toArray().reduce((sum, el) => {
                return sum + (parseFloat($(el).val()) || 0);
            }, 0);

            const isInstallmentEnabled = $('.fees-installment-toggle:checked').val() === '1';

            if (isInstallmentEnabled && Math.abs(installmentsTotal - compulsoryTotal) > 0.01) {
                e.preventDefault();

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Total Installment must equal Compulsory Fees',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                });

                return false;
            }
        });
    </script>
@endsection