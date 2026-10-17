@extends('layouts.master')

@section('title')
    {{ __('Canteen POS') }}
@endsection

@section('css')
    <style>
        .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
        .pos-item { border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; text-align: left; background: #fff; min-height: 92px; }
        .pos-item:hover { border-color: #6366f1; }
        .pos-item[disabled] { opacity: .45; }
        .pos-item .price { font-weight: 700; }
        .pos-diet { display: inline-block; width: 10px; height: 10px; border: 1px solid; margin-right: 4px; }
        .pos-diet.Veg, .pos-diet.Vegan { border-color: #16a34a; background: #16a34a; }
        .pos-diet.Non-Veg { border-color: #b91c1c; background: #b91c1c; }
        .pos-diet.Egg { border-color: #ca8a04; background: #ca8a04; }
        .pos-cart td { vertical-align: middle; }
        #pos-allergen-modal .modal-content { border: 4px solid #dc2626; }
    </style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen POS') }}</h3>
        </div>
        @include('canteen::partials.nav')

        <div class="row">
            <div class="col-lg-8 grid-margin">
                <div class="card"><div class="card-body">
                    <div class="d-flex flex-wrap mb-3" style="gap: 6px;" id="pos-categories">
                        <button type="button" class="btn btn-sm btn-theme pos-cat" data-id="">{{ __('All') }}</button>
                        @foreach ($categories as $category)
                            <button type="button" class="btn btn-sm btn-outline-secondary pos-cat" data-id="{{ $category->id }}">{{ $category->name }}</button>
                        @endforeach
                        <input type="search" id="pos-search" class="form-control form-control-sm ml-auto" style="max-width: 200px;" placeholder="{{ __('Search / scan item code') }}">
                    </div>
                    <div class="pos-grid" id="pos-items"></div>
                </div></div>
            </div>
            <div class="col-lg-4 grid-margin">
                <div class="card mb-3"><div class="card-body">
                    <form id="pos-customer-form">
                        <div class="input-group">
                            <input type="text" id="pos-customer-code" class="form-control" autocomplete="off" placeholder="{{ __('Tap RFID card or type admission no.') }}">
                            <div class="input-group-append"><button class="btn btn-theme" type="submit"><i class="fa fa-id-card"></i></button></div>
                        </div>
                    </form>
                    <div id="pos-customer" class="mt-3 d-none">
                        <div class="d-flex align-items-center">
                            <img id="pos-customer-image" src="" alt="" class="rounded mr-2" style="width:48px;height:48px;object-fit:cover;background:#f1f5f9">
                            <div class="flex-grow-1">
                                <strong id="pos-customer-name"></strong>
                                <div class="small text-muted" id="pos-customer-class"></div>
                            </div>
                            <button type="button" class="btn btn-xs btn-link" id="pos-customer-clear">{{ __('clear') }}</button>
                        </div>
                        <div class="d-flex justify-content-between small mt-2">
                            <span>{{ __('Balance') }}: <strong id="pos-balance"></strong></span>
                            <span>{{ __('Left today') }}: <strong id="pos-left"></strong></span>
                        </div>
                        <div class="small text-danger mt-1" id="pos-alerts"></div>
                    </div>
                </div></div>
                <div class="card"><div class="card-body">
                    <table class="table table-sm pos-cart mb-2"><tbody id="pos-cart"></tbody></table>
                    <div class="d-flex justify-content-between h4"><span>{{ __('Total') }}</span><span id="pos-total">0.00</span></div>
                    <input type="text" id="pos-notes" class="form-control form-control-sm mb-2" maxlength="191" placeholder="{{ __('Note for the kitchen') }}">
                    <div class="d-flex" style="gap: 6px;">
                        <button type="button" class="btn btn-success flex-fill pos-pay" data-mode="Wallet"><i class="fa fa-credit-card mr-1"></i> {{ __('Wallet') }}</button>
                        <button type="button" class="btn btn-outline-primary flex-fill pos-pay" data-mode="Cash">{{ __('Cash') }}</button>
                        <button type="button" class="btn btn-outline-primary flex-fill pos-pay" data-mode="Card">{{ __('Card') }}</button>
                    </div>
                    <button type="button" class="btn btn-link btn-sm text-muted px-0 mt-1" id="pos-clear">{{ __('Clear order') }}</button>
                    <div id="pos-last" class="alert alert-success mt-2 mb-0 d-none"></div>
                </div></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pos-allergen-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <i class="fa fa-exclamation-triangle fa-4x text-danger"></i>
                    <h3 class="text-danger mt-3">{{ __('ALLERGEN HAZARD') }}</h3>
                    <p class="lead" id="pos-allergen-text"></p>
                    <button type="button" class="btn btn-danger" data-dismiss="modal">{{ __('Understood — remove the item') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.CanteenPos = {
            items: @json($items),
            customerUrl: "{{ route('canteen.pos.customer') }}",
            checkoutUrl: "{{ route('canteen.pos.checkout') }}",
        };
    </script>
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
