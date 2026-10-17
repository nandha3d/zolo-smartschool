@extends('layouts.master')

{{-- One list page for loans, fines and reservations: $title, $listUrl, $statuses, $columns. --}}
@section('title')
    {{ $title }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ $title }}</h3>
        </div>
        @include('library::partials.nav')

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="row" id="toolbar">
                            <div class="form-group col-md-4">
                                <select id="filter_status" class="form-control">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected(request('status', array_key_first($statuses)) == $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <table aria-describedby="library-register" class="table" id="table_list" data-toggle="table"
                               data-url="{{ $listUrl }}" data-side-pagination="server" data-pagination="true"
                               data-page-list="[10, 20, 50, 100]" data-search="true" data-toolbar="#toolbar"
                               data-show-columns="true" data-show-refresh="true" data-mobile-responsive="true"
                               data-sort-name="id" data-sort-order="desc" data-query-params="libraryRegisterQueryParams"
                               data-show-export="true" data-export-data-type="all">
                            <thead>
                            <tr>
                                <th data-field="no">{{ __('no.') }}</th>
                                @foreach ($columns as [$field, $label, $formatter])
                                    <th data-field="{{ $field }}" @if ($formatter) data-formatter="{{ $formatter }}" data-escape="false" @endif>{{ $label }}</th>
                                @endforeach
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.LibraryRegister = {
            canCirculate: @json(Auth::user()->can('library-circulation')),
            renewUrl: "{{ url('library/loans') }}/:id/renew",
            settleUrl: "{{ url('library/fines') }}/:id/settle",
            cancelUrl: "{{ url('library/reservations') }}/:id/cancel",
        };
    </script>
    <script src="{{ asset('assets/js/custom/library.js') }}"></script>
@endsection
