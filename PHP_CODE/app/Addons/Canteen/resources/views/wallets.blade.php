@extends('layouts.master')

@section('title')
    {{ __('Canteen Wallets') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen Wallets') }}</h3>
        </div>
        @include('canteen::partials.nav')

        <div class="row">
            <div class="col-lg-5 grid-margin">
                <div class="card"><div class="card-body">
                    <form id="wallet-lookup-form">
                        <div class="input-group">
                            <input type="text" id="wallet-code" class="form-control" autocomplete="off" placeholder="{{ __('RFID card, admission no., email or mobile') }}">
                            <div class="input-group-append"><button class="btn btn-theme" type="submit"><i class="fa fa-search"></i></button></div>
                        </div>
                    </form>
                    <div id="wallet-panel" class="d-none mt-3">
                        <h4 class="mb-0" id="wallet-name"></h4>
                        <div class="small text-muted mb-2" id="wallet-class"></div>
                        <div class="h3 mb-3"><span id="wallet-balance"></span> <small class="text-muted h6">{{ __('balance') }}</small></div>

                        <form id="wallet-topup-form" class="mb-3">
                            <label class="small font-weight-bold">{{ __('Top up') }}</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="amount" class="form-control" min="1" step="0.01" required placeholder="{{ __('Amount') }}">
                                <select name="payment_mode" class="form-control"><option>Cash</option><option>Card</option><option>Online</option><option>Cheque</option></select>
                                <input type="text" name="reference" class="form-control" maxlength="64" placeholder="{{ __('Ref.') }}">
                                <div class="input-group-append"><button class="btn btn-success" type="submit">{{ __('Add') }}</button></div>
                            </div>
                        </form>

                        <form id="wallet-controls-form">
                            <div class="row">
                                <div class="form-group col-6"><label class="small">{{ __('RFID card no.') }}</label><input name="rfid_card" class="form-control form-control-sm" maxlength="64"></div>
                                <div class="form-group col-6"><label class="small">{{ __('Daily limit') }}</label><input type="number" name="daily_limit" class="form-control form-control-sm" min="0" step="0.01" placeholder="{{ __('no limit') }}"></div>
                            </div>
                            <label class="small d-block">{{ __('Allergies (items containing these are blocked at the till)') }}</label>
                            @foreach ($allergens as $allergen)
                                <div class="custom-control custom-checkbox custom-control-inline">
                                    <input type="checkbox" class="custom-control-input wallet-allergen" id="wa_{{ \Illuminate\Support\Str::slug($allergen) }}" value="{{ $allergen }}">
                                    <label class="custom-control-label small" for="wa_{{ \Illuminate\Support\Str::slug($allergen) }}">{{ $allergen }}</label>
                                </div>
                            @endforeach
                            <label class="small d-block mt-2">{{ __('Blocked categories') }}</label>
                            @foreach ($categories as $category)
                                <div class="custom-control custom-checkbox custom-control-inline">
                                    <input type="checkbox" class="custom-control-input wallet-blocked" id="wb_{{ $category->id }}" value="{{ $category->id }}">
                                    <label class="custom-control-label small" for="wb_{{ $category->id }}">{{ $category->name }}</label>
                                </div>
                            @endforeach
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="wallet-locked">
                                <label class="custom-control-label small" for="wallet-locked">{{ __('Lock wallet (e.g. lost card)') }}</label>
                            </div>
                            <button class="btn btn-sm btn-theme mt-2" type="submit">{{ __('Save settings') }}</button>
                        </form>

                        @can('canteen-edit')
                            <form id="wallet-adjust-form" class="mt-3 border-top pt-2">
                                <label class="small font-weight-bold">{{ __('Correction') }}</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="amount" class="form-control" step="0.01" required placeholder="{{ __('+/- amount') }}">
                                    <input type="text" name="note" class="form-control" required maxlength="191" placeholder="{{ __('Reason') }}">
                                    <div class="input-group-append"><button class="btn btn-outline-secondary" type="submit">{{ __('Apply') }}</button></div>
                                </div>
                            </form>
                        @endcan
                    </div>
                </div></div>
            </div>
            <div class="col-lg-7 grid-margin">
                <div class="card mb-3"><div class="card-body">
                    <h4 class="card-title">{{ __('Wallets') }}</h4>
                    <div id="toolbar" class="row">
                        <div class="col-md-5">
                            <select id="filter_status" class="form-control">
                                <option value="">{{ __('All') }}</option>
                                <option value="low">{{ __('Balance under 50') }}</option>
                                <option value="locked">{{ __('Locked') }}</option>
                            </select>
                        </div>
                    </div>
                    <table aria-describedby="canteen-wallets" class="table" id="table_list" data-toggle="table"
                           data-url="{{ route('canteen.wallets.list') }}" data-side-pagination="server" data-pagination="true"
                           data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-sort-name="id" data-sort-order="desc"
                           data-query-params="canteenQueryParams">
                        <thead>
                        <tr>
                            <th data-field="name" data-formatter="canteenWalletName" data-escape="false">{{ __('Name') }}</th>
                            <th data-field="balance" data-sortable="true">{{ __('Balance') }}</th>
                            <th data-field="daily_limit">{{ __('Daily limit') }}</th>
                            <th data-field="spent_today">{{ __('Spent today') }}</th>
                            <th data-field="rfid_card">{{ __('Card') }}</th>
                            <th data-field="allergens" data-formatter="canteenAllergenFormatter" data-escape="false">{{ __('Allergies') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div></div>
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('Ledger') }} <small class="text-muted" id="ledger-for"></small></h4>
                    <table aria-describedby="canteen-ledger" class="table" id="ledger_list" data-toggle="table"
                           data-url="{{ route('canteen.wallets.transactions') }}" data-side-pagination="server" data-pagination="true"
                           data-sort-name="id" data-sort-order="desc" data-query-params="canteenLedgerParams" data-page-size="10">
                        <thead>
                        <tr>
                            <th data-field="created_at">{{ __('When') }}</th>
                            <th data-field="name">{{ __('Name') }}</th>
                            <th data-field="type">{{ __('Type') }}</th>
                            <th data-field="amount">{{ __('Amount') }}</th>
                            <th data-field="balance_after">{{ __('Balance') }}</th>
                            <th data-field="payment_mode">{{ __('Mode') }}</th>
                            <th data-field="reference">{{ __('Ref.') }}</th>
                            <th data-field="note">{{ __('Note') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.CanteenWallets = {
            lookupUrl: "{{ route('canteen.wallets.lookup') }}",
            topupUrl: "{{ url('canteen/wallets') }}/:id/top-up",
            adjustUrl: "{{ url('canteen/wallets') }}/:id/adjust",
            updateUrl: "{{ url('canteen/wallets') }}/:id",
        };
    </script>
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
