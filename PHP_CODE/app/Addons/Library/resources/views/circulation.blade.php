@extends('layouts.master')

@section('title')
    {{ __('Issue / Return') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Issue / Return Desk') }}</h3>
            <div class="btn-group">
                <button type="button" class="btn btn-sm" id="desk-mode-issue"><i class="fa fa-sign-out mr-1"></i> {{ __('Issue') }}</button>
                <button type="button" class="btn btn-sm" id="desk-mode-return"><i class="fa fa-sign-in mr-1"></i> {{ __('Return') }} <small>(F2)</small></button>
            </div>
        </div>
        @include('library::partials.nav')

        <div class="row">
            <div class="col-lg-7 grid-margin">
                <div class="card mb-3" id="desk-member-step">
                    <div class="card-body">
                        <h4 class="card-title mb-2">1. {{ __('Scan member card') }}</h4>
                        <form id="desk-member-form">
                            <div class="input-group">
                                <input type="text" id="desk-member-code" class="form-control form-control-lg" autocomplete="off"
                                       placeholder="{{ __('Membership no., admission no., email or mobile') }}">
                                <div class="input-group-append"><button class="btn btn-theme" type="submit"><i class="fa fa-search"></i></button></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h4 class="card-title mb-2" id="desk-copy-label"></h4>
                        <form id="desk-copy-form">
                            <div class="input-group">
                                <input type="text" id="desk-barcode" class="form-control form-control-lg text-monospace" autocomplete="off" placeholder="LB000000000">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="desk-copy-lookup" title="{{ __('Look up without changing anything') }}"><i class="fa fa-info-circle"></i></button>
                                    <button class="btn btn-theme" type="submit"><i class="fa fa-check mr-1"></i> {{ __('Go') }} <small>(Enter)</small></button>
                                </div>
                            </div>
                            <div class="form-inline mt-2 d-none" id="desk-condition-wrap">
                                <label class="small mr-2">{{ __('Condition on return') }}</label>
                                <select id="desk-condition" class="form-control form-control-sm">
                                    <option value="">{{ __('unchanged') }}</option>
                                    <option>New</option><option>Good</option><option>Fair</option><option>Poor</option>
                                </select>
                            </div>
                        </form>
                        <div id="desk-message" class="alert alert-light mt-3 mb-0">{{ __('Ready.') }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-2">{{ __('Reserve a title for this member') }}</h4>
                        <input type="search" id="desk-reserve-search" class="form-control form-control-sm" placeholder="{{ __('Title, author or ISBN') }}">
                        <ul class="list-group list-group-flush mt-2" id="desk-reserve-results"></ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 grid-margin">
                <div class="card d-none" id="desk-member">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img id="desk-member-image" src="" alt="" class="rounded mr-3" style="width:64px;height:64px;object-fit:cover;background:#f1f5f9">
                            <div>
                                <h4 class="mb-0" id="desk-member-name"></h4>
                                <div class="small text-muted" id="desk-member-meta"></div>
                                <span class="badge mt-1" id="desk-member-status"></span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>{{ __('Books out') }}: <strong id="desk-member-quota"></strong></span>
                            <span>{{ __('Unpaid fines') }}: <strong id="desk-member-fines"></strong></span>
                        </div>
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>{{ __('On loan') }}</th><th>{{ __('Due') }}</th><th></th></tr></thead>
                            <tbody id="desk-loans"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @php
        $deskStrings = [
                'scanToIssue' => '2. ' . __('Scan book barcode to issue'),
                'scanToReturn' => __('Scan book barcode to return'),
                'renew' => __('Renew'),
                'lost' => __('Lost'),
                'eligible' => __('Eligible'),
                'blocked' => __('Cannot borrow'),
                'noLoans' => __('No books on loan.'),
                'memberReady' => __('Member found. Scan books to issue.'),
                'memberBlocked' => __('Member found, but cannot borrow right now (limit, fines or status).'),
                'scanMemberFirst' => __('Scan the member card first.'),
                'confirmLost' => __('Mark this book lost and charge its replacement price?'),
                'onShelf' => __('on shelf'),
                'reserve' => __('Reserve'),
            ];
    @endphp
    <script>
        window.LibraryDesk = {
            memberUrl: "{{ route('library.circulation.member') }}",
            copyUrl: "{{ route('library.circulation.copy') }}",
            booksUrl: "{{ route('library.circulation.books') }}",
            issueUrl: "{{ route('library.circulation.issue') }}",
            returnUrl: "{{ route('library.circulation.return') }}",
            reserveUrl: "{{ route('library.circulation.reserve') }}",
            renewUrl: "{{ url('library/loans') }}/:id/renew",
            lostUrl: "{{ url('library/loans') }}/:id/lost",
            t: @json($deskStrings),
        };
    </script>
    <script src="{{ asset('assets/js/custom/library.js') }}"></script>
@endsection
