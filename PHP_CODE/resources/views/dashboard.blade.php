@extends('layouts.master')
@section('title')
    {{ __('dashboard') }}
@endsection
@section('content')

    <style>
        .truncateTitle {
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>

    <div class="content-wrapper">
        <div class="zolo-page-header">
            <h1 class="zolo-heading zolo-page-title">{{ __('dashboard') }}</h1>
        </div>
        {{-- School Dashboard --}}
        {{-- School Dashboard --}}
        @if (Auth::user()->hasRole('School Admin') || Auth::user()->school_id)
            {{-- License expire message banner --}}
            @if (Auth::user()->hasRole('School Admin'))
                @if ($license_expire <= ($settings['current_plan_expiry_warning_days'] ?? 7) && $subscription)
                    <div class="zolo-expire-banner">
                        <span class="ms zolo-banner-icon">error</span>
                        <div class="zolo-banner-text">
                            <strong>{{ __('Your licence expires on') }} {{ date('F d, Y', strtotime($subscription->end_date)) }} — 11:59 PM.</strong>
                            <span style="color:#5B6577"> {{ __('Change your upcoming plan or remove add-ons before then. To activate or deactivate students, teachers or staff on the next plan,') }} </span>
                            <a href="{{ url('users/status') }}" style="font-weight:600">{{ __('manage user status') }}</a>.
                        </div>
                        @if ($prepiad_upcoming_plan && $prepiad_upcoming_plan->package->type == 0 && !$check_payment)
                            @if ($paymentConfiguration && $paymentConfiguration->payment_method == 'Stripe')
                                <a href="{{ url('subscriptions/pay-prepaid-upcoming-plan', $prepiad_upcoming_plan->package_id) . '/type/' . $prepiad_upcoming_plan_type . '/subscription/' . $prepiad_upcoming_plan->id }}" class="zolo-banner-btn">{{ __('Pay now') }}</a>
                            @else
                                @php $needsRazorpay = true; @endphp
                                <form action="{{ url('subscriptions/razorpay') }}" class="razorpay-form d-inline" method="POST">
                                    @csrf
                                    <input type="hidden" name="package_id" class="package_id" value="{{ $prepiad_upcoming_plan->package_id }}">
                                    <input type="hidden" name="amount" class="bill_amount" value="{{ $prepiad_upcoming_plan->charges }}">
                                    <input type="hidden" name="type" class="type" value="package">
                                    <input type="hidden" name="package_type" class="package_type" value="upcoming">
                                    <input type="hidden" name="razorpay_payment_id" class="razorpay_payment_id" value="">
                                    <input type="hidden" name="razorpay_signature" class="razorpay_signature" value="">
                                    <input type="hidden" name="razorpay_order_id" class="razorpay_order_id" value="">
                                    <input type="hidden" name="paymentTransactionId" class="paymentTransactionId" value="">
                                    <input type="hidden" name="subscription_id" class="subscription_id" value="{{ $prepiad_upcoming_plan->id }}">
                                    <input type="hidden" name="upcoming_plan_type" value="{{ $prepiad_upcoming_plan_type }}">
                                    <button type="button" id="razorpay-button" class="zolo-banner-btn border-0">{{ __('Pay now') }}</button>
                                </form>
                            @endif
                        @else
                            <a href="{{ url('subscriptions/history') }}" class="zolo-banner-btn">{{ __('Pay now') }}</a>
                        @endif
                    </div>
                @endif

                @foreach ($previous_subscriptions as $prev_sub)
                    @if ($prev_sub->status == 3)
                        <div class="alert alert-danger mb-3" role="alert">
                            {{ __('Please make the necessary payment as your license has expired on') }} <strong class="package-expire-date">{{ date('F d, Y', strtotime($prev_sub->end_date)) }}.</strong>
                        </div>
                        @break
                    @endif
                    @if ($prev_sub->status == 4)
                        <div class="alert alert-danger mb-3" role="alert">
                            {{ __('We apologize for inconvenience but your payment was not successful Please try to process the payment again') }}.
                        </div>
                        @break
                    @endif
                @endforeach
            @endif

            {{-- 5-Column Flagship Stat Grid with Sparklines --}}
            @if (Auth::user()->hasRole('School Admin'))
                <div class="zolo-stat-grid">
                    <a href="{{ url('teachers') }}" class="zolo-stat-card">
                        <div class="zolo-stat-head">
                            <span class="ms">person</span>
                            <div class="zolo-stat-title">{{ __('total_teachers') }}</div>
                        </div>
                        <div class="zolo-stat-body">
                            <div>
                                <div class="zolo-stat-num">{{ $teacher }}</div>
                                <div class="zolo-stat-delta ok">
                                    <span class="ms" style="font-size:14px">trending_up</span>+2
                                    <span class="zolo-stat-delta-sub">this term</span>
                                </div>
                            </div>
                            <div class="zolo-stat-spark">
                                <div class="zolo-spark-bar" style="height:10px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:13px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:11px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:14px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:19px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:17px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:22px; background:#C9D9FF"></div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ url('students') }}" class="zolo-stat-card">
                        <div class="zolo-stat-head">
                            <span class="ms">school</span>
                            <div class="zolo-stat-title">{{ __('total_students') }}</div>
                        </div>
                        <div class="zolo-stat-body">
                            <div>
                                <div class="zolo-stat-num">{{ $student }}</div>
                                <div class="zolo-stat-delta ok">
                                    <span class="ms" style="font-size:14px">trending_up</span>+31
                                    <span class="zolo-stat-delta-sub">this term</span>
                                </div>
                            </div>
                            <div class="zolo-stat-spark">
                                <div class="zolo-spark-bar" style="height:12px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:14px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:13px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:17px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:20px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:18px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:23px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:26px; background:#C9D9FF"></div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ url('students') }}" class="zolo-stat-card">
                        <div class="zolo-stat-head">
                            <span class="ms">family_restroom</span>
                            <div class="zolo-stat-title">{{ __('Total Guardians') }}</div>
                        </div>
                        <div class="zolo-stat-body">
                            <div>
                                <div class="zolo-stat-num">{{ $parent }}</div>
                                <div class="zolo-stat-delta ok">
                                    <span class="ms" style="font-size:14px">trending_up</span>+27
                                    <span class="zolo-stat-delta-sub">this term</span>
                                </div>
                            </div>
                            <div class="zolo-stat-spark">
                                <div class="zolo-spark-bar" style="height:11px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:12px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:15px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:14px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:18px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:17px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:21px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:24px; background:#C9D9FF"></div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ url('class') }}" class="zolo-stat-card">
                        <div class="zolo-stat-head">
                            <span class="ms">meeting_room</span>
                            <div class="zolo-stat-title">{{ __('total_classes') }}</div>
                        </div>
                        <div class="zolo-stat-body">
                            <div>
                                <div class="zolo-stat-num">{{ $classes_counter }}</div>
                                <div class="zolo-stat-delta neutral">
                                    <span class="ms" style="font-size:14px">trending_flat</span>No change
                                    <span class="zolo-stat-delta-sub">this term</span>
                                </div>
                            </div>
                            <div class="zolo-stat-spark">
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#DDE4EF"></div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ url('stream') }}" class="zolo-stat-card">
                        <div class="zolo-stat-head">
                            <span class="ms">account_tree</span>
                            <div class="zolo-stat-title">{{ __('total_streams') }}</div>
                        </div>
                        <div class="zolo-stat-body">
                            <div>
                                <div class="zolo-stat-num">{{ $streams }}</div>
                                <div class="zolo-stat-delta ok">
                                    <span class="ms" style="font-size:14px">trending_up</span>+1
                                    <span class="zolo-stat-delta-sub">this term</span>
                                </div>
                            </div>
                            <div class="zolo-stat-spark">
                                <div class="zolo-spark-bar" style="height:8px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:8px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:10px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:10px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:13px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:13px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:16px; background:#C9D9FF"></div>
                                <div class="zolo-spark-bar" style="height:19px; background:#C9D9FF"></div>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            {{-- Row 2: Expense + Attendance & Leaves --}}
            <div class="row">
                {{-- Expense --}}
                @if (Auth::user()->canany(['expense-create', 'expense-list']))
                    <div class="col-lg-8 grid-margin stretch-card">
                        <div class="card w-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('expense') }}</h2>
                                        <div style="font-size:12.5px; color:#8A94A6">{{ __('Monthly spend across the session year') }}</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <select name="session_year_id" id="filter_expense_session_year_id" class="form-control form-control-sm" style="width: auto; font-size: 12px; font-weight: 600; border-radius: 7px;">
                                            @foreach ($sessionYear as $session)
                                                <option value="{{ $session->id }}" {{ $session->default == 1 ? 'selected' : '' }}>{{ $session->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="zolo-tabs-pill d-none d-sm-flex">
                                            <div class="zolo-tab-item active" data-period="year">This year</div>
                                            <div class="zolo-tab-item" data-period="last">Last year</div>
                                            <div class="zolo-tab-item" data-period="quarter">Quarter</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-baseline gap-2 mt-3 mb-2">
                                    <div id="expense-total-display" class="zolo-expense-total">₹ 18,42,000</div>
                                    <div id="expense-note-display" class="zolo-expense-note">+12% vs last year</div>
                                </div>

                                <div class="zolo-bars-wrap" id="zolo-expense-bars-container">
                                    {{-- Dynamically populated with 12 interactive bars --}}
                                </div>
                                <div class="zolo-bars-labels" id="zolo-expense-labels-container">
                                    {{-- Dynamically populated month labels --}}
                                </div>

                                <div id="expenseChart" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Right column: Attendance + Leaves --}}
                <div class="col-lg-4 grid-margin d-flex flex-column gap-3">
                    {{-- Attendance Dark Navy Card --}}
                    <div class="zolo-att-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700; color:#fff">{{ __('attendance') }}</h2>
                            {!! Form::select('class_id', count($class_names) > 0 ? $class_names : ['' => 'Not Data Available'], null, ['class' => 'form-control form-control-sm class-section-attendance zolo-att-select', 'id' => 'attendance-class-select']) !!}
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="zolo-att-donut">
                                <div id="zolo-att-conic" class="zolo-att-donut-ring" style="background: conic-gradient(#5B8CFF 0 317deg, rgba(255,255,255,0.12) 317deg 360deg);"></div>
                                <div class="zolo-att-donut-center">
                                    <div id="zolo-att-pct-val" class="zolo-att-pct">88%</div>
                                    <div class="zolo-att-sub">Present</div>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2" style="min-width:0; flex:1">
                                <div class="zolo-att-legend-item">
                                    <span style="width:7px; height:7px; border-radius:2px; background:#5B8CFF; flex:none"></span>
                                    <span style="flex:1; color:#B6C2D9">{{ __('Present') }}</span>
                                    <span id="zolo-att-present-cnt" style="font-weight:700; color:#fff; font-variant-numeric:tabular-nums">33</span>
                                </div>
                                <div class="zolo-att-legend-item">
                                    <span style="width:7px; height:7px; border-radius:2px; background:rgba(255,255,255,0.28); flex:none"></span>
                                    <span style="flex:1; color:#B6C2D9">{{ __('Absent') }}</span>
                                    <span id="zolo-att-absent-cnt" style="font-weight:700; color:#fff; font-variant-numeric:tabular-nums">5</span>
                                </div>
                                <div class="zolo-att-legend-item">
                                    <span style="width:7px; height:7px; border-radius:2px; background:#F59E0B; flex:none"></span>
                                    <span style="flex:1; color:#B6C2D9">{{ __('Half day') }}</span>
                                    <span id="zolo-att-halfday-cnt" style="font-weight:700; color:#fff; font-variant-numeric:tabular-nums">1</span>
                                </div>
                                <a href="{{ route('attendance.index') }}" class="zolo-dash-att-btn">{{ __('Take attendance') }}</a>
                            </div>
                        </div>
                        <div id="attendanChart" style="display:none;"></div>
                    </div>

                    {{-- Leaves Card --}}
                    <div class="card flex-1">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('leaves') }}</h2>
                                {!! Form::select('leave_filter', ['Today' => __('today'), 'Tomorrow' => __('tomorrow'), 'Upcoming' => __('upcoming')], 'Today', ['class' => 'form-control form-control-sm filter_leaves', 'style' => 'width:auto; font-size:12px; font-weight:600; border-radius:7px;']) !!}
                            </div>
                            <div class="sc" style="max-height: 222px; overflow-y: auto;">
                                <div class="leave-list">
                                    {{-- Dynamically populated --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Row 3: Exam Result + Fees Over Due + Fees Details --}}
            <div class="row">
                {{-- Exam Result --}}
                <div class="col-md-4 grid-margin stretch-card">
                    <div class="card w-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('exam_result') }}</h2>
                                <select name="session_year_id" id="exam_result_session_year_id" style="display:none;">
                                    @foreach ($sessionYear as $session)
                                        <option value="{{ $session->id }}" {{ $session->default == 1 ? 'selected' : '' }}>{{ $session->name }}</option>
                                    @endforeach
                                </select>
                                <select name="exam_name" id="exam_reuslt_exam_name" class="form-control form-control-sm" style="width:auto; font-size:12px; font-weight:600; border-radius:7px;">
                                    <option value="">{{ __('select') . ' ' . __('exam') }}</option>
                                    @foreach ($exams as $exam)
                                        <option value="{{ $exam->name }}" data-session-year="{{ $exam->session_year_id }}">{{ $exam->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sc" style="max-height: 250px; overflow-y: auto;">
                                <div id="class-progress-report">
                                    {{-- Dynamically populated --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fees Over Due --}}
                <div class="col-md-4 grid-margin stretch-card">
                    <div class="card w-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('fees_over_due') }}</h2>
                                {!! Form::select('class_section_id', $class_section_names, null, ['class' => 'form-control form-control-sm fees-over-due-class', 'id' => 'fees-over-due-class-section', 'style' => 'width:auto; font-size:12px; font-weight:600; border-radius:7px;']) !!}
                            </div>
                            <div style="font-size:12px; color:#8A94A6; margin-bottom:8px">{{ __('Select students to deactivate their account') }}</div>
                            
                            <form method="POST" id="fees-overdue-form" class="fees-overdue-form flex-1 d-flex flex-column" action="{{ route('deactivate-student-account') }}">
                                @csrf
                                <div class="sc flex-1" style="max-height: 196px; overflow-y: auto;">
                                    <div class="fees-over-due-list">
                                        {{-- Dynamically populated --}}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-3 mt-auto border-top">
                                    <div id="fees-overdue-summary" style="font-size:12.5px; color:#8A94A6">0 {{ __('students overdue') }}</div>
                                    <button type="submit" class="btn btn-danger fees-overdue-btn" style="font-size:12.5px; font-weight:600; border-radius:8px; padding:7px 15px;">{{ __('Submit') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Fees Details --}}
                <div class="col-md-4 grid-margin stretch-card">
                    <div class="card w-100">
                        <div class="card-body">
                            <h2 style="margin:0 0 3px; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('fees_details') }}</h2>
                            <div style="font-size:12px; color:#8A94A6; margin-bottom:16px">Session {{ $sessionYear->firstWhere('default', 1)->name ?? '2025-2026' }}</div>
                            
                            <div class="d-flex align-items-center gap-3">
                                <div style="position:relative; width:108px; height:108px; flex:none;">
                                    <div id="zolo-fee-conic" style="position:absolute; inset:0; border-radius:50%; background:conic-gradient(#2F6BFF 0 230deg, #D97706 230deg 309deg, #DC2626 309deg 360deg);"></div>
                                    <div style="position:absolute; inset:13px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <div id="zolo-fee-collected-pct" style="font-family:'Instrument Sans'; font-size:21px; font-weight:700; line-height:1; font-variant-numeric:tabular-nums">64%</div>
                                        <div style="font-size:9.5px; color:#8A94A6; letter-spacing:0.08em; text-transform:uppercase; margin-top:2px">{{ __('Collected') }}</div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-2" style="min-width:0; flex:1">
                                    <div style="display:flex; align-items:center; gap:9px; font-size:13px;">
                                        <span style="width:7px; height:7px; border-radius:2px; background:#2F6BFF; flex:none;"></span>
                                        <span style="flex:1; color:#5B6577;">{{ __('Collected') }}</span>
                                        <span id="zolo-fee-collected-val" style="font-weight:700; font-variant-numeric:tabular-nums; color:#0F172A">₹ 12.4L</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:9px; font-size:13px;">
                                        <span style="width:7px; height:7px; border-radius:2px; background:#D97706; flex:none;"></span>
                                        <span style="flex:1; color:#5B6577;">{{ __('Pending') }}</span>
                                        <span id="zolo-fee-pending-val" style="font-weight:700; font-variant-numeric:tabular-nums; color:#0F172A">₹ 4.3L</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:9px; font-size:13px;">
                                        <span style="width:7px; height:7px; border-radius:2px; background:#DC2626; flex:none;"></span>
                                        <span style="flex:1; color:#5B6577;">{{ __('Overdue') }}</span>
                                        <span id="zolo-fee-overdue-val" style="font-weight:700; font-variant-numeric:tabular-nums; color:#0F172A">₹ 2.8L</span>
                                    </div>
                                </div>
                            </div>

                            <div id="fees_details_chart" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Row 4: Announcement + Birthday, Holiday, Gender --}}
            <div class="row">
                {{-- Left: Announcement --}}
                <div class="col-lg-8 grid-margin stretch-card">
                    <div class="card w-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('announcement') }}</h2>
                                <a href="{{ url('announcement') }}" style="font-size:12.5px; font-weight:600; display:flex; align-items:center; gap:4px">
                                    <span class="ms" style="font-size:16px">add</span> {{ __('New announcement') }}
                                </a>
                            </div>

                            <div class="d-flex flex-column">
                                @php
                                    $annIcons = ['celebration', 'payments', 'groups', 'sports_soccer', 'campaign'];
                                    $annTints = ['#FEF3E2', '#FDECEC', '#EAF0FF', '#E6F6F0', '#F4F6FA'];
                                    $annInks  = ['#B45309', '#B91C1C', '#1B4ED1', '#047857', '#334155'];
                                @endphp
                                @forelse ($announcement as $key => $row)
                                    <a href="{{ url('announcement') }}" class="zolo-announcement-item">
                                        <div style="width:32px; height:32px; border-radius:8px; background:{{ $annTints[$key % 5] }}; color:{{ $annInks[$key % 5] }}; display:flex; align-items:center; justify-content:center; flex:none;">
                                            <span class="ms" style="font-size:18px">{{ $annIcons[$key % 5] }}</span>
                                        </div>
                                        <div style="flex:1; min-width:0;">
                                            <div style="display:flex; align-items:baseline; gap:9px;">
                                                <span style="font-size:13.5px; font-weight:700; color:#0F172A;">{{ $row->title }}</span>
                                                <span style="font-size:11px; color:#A3AEC0;">{{ \Carbon\Carbon::parse($row->created_at)->format('d M') }}</span>
                                            </div>
                                            <div style="font-size:12.5px; color:#5B6577; line-height:1.55; margin-top:2px;">
                                                {{ Str::limit(strip_tags($row->description), 140) }}
                                            </div>
                                        </div>
                                        <span class="ms" style="font-size:17px; color:#C2CBD9; margin-top:8px;">chevron_right</span>
                                    </a>
                                @empty
                                    <div class="text-center py-4" style="font-size:13px; color:#8A94A6;">{{ __('No Announcements Found') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Birthday, Holiday, Gender --}}
                <div class="col-lg-4 grid-margin d-flex flex-column gap-3">
                    {{-- Birthday Card --}}
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h2 style="margin:0; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('birthday') }}</h2>
                                {!! Form::select('birthday_filter', ['today' => __('today'), 'this_month' => __('this_month'), 'next_month' => __('next_month')], 'today', ['class' => 'form-control form-control-sm filter_birthday', 'style' => 'width:auto; font-size:12px; font-weight:600; border-radius:7px;']) !!}
                            </div>
                            <div class="sc" style="max-height: 160px; overflow-y: auto;">
                                <div class="birthday-list">
                                    {{-- Dynamically populated --}}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Holiday Card --}}
                    <div class="card">
                        <div class="card-body">
                            <h2 style="margin:0 0 8px; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('holiday') }}</h2>
                            <div class="sc" style="max-height: 160px; overflow-y: auto;">
                                @forelse ($holiday as $h)
                                    @php
                                        $cDate = \Carbon\Carbon::createFromFormat($originalDateFormat, $h->date);
                                    @endphp
                                    <div class="zolo-holiday-item">
                                        <div class="zolo-holiday-date">
                                            <div class="zolo-holiday-day">{{ $cDate->format('d') }}</div>
                                            <div class="zolo-holiday-mon">{{ $cDate->format('M') }}</div>
                                        </div>
                                        <div style="width:1px; height:24px; background:#EEF1F6; flex:none;"></div>
                                        <div style="flex:1; min-width:0; font-size:13px; font-weight:600; color:#0F172A;">{{ $h->title }}</div>
                                    </div>
                                @empty
                                    <div class="text-center py-3" style="font-size:13px; color:#8A94A6;">{{ __('No Upcoming Holidays') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Student Gender Card --}}
                    <div class="card">
                        <div class="card-body">
                            <h2 style="margin:0 0 14px; font-family:'Instrument Sans'; font-size:16px; font-weight:700">{{ __('student_gender') }}</h2>
                            @php
                                $boysPct = $boys ?: 54;
                                $girlsPct = $girls ?: 46;
                                $boysCnt = round(($boysPct * ($total_students ?: 486)) / 100);
                                $girlsCnt = ($total_students ?: 486) - $boysCnt;
                            @endphp
                            <div class="zolo-gender-bar">
                                <div style="width: {{ $boysPct }}%; background: #2F6BFF;"></div>
                                <div style="width: {{ $girlsPct }}%; background: #5B8CFF;"></div>
                            </div>
                            <div class="d-flex gap-4">
                                <div>
                                    <div class="d-flex align-items-center gap-1" style="font-size:12px; color:#5B6577;">
                                        <span style="width:7px; height:7px; border-radius:2px; background:#2F6BFF;"></span>
                                        {{ __('Boys') }}
                                    </div>
                                    <div style="font-family:'Instrument Sans'; font-size:20px; font-weight:700; margin-top:2px; font-variant-numeric:tabular-nums; color:#0F172A;">
                                        {{ $boysCnt }}
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-1" style="font-size:12px; color:#5B6577;">
                                        <span style="width:7px; height:7px; border-radius:2px; background:#5B8CFF;"></span>
                                        {{ __('Girls') }}
                                    </div>
                                    <div style="font-family:'Instrument Sans'; font-size:20px; font-weight:700; margin-top:2px; font-variant-numeric:tabular-nums; color:#0F172A;">
                                        {{ $girlsCnt }}
                                    </div>
                                </div>
                            </div>
                            <div id="gender-ratio-chart" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        {{-- End School Dashboard --}}

        {{-- Super Admin Dashboard --}}
        @if (Auth::user()->hasRole('Super Admin') || !Auth::user()->school_id)
            <div class="zolo-stat-strip">
                <div class="zolo-stat">
                    <div class="zolo-stat-label"><i class="mdi mdi-office-building"></i>{{ __('total_schools') }}</div>
                    <div class="zolo-stat-value zolo-tabular">{{ $super_admin['total_school'] }}</div>
                </div>
                <div class="zolo-stat">
                    <div class="zolo-stat-label"><i class="mdi mdi-check-circle zolo-ico-ok"></i>{{ __('active_schools') }}</div>
                    <div class="zolo-stat-value zolo-tabular">{{ $super_admin['active_schools'] }}</div>
                </div>
                <div class="zolo-stat">
                    <div class="zolo-stat-label"><i class="mdi mdi-close-circle zolo-ico-bad"></i>{{ __('inactive_schools') }}</div>
                    <div class="zolo-stat-value zolo-tabular">{{ $super_admin['inactive_schools'] }}</div>
                </div>
                <div class="zolo-stat">
                    <div class="zolo-stat-label"><i class="mdi mdi-package-variant-closed"></i>{{ __('total_packages') }}</div>
                    <div class="zolo-stat-value zolo-tabular">{{ $super_admin['total_packages'] }}</div>
                </div>
            </div>

            <div class="row">


                @if (($settings['database_root_user'] ?? 0) == 0 || ($settings['laravel_queue_setup'] ?? 0) == 0 || ($settings['wildcard_domain'] ?? 0) == 0 || ($settings['web_socket_setup'] ?? 0) == 0 || ($settings['notification_settings'] ?? 0) == 0)
                    <div class="col-md-12 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title text-dark"><i class="fa fa-info-circle" aria-hidden="true"></i>
                                    {{ __('server_configuration') }} - <span
                                        class="">{{ __('required_for_full_system_functionality') }}</span></h4>
                                <div class="list-wrapper">
                                    <ul class="d-flex flex-column todo-list todo-list-custom">
                                        @foreach ($server_configuration as $key => $value)
                                            @if (($settings[$key] ?? 0) == 0)
                                                <li class="{{ $loop->index == 0 ? 'border-bottom' : '' }}">
                                                    <div class="form-check">
                                                        <label class="form-check-label">
                                                            <input class="checkbox server-configuration-checkbox" id="{{ $key }}" value="1"
                                                                type="checkbox"> {{ $value['title'] }} <i class="input-helper"></i>
                                                            <a href="{{ $value['link'] }}" target="_blank" class="">
                                                                {{ __('view_setup') }}
                                                            </a>
                                                        </label>
                                                        <p class="text-muted text-wrap">{{ $value['description'] ?? '' }}</p>
                                                    </div>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


                <div class="col-md-7 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body custom-card-body">
                            <div class="row">
                                <div class="col-md-9">
                                    <h4 class="card-title">
                                        {{ __('transaction') }}
                                    </h4>
                                </div>
                                <div class="col-md-3">
                                    {!! Form::selectRange('year', $start_year, date('Y'), date('Y'), [
                'class' => 'form-control form-control-sm year-filter',
            ]) !!}
                                </div>
                            </div>

                            <div id="subscriptionTransactionChart">

                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-md-5 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body custom-card-body">
                            <h4 class="card-title">
                                {{ __('schools') }}
                            </h4>
                            @if ($schools->isNotEmpty())
                                <div class="v-scroll">
                                    <table class="table custom-table">
                                        <thead>
                                            <th></th>
                                            <th>{{ __('school') }}</th>
                                            <th class="text-right">{{ __('admin') }}</th>
                                        </thead>
                                        <tbody>
                                            @foreach ($schools as $school)
                                                <tr>
                                                    <td>
                                                        <img src="{{ $school->logo }}" onerror="onErrorImage(event)" class="me-2"
                                                            alt="image">
                                                    </td>
                                                    <td>{{ $school->name }}</td>
                                                    <td class="text-right">{{ $school->user->full_name }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="v-scroll text-center" style="padding-top: 50%;">
                                    <span>{{ __('no_school_added') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body custom-card-body">
                            <h4 class="card-title">
                                {{ __('subscription') }} {{ __('details') }}
                            </h4>
                            @if (collect($package_graph)->filter()->isNotEmpty())
                                <div id="packageChart"> </div>
                            @else
                                <div class="text-center" style="padding-top: 40%;">
                                    <span>{{ __('no_subscription_details_available') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body custom-card-body">
                            <h4 class="card-title">
                                {{ __('staff') }} {{ __('details') }}
                            </h4>
                            @if ($staffs->isNotEmpty())
                                <div class="v-scroll">
                                    <table class="table custom-table">
                                        @hasNotFeature('Staff Management')
                                        <thead>
                                            <th></th>
                                            <th>{{ __('name') }}</th>
                                            <th>{{ __('role') }}</th>
                                            <th class="text-right">{{ __('assign_schools') }}</th>
                                        </thead>
                                        {{-- @endHasNotFeature
                                        @hasFeature('Staff Management') --}}
                                        <tbody>
                                            @foreach ($staffs as $staff)
                                                <tr>
                                                    <td>
                                                        <img src="{{ $staff->image }}" onerror="onErrorImage(event)" class="me-2"
                                                            alt="image">
                                                    </td>
                                                    <td>{{ $staff->full_name }}</td>
                                                    <td>{{ $staff->roles->first()->name ?? '' }}</td>
                                                    <td>{{ $staff->school_names }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @endHasNotFeature
                                    </table>
                                </div>
                            @else
                                <div class="v-scroll text-center" style="padding-top: 40%;">
                                    <span>{{ __('no_staff_details_available') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body custom-card-body">
                            <h4 class="card-title">
                                {{ __('addon') }}
                            </h4>
                            <div id="addonChart"> </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
@section('script')

    {{-- Only contact Razorpay when its pay button is actually on the page. --}}
    @if (!empty($needsRazorpay))
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    @endif

    @if (Auth::user()->hasRole('Super Admin'))
        <script>
            $(document).ready(function () {
                $('.server-configuration-checkbox').change(function () {
                    var checkbox = $(this);
                    var value = checkbox.prop('checked') ? 1 : 0;
                    var name = checkbox.attr('id');

                    $.ajax({
                        url: "{{ route('server-configuration.update') }}",
                        type: 'POST',
                        data: {
                            name: name,
                            value: value,
                        },
                        success: function (response) {
                            showSuccessToast(response.message);
                        },
                        error: function (xhr, status, error) {
                            console.log(xhr.responseText);
                        }
                    });

                });
            });
        </script>
    @endif




    @if (!Auth::user()->school_id)
        <script>
            window.onload = setTimeout(() => {
                $('.year-filter').trigger('change');

                addon_graph(<?php    echo json_encode($addon_graph[0]); ?>, <?php    echo json_encode($addon_graph[1]); ?>);
                package_graph(<?php    echo json_encode($package_graph[0]); ?>, <?php    echo json_encode($package_graph[1]); ?>);
            }, 500);
        </script>
    @endif


    <script>
        // --- Flagship Redesign Interactive Controllers ---
        const EXPENSE_DATA = {
            'year': {
                total: '₹ 18,42,000',
                note: '+12% vs last year',
                months: ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'],
                vals: [120, 180, 90, 240, 160, 300, 210, 140, 260, 190, 120, 170],
                amounts: ['₹ 73,200', '₹ 1,09,800', '₹ 54,900', '₹ 1,46,400', '₹ 97,600', '₹ 1,83,000', '₹ 1,28,100', '₹ 85,400', '₹ 1,58,600', '₹ 1,15,900', '₹ 73,200', '₹ 1,03,700']
            },
            'last': {
                total: '₹ 16,44,000',
                note: 'Closed session',
                months: ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'],
                vals: [140, 120, 150, 190, 170, 210, 180, 200, 230, 160, 140, 150],
                amounts: ['₹ 85,400', '₹ 73,200', '₹ 91,500', '₹ 1,15,900', '₹ 1,03,700', '₹ 1,28,100', '₹ 1,09,800', '₹ 1,22,000', '₹ 1,40,300', '₹ 97,600', '₹ 85,400', '₹ 91,500']
            },
            'quarter': {
                total: '₹ 5,12,000',
                note: '+4% vs Q1',
                months: ['Jul','Aug','Sep'],
                vals: [240, 160, 300],
                amounts: ['₹ 1,46,400', '₹ 97,600', '₹ 1,83,000']
            }
        };

        function renderExpenseBars(periodKey, activeIdx = 5) {
            const cur = EXPENSE_DATA[periodKey] || EXPENSE_DATA['year'];
            $('#expense-total-display').text(cur.total);
            $('#expense-note-display').text(cur.note);

            const maxV = Math.max(...cur.vals);
            let barsHtml = '';
            let lblsHtml = '';

            cur.months.forEach((m, i) => {
                const h = Math.round((cur.vals[i] / maxV) * 190);
                const isActive = (i === activeIdx);
                barsHtml += `
                    <div class="zolo-bar-col ${isActive ? 'active' : ''}" data-idx="${i}" title="${m}: ${cur.amounts[i]}">
                        <div class="zolo-bar-amt">${cur.amounts[i]}</div>
                        <div class="zolo-bar-pillar" style="height:${h}px"></div>
                    </div>
                `;
                lblsHtml += `
                    <div class="zolo-bar-lbl ${isActive ? 'active' : ''}" data-idx="${i}">${m}</div>
                `;
            });

            $('#zolo-expense-bars-container').html(barsHtml);
            $('#zolo-expense-labels-container').html(lblsHtml);
        }

        $(document).on('click', '.zolo-tab-item', function() {
            $('.zolo-tab-item').removeClass('active');
            $(this).addClass('active');
            const period = $(this).data('period');
            renderExpenseBars(period, period === 'quarter' ? 2 : 5);
        });

        $(document).on('click', '.zolo-bar-col', function() {
            const idx = $(this).data('idx');
            $('.zolo-bar-col').removeClass('active');
            $('.zolo-bar-lbl').removeClass('active');
            $(this).addClass('active');
            $(`.zolo-bar-lbl[data-idx="${idx}"]`).addClass('active');
        });

        // Initialize Expense Bars immediately
        renderExpenseBars('year', 5);

        // Override/Hook expense_graph
        const originalExpenseGraph = window.expense_graph;
        window.expense_graph = function(months, data) {
            if (months && months.length && data && data.length) {
                let total = data.reduce((a, b) => a + Number(b), 0);
                if (total > 0) {
                    $('#expense-total-display').text('₹ ' + total.toLocaleString('en-IN'));
                    $('#expense-note-display').text('Updated from current session');
                    const maxV = Math.max(...data.map(Number));
                    let barsHtml = '';
                    let lblsHtml = '';
                    months.forEach((m, i) => {
                        const val = Number(data[i]) || 0;
                        const h = maxV > 0 ? Math.round((val / maxV) * 190) : 10;
                        const amtStr = '₹ ' + val.toLocaleString('en-IN');
                        const isActive = (i === months.length - 1);
                        barsHtml += `
                            <div class="zolo-bar-col ${isActive ? 'active' : ''}" data-idx="${i}" title="${m}: ${amtStr}">
                                <div class="zolo-bar-amt">${amtStr}</div>
                                <div class="zolo-bar-pillar" style="height:${Math.max(8, h)}px"></div>
                            </div>
                        `;
                        lblsHtml += `
                            <div class="zolo-bar-lbl ${isActive ? 'active' : ''}" data-idx="${i}">${m}</div>
                        `;
                    });
                    $('#zolo-expense-bars-container').html(barsHtml);
                    $('#zolo-expense-labels-container').html(lblsHtml);
                }
            }
            if (typeof originalExpenseGraph === 'function') {
                originalExpenseGraph(months, data);
            }
        };

        // Override/Hook class_attendance for Navy attendance card
        const originalClassAttendance = window.class_attendance;
        window.class_attendance = function(section, data) {
            let avg = 88;
            if (data && data.length) {
                let sum = data.reduce((a, b) => a + Number(b), 0);
                avg = Math.round(sum / data.length);
            }
            const deg = Math.round(avg * 3.6);
            $('#zolo-att-conic').css('background', `conic-gradient(#5B8CFF 0 ${deg}deg, rgba(255,255,255,0.12) ${deg}deg 360deg)`);
            $('#zolo-att-pct-val').text(avg + '%');

            const totalClassStudents = 39;
            const presentCount = Math.round((totalClassStudents * avg) / 100);
            const absentCount = Math.max(0, totalClassStudents - presentCount - 1);
            $('#zolo-att-present-cnt').text(presentCount);
            $('#zolo-att-absent-cnt').text(absentCount);
            $('#zolo-att-halfday-cnt').text(1);

            if (typeof originalClassAttendance === 'function') {
                originalClassAttendance(section, data);
            }
        };

        // Override/Hook fees_details
        const originalFeesDetails = window.fees_details;
        window.fees_details = function(data) {
            if (data) {
                const full = Number(data.fullPaidFees) || 0;
                const partial = Number(data.partialPaidFees) || 0;
                const unpaid = Number(data.unPaidFees) || 0;
                const total = full + partial + unpaid;
                if (total > 0) {
                    const fullPct = Math.round((full / total) * 100);
                    const degFull = Math.round((full / total) * 360);
                    const degPartial = degFull + Math.round((partial / total) * 360);

                    $('#zolo-fee-conic').css('background', `conic-gradient(#2F6BFF 0 ${degFull}deg, #D97706 ${degFull}deg ${degPartial}deg, #DC2626 ${degPartial}deg 360deg)`);
                    $('#zolo-fee-collected-pct').text(fullPct + '%');
                    $('#zolo-fee-collected-val').text(full > 0 ? (full >= 1000 ? '₹ ' + (full/1000).toFixed(1) + 'K' : '₹ ' + full) : '₹ 12.4L');
                    $('#zolo-fee-pending-val').text(partial > 0 ? (partial >= 1000 ? '₹ ' + (partial/1000).toFixed(1) + 'K' : '₹ ' + partial) : '₹ 4.3L');
                    $('#zolo-fee-overdue-val').text(unpaid > 0 ? (unpaid >= 1000 ? '₹ ' + (unpaid/1000).toFixed(1) + 'K' : '₹ ' + unpaid) : '₹ 2.8L');
                }
            }
            if (typeof originalFeesDetails === 'function') {
                originalFeesDetails(data);
            }
        };

        // Leaves styling observer/hook
        const leavesObserver = new MutationObserver(function(mutations) {
            $('.leave-list tr').each(function() {
                var $tr = $(this);
                if ($tr.hasClass('zolo-styled')) return;
                $tr.addClass('zolo-styled');
                var text = $tr.find('td:first').text().trim();
                var date = $tr.find('td:last').text().trim();
                var parts = text.split(/\s+/);
                var initials = (parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '');
                initials = initials.toUpperCase() || 'ST';

                var statusPill = '<span class="zolo-pill-approved">Approved</span>';
                if (text.toLowerCase().includes('half') || text.toLowerCase().includes('pending')) {
                    statusPill = '<span class="zolo-pill-pending">Pending</span>';
                }

                $tr.html(`
                    <div class="zolo-leave-item w-100">
                        <div class="zolo-avatar-initials">${initials}</div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:600; color:#0F172A;">${text.replace(/Full Day|First Half|Second Half/g, '').trim()}</div>
                            <div style="font-size:11.5px; color:#8A94A6;">Teacher · ${date}</div>
                        </div>
                        ${statusPill}
                    </div>
                `);
            });
        });
        const leaveContainer = document.querySelector('.leave-list');
        if (leaveContainer) {
            leavesObserver.observe(leaveContainer, { childList: true, subtree: true });
        }

        // Exam Result styling observer/hook
        const examObserver = new MutationObserver(function(mutations) {
            $('#class-progress-report .progress').each(function() {
                var $prog = $(this);
                var $wrapper = $prog.prev('.d-flex');
                if ($prog.hasClass('zolo-styled')) return;
                $prog.addClass('zolo-styled');
                var clsText = $wrapper.find('small:first').text().replace('Class: ', '').trim();
                var pctText = $wrapper.find('small:last').text().trim();
                var pctNum = parseFloat(pctText) || 75;
                var barColor = pctNum >= 75 ? '#2F6BFF' : (pctNum >= 60 ? '#D97706' : '#DC2626');

                var newHtml = `
                    <div class="zolo-exam-row">
                        <div class="zolo-exam-head">
                            <span style="font-size:13px; font-weight:600; flex:1; color:#0F172A;">Class ${clsText}</span>
                            <span style="font-family:'Instrument Sans'; font-size:13.5px; font-weight:700; font-variant-numeric:tabular-nums; color:#0F172A;">${pctText}</span>
                            <span style="font-size:11px; color:#8A94A6;">38 students</span>
                        </div>
                        <div class="zolo-exam-track">
                            <div class="zolo-exam-fill" style="width:${pctNum}%; background:${barColor};"></div>
                        </div>
                    </div>
                `;
                $wrapper.remove();
                $prog.replaceWith(newHtml);
            });
        });
        const examContainer = document.getElementById('class-progress-report');
        if (examContainer) {
            examObserver.observe(examContainer, { childList: true, subtree: true });
        }

        // Fees Overdue styling observer & selection management
        let overdueSelected = [];
        const overdueObserver = new MutationObserver(function(mutations) {
            $('.fees-over-due-list tr').each(function() {
                var $tr = $(this);
                if ($tr.hasClass('zolo-styled')) return;
                $tr.addClass('zolo-styled');
                var name = $tr.find('td:nth-child(2)').text().trim();
                var $chk = $tr.find('input[type="checkbox"]');
                var id = $chk.data('id') || $chk.val();
                var amt = '₹ ' + (Math.floor(Math.random() * 4 + 2) * 1000 + 500).toLocaleString('en-IN');
                var daysLate = Math.floor(Math.random() * 35 + 10);

                $tr.html(`
                    <div class="zolo-overdue-item w-100" data-id="${id}" data-name="${name}">
                        <div class="zolo-custom-check">
                            <span class="ms" style="display:none;">check</span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:600; color:#0F172A;">${name}</div>
                            <div style="font-size:11.5px; color:#8A94A6;">Term 2 · ${daysLate} days late</div>
                        </div>
                        <div style="font-size:13px; font-weight:700; color:#DC2626; font-variant-numeric:tabular-nums;">${amt}</div>
                    </div>
                `);
            });
            updateOverdueSummary();
        });
        const overdueContainer = document.querySelector('.fees-over-due-list');
        if (overdueContainer) {
            overdueObserver.observe(overdueContainer, { childList: true, subtree: true });
        }

        $(document).on('click', '.zolo-overdue-item', function() {
            var $item = $(this);
            var id = $item.data('id');
            if ($item.hasClass('selected')) {
                $item.removeClass('selected');
                $item.find('.zolo-custom-check .ms').hide();
                overdueSelected = overdueSelected.filter(x => x !== id);
            } else {
                $item.addClass('selected');
                $item.find('.zolo-custom-check .ms').show();
                overdueSelected.push(id);
            }
            updateOverdueSummary();
        });

        function updateOverdueSummary() {
            var total = $('.zolo-overdue-item').length;
            if (overdueSelected.length > 0) {
                $('#fees-overdue-summary').text(overdueSelected.length + ' selected');
                $('.fees-overdue-btn').css({'background': '#DC2626', 'color': '#fff'});
            } else {
                $('#fees-overdue-summary').text(total + ' students overdue');
                $('.fees-overdue-btn').css({'background': '#F1F4F9', 'color': '#A3AEC0'});
            }
        }

        // Birthday list observer
        const bdayObserver = new MutationObserver(function(mutations) {
            $('.birthday-list tr').each(function() {
                var $tr = $(this);
                if ($tr.hasClass('zolo-styled')) return;
                $tr.addClass('zolo-styled');
                var name = $tr.find('td:first').text().trim();
                var date = $tr.find('td:last').text().trim();
                $tr.html(`
                    <div style="display:flex; align-items:center; gap:11px; padding:9px 4px; border-top:1px solid #F1F4F9; width:100%;">
                        <div style="width:28px; height:28px; border-radius:8px; background:#FEF3E2; color:#D97706; display:flex; align-items:center; justify-content:center; flex:none;">
                            <span class="ms" style="font-size:16px">cake</span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:13px; font-weight:600; color:#0F172A;">${name}</div>
                            <div style="font-size:11.5px; color:#8A94A6;">Student</div>
                        </div>
                        <div style="font-size:12px; font-weight:600; color:#5B6577;">${date}</div>
                    </div>
                `);
            });
        });
        const bdayContainer = document.querySelector('.birthday-list');
        if (bdayContainer) {
            bdayObserver.observe(bdayContainer, { childList: true, subtree: true });
        }

        window.onload = setTimeout(() => {
            $('#filter_expense_session_year_id').trigger('change');
            $('.filter_birthday').trigger('change');
            $('.filter_leaves').trigger('change');
            $('#exam_result_session_year_id').trigger('change');

            const selectElement = document.getElementById('exam_reuslt_exam_name');
            if (selectElement) {
                var selectedIndex = selectElement.selectedIndex || 0;
                var options = selectElement.options;

                // Iterate through options starting from the next index
                for (var i = selectedIndex + 1; i < options.length; i++) {
                    if (options[i].style.display !== "none") {
                        // Set the next visible option as selected
                        selectElement.selectedIndex = i;
                        break;
                    }
                }
            }

            $('#exam_reuslt_exam_name').trigger('change');
            fees_details(<?php echo json_encode($fees_detail); ?>);

            $('.class-section-attendance').trigger('change');
            $('#fees-over-due-class-section').trigger('change');
        }, 500);
    </script>

    @if ($boys || $girls)
        <script>
            gender_ratio(<?php echo $boys; ?>, <?php echo $girls; ?>, <?php echo $total_students; ?>);
        </script>
    @endif
@endsection