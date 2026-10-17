@extends('layouts.master')

@section('title')
    {{ __('Meal Plans') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Meal Plans') }}</h3>
        </div>
        @include('canteen::partials.nav')

        <div class="row">
            <div class="col-lg-6 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('Plans') }}</h4>
                    <table class="table table-sm small">
                        <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Price') }}</th><th>{{ __('Days') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td>{{ $plan->name }}<div class="text-muted">{{ $plan->description }}</div></td>
                                <td>{{ number_format($plan->price, 2) }}</td>
                                <td>{{ $plan->duration_days }}</td>
                                <td>
                                    <span class="badge badge-{{ $plan->is_active ? 'success' : 'secondary' }}">{{ $plan->is_active ? __('On offer') : __('Withdrawn') }}</span>
                                    @can('canteen-edit')<button type="button" class="btn btn-xs btn-link" data-canteen-post="{{ route('canteen.plans.toggle', $plan->id) }}">{{ __('toggle') }}</button>@endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">{{ __('No meal plans yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @can('canteen-create')
                        <form data-canteen-form data-after="reload" action="{{ route('canteen.plans.store') }}" method="POST" class="border-top pt-3">
                            <div class="row">
                                <div class="form-group col-md-6"><label class="small">{{ __('Name') }} *</label><input name="name" class="form-control form-control-sm" required placeholder="{{ __('Monthly full lunch plan') }}"></div>
                                <div class="form-group col-md-3"><label class="small">{{ __('Price') }} *</label><input type="number" name="price" class="form-control form-control-sm" min="0" step="0.01" required></div>
                                <div class="form-group col-md-3"><label class="small">{{ __('Days') }}</label><input type="number" name="duration_days" class="form-control form-control-sm" min="1" max="366" value="30"></div>
                                <div class="form-group col-md-12"><label class="small">{{ __('Description') }}</label><input name="description" class="form-control form-control-sm" maxlength="191"></div>
                            </div>
                            <button class="btn btn-sm btn-theme" type="submit">{{ __('Add plan') }}</button>
                        </form>
                    @endcan
                </div></div>
            </div>
            <div class="col-lg-6 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('Subscribe a student or staff member') }}</h4>
                    @can('canteen-wallet')
                        <form data-canteen-form data-after="reload" action="{{ route('canteen.plans.subscribe') }}" method="POST">
                            <div class="row">
                                <div class="form-group col-md-6"><label class="small">{{ __('Card / admission no. / email') }} *</label><input name="code" class="form-control form-control-sm" required maxlength="64"></div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Plan') }}</label>
                                    <select name="plan_id" class="form-control form-control-sm">@foreach ($plans->where('is_active', true) as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} — {{ number_format($plan->price, 2) }}</option>@endforeach</select></div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Starts') }}</label><input type="date" name="start_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Paid by') }}</label>
                                    <select name="payment_mode" class="form-control form-control-sm"><option value="Wallet">{{ __('Wallet') }}</option><option value="Cash">{{ __('Cash') }}</option></select></div>
                            </div>
                            <button class="btn btn-sm btn-theme" type="submit">{{ __('Subscribe') }}</button>
                        </form>
                    @endcan
                    <h4 class="card-title mt-4">{{ __('Recent subscriptions') }}</h4>
                    <table class="table table-sm small mb-0">
                        @forelse ($subscriptions as $sub)
                            <tr>
                                <td>{{ $sub->user?->full_name }}</td>
                                <td>{{ $sub->plan?->name }}</td>
                                <td>{{ $sub->start_date->toDateString() }} → {{ $sub->end_date->toDateString() }}</td>
                                <td>{{ $sub->payment_mode }}</td>
                                <td>
                                    @if ($sub->status === 'Active')
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                        @can('canteen-wallet')<button type="button" class="btn btn-xs btn-link text-danger" data-canteen-post="{{ route('canteen.plans.subscriptions.cancel', $sub->id) }}">{{ __('cancel') }}</button>@endcan
                                    @else
                                        <span class="badge badge-secondary">{{ $sub->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">{{ __('None yet.') }}</td></tr>
                        @endforelse
                    </table>
                </div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
