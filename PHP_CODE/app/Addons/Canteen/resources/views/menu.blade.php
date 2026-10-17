@extends('layouts.master')

@section('title')
    {{ __('Canteen Menu') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen Menu') }}</h3>
        </div>
        @include('canteen::partials.nav')

        <div class="row">
            @can('canteen-create')
                <div class="col-lg-9 grid-margin stretch-card">
                    <div class="card"><div class="card-body">
                        <h4 class="card-title">{{ __('Add an item') }}</h4>
                        <form class="create-form" action="{{ route('canteen.items.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                            @csrf
                            @include('canteen::partials.item-fields', ['p' => 'new_'])
                            <input class="btn btn-theme float-right ml-3" type="submit" value="{{ __('submit') }}">
                            <input class="btn btn-secondary float-right" type="reset" value="{{ __('reset') }}">
                        </form>
                    </div></div>
                </div>
                <div class="col-lg-3 grid-margin stretch-card">
                    <div class="card"><div class="card-body">
                        <h4 class="card-title">{{ __('Categories') }}</h4>
                        <form data-canteen-form data-after="reload" action="{{ route('canteen.categories.store') }}" method="POST" class="mb-3">
                            <div class="input-group input-group-sm">
                                <input name="name" class="form-control" placeholder="{{ __('e.g. Hot Breakfast') }}" required maxlength="191">
                                <div class="input-group-append"><button class="btn btn-theme" type="submit"><i class="fa fa-plus"></i></button></div>
                            </div>
                        </form>
                        <ul class="list-group list-group-flush small">
                            @foreach ($categories as $category)
                                <li class="list-group-item d-flex justify-content-between px-0 py-1">{{ $category->name }}
                                    @can('canteen-delete')<button type="button" class="btn btn-xs btn-link text-danger p-0" data-canteen-delete="{{ route('canteen.categories.destroy', $category->id) }}"><i class="fa fa-times"></i></button>@endcan
                                </li>
                            @endforeach
                        </ul>
                    </div></div>
                </div>
            @endcan
            <div class="col-12 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <div class="row" id="toolbar">
                        <div class="col-md-4">
                            <select id="filter_category_id" class="form-control">
                                <option value="">{{ __('All categories') }}</option>
                                @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <table aria-describedby="canteen-items" class="table" id="table_list" data-toggle="table"
                           data-url="{{ route('canteen.items.list') }}" data-side-pagination="server" data-pagination="true"
                           data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-sort-name="name" data-sort-order="asc"
                           data-query-params="canteenQueryParams">
                        <thead>
                        <tr>
                            <th data-field="name" data-sortable="true">{{ __('Item') }}</th>
                            <th data-field="category">{{ __('Category') }}</th>
                            <th data-field="price" data-sortable="true">{{ __('Price') }}</th>
                            <th data-field="dietary_type" data-formatter="canteenDietFormatter" data-escape="false">{{ __('Type') }}</th>
                            <th data-field="allergens" data-formatter="canteenAllergenFormatter" data-escape="false">{{ __('Allergens') }}</th>
                            <th data-field="calories">{{ __('kcal') }}</th>
                            <th data-field="stock_qty" data-formatter="canteenStockFormatter">{{ __('Stock') }}</th>
                            <th data-field="is_available" data-formatter="canteenAvailableFormatter" data-escape="false">{{ __('On menu') }}</th>
                            <th data-field="operate" data-escape="false" data-events="canteenItemEvents">{{ __('action') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Edit item') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form class="edit-form" action="" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="modal-body">@include('canteen::partials.item-fields', ['p' => 'edit_'])</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                        <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.CanteenMenu = { toggleUrl: "{{ url('canteen/items') }}/:id/toggle" };
    </script>
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
