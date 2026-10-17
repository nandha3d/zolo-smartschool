@extends('layouts.master')

@section('title')
    {{ __('Library Catalogue') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Library Catalogue') }}</h3>
        </div>
        @include('library::partials.nav')

        <div class="row">
            @can('library-create')
                <div class="col-lg-9 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('Add a title') }}</h4>
                            <form class="create-form pt-2" action="{{ route('library.books.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                                @csrf
                                @include('library::partials.book-fields', ['p' => 'new_'])
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label>{{ __('Copies to add now') }}</label>
                                        <input type="number" name="copies" class="form-control" value="1" min="0" max="200">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>{{ __('Acquired by') }}</label>
                                        <select name="acquisition_type" class="form-control">
                                            <option value="Purchase">{{ __('Purchase') }}</option>
                                            <option value="Donation">{{ __('Donation') }}</option>
                                            <option value="Govt_Grant">{{ __('Government grant') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <input class="btn btn-theme float-right ml-3" type="submit" value="{{ __('submit') }}">
                                <input class="btn btn-secondary float-right" type="reset" value="{{ __('reset') }}">
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('Categories') }}</h4>
                            <form id="library-category-form" class="mb-3">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="library-category-name" class="form-control" placeholder="{{ __('e.g. Science & Technology') }}" required maxlength="191">
                                    <div class="input-group-append">
                                        <button class="btn btn-theme" type="submit"><i class="fa fa-plus"></i></button>
                                    </div>
                                </div>
                            </form>
                            <ul class="list-group list-group-flush small" id="library-category-list">
                                @foreach ($categories as $category)
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        {{ $category->name }}
                                        @can('library-delete')
                                            <button type="button" class="btn btn-xs btn-link text-danger library-category-delete" data-id="{{ $category->id }}"><i class="fa fa-times"></i></button>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endcan

            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Titles') }}</h4>
                        <div class="row" id="toolbar">
                            <div class="form-group col-md-4">
                                <select id="filter_category_id" class="form-control">
                                    <option value="">{{ __('All categories') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <table aria-describedby="library-books" class="table" id="table_list" data-toggle="table"
                               data-url="{{ route('library.books.list') }}" data-side-pagination="server" data-pagination="true"
                               data-page-list="[10, 20, 50, 100]" data-search="true" data-toolbar="#toolbar"
                               data-show-columns="true" data-show-refresh="true" data-mobile-responsive="true"
                               data-sort-name="id" data-sort-order="desc" data-query-params="libraryBookQueryParams">
                            <thead>
                            <tr>
                                <th data-field="id" data-visible="false">{{ __('id') }}</th>
                                <th data-field="no">{{ __('no.') }}</th>
                                <th data-field="title" data-sortable="true" data-formatter="libraryTitleFormatter">{{ __('Title') }}</th>
                                <th data-field="author" data-sortable="true">{{ __('Author') }}</th>
                                <th data-field="isbn">{{ __('ISBN') }}</th>
                                <th data-field="category">{{ __('Category') }}</th>
                                <th data-field="location">{{ __('Location') }}</th>
                                <th data-field="available" data-formatter="libraryAvailabilityFormatter">{{ __('On shelf') }}</th>
                                <th data-field="operate" data-escape="false" data-events="libraryBookEvents">{{ __('action') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Edit title') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form class="edit-form" action="" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="modal-body">
                        @include('library::partials.book-fields', ['p' => 'edit_'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                        <input class="btn btn-theme" type="submit" value="{{ __('submit') }}">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="library-copies-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-barcode mr-1"></i> <span id="library-copies-title"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @can('library-create')
                        <form id="library-add-copies" class="form-inline mb-3" style="gap: 6px;">
                            <label class="small">{{ __('Add') }}</label>
                            <input type="number" id="library-copy-count" class="form-control form-control-sm" value="1" min="1" max="200" style="width: 80px;">
                            <select id="library-copy-acquisition" class="form-control form-control-sm">
                                <option value="Purchase">{{ __('Purchase') }}</option>
                                <option value="Donation">{{ __('Donation') }}</option>
                                <option value="Govt_Grant">{{ __('Government grant') }}</option>
                            </select>
                            <button class="btn btn-sm btn-theme" type="submit">{{ __('Add copies') }}</button>
                            <button class="btn btn-sm btn-outline-secondary ml-auto" type="button" id="library-print-labels"><i class="fa fa-print mr-1"></i> {{ __('Print labels') }}</button>
                        </form>
                    @endcan
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('Barcode') }}</th>
                            <th>{{ __('Accession') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Condition') }}</th>
                            <th>{{ __('Borrower') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="library-copies-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.LibraryCatalog = {
            copiesUrl: "{{ url('library/books') }}/:id/copies",
            copyUrl: "{{ url('library/copies') }}/:id",
            ebookUrl: "{{ url('library/books') }}/:id/ebook",
            categoryStoreUrl: "{{ route('library.categories.store') }}",
            categoryUrl: "{{ url('library/categories') }}/:id",
            canEdit: @json(Auth::user()->can('library-edit')),
            canDelete: @json(Auth::user()->can('library-delete')),
            statuses: ['Available', 'Lost', 'Damaged', 'Under_Repair'],
            conditions: ['New', 'Good', 'Fair', 'Poor'],
        };
    </script>
    <script src="{{ asset('assets/js/custom/library.js') }}"></script>
@endsection
