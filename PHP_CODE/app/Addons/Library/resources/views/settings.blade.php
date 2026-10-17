@extends('layouts.master')

@section('title')
    {{ __('Library Settings') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Library Settings') }}</h3>
        </div>
        @include('library::partials.nav')

        <div class="row">
            <div class="col-lg-8 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <form class="edit-form-without-reset" action="{{ route('library.settings.update') }}" method="POST" novalidate>
                            @csrf
                            <h4 class="card-title">{{ __('Borrowing limits') }}</h4>
                            <div class="row">
                                @foreach ([
                                    ['student_max_books', __('Books a student may hold'), 0, 50],
                                    ['student_issue_days', __('Student loan period (days)'), 1, 365],
                                    ['staff_max_books', __('Books a teacher / staff member may hold'), 0, 50],
                                    ['staff_issue_days', __('Staff loan period (days)'), 1, 365],
                                    ['max_renewals', __('Renewals allowed per loan'), 0, 20],
                                    ['reservation_hold_days', __('Days a returned copy is held for a reservation'), 1, 30],
                                ] as [$field, $label, $min, $max])
                                    <div class="form-group col-md-6">
                                        <label>{{ $label }}</label>
                                        <input type="number" name="{{ $field }}" class="form-control" min="{{ $min }}" max="{{ $max }}" value="{{ $settings->$field }}" required>
                                    </div>
                                @endforeach
                            </div>
                            <h4 class="card-title mt-2">{{ __('Overdue fines') }}</h4>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>{{ __('Fine per working day') }}</label>
                                    <input type="number" name="fine_per_day" class="form-control" min="0" step="0.01" value="{{ $settings->fine_per_day }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>{{ __('Grace period (working days)') }}</label>
                                    <input type="number" name="grace_period_days" class="form-control" min="0" max="30" value="{{ $settings->grace_period_days }}" required>
                                </div>
                                <div class="form-group col-md-12">
                                    <div class="custom-control custom-checkbox">
                                        <input type="hidden" name="skip_holidays" value="0">
                                        <input type="checkbox" class="custom-control-input" id="skip_holidays" name="skip_holidays" value="1" @checked($settings->skip_holidays)>
                                        <label class="custom-control-label" for="skip_holidays">{{ __('Skip Sundays and school holidays when counting late days and due dates') }}</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="hidden" name="allow_online_reservation" value="0">
                                        <input type="checkbox" class="custom-control-input" id="allow_online_reservation" name="allow_online_reservation" value="1" @checked($settings->allow_online_reservation)>
                                        <label class="custom-control-label" for="allow_online_reservation">{{ __('Let students and staff reserve books from the mobile app') }}</label>
                                    </div>
                                </div>
                            </div>
                            <input class="btn btn-theme float-right" type="submit" value="{{ __('submit') }}">
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body small text-muted">
                        <h4 class="card-title">{{ __('How fines work') }}</h4>
                        <p>{{ __('A fine is charged when a book comes back after its due date and the grace period. Days are counted as working days if the option is on.') }}</p>
                        <p>{{ __('Members with unpaid fines cannot borrow until the fine is collected or waived on the Fines page.') }}</p>
                        <p class="mb-0">{{ __('Every night the system flags overdue loans and releases reservations that were not collected in time.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
