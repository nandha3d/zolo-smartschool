@extends('layouts.master')

@section('title')
    {{ __('Canteen Inventory') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen Inventory') }}</h3>
        </div>
        @include('canteen::partials.nav')

        @can('canteen-create')
            <div class="card mb-3"><div class="card-body">
                <h4 class="card-title">{{ __('Add an ingredient') }}</h4>
                <form data-canteen-form data-after="reload" action="{{ route('canteen.inventory.store') }}" method="POST">
                    <div class="row align-items-end">
                        <div class="form-group col-md-3"><label class="small">{{ __('Name') }} *</label><input name="name" class="form-control form-control-sm" required maxlength="191" placeholder="{{ __('e.g. Basmati rice') }}"></div>
                        <div class="form-group col-md-1"><label class="small">{{ __('Unit') }}</label>
                            <select name="unit" class="form-control form-control-sm">@foreach (['kg', 'g', 'litres', 'ml', 'pcs', 'packets', 'dozen'] as $u)<option>{{ $u }}</option>@endforeach</select></div>
                        <div class="form-group col-md-2"><label class="small">{{ __('Opening stock') }}</label><input type="number" name="current_stock" class="form-control form-control-sm" min="0" step="0.001" value="0"></div>
                        <div class="form-group col-md-2"><label class="small">{{ __('Reorder at') }}</label><input type="number" name="reorder_level" class="form-control form-control-sm" min="0" step="0.001" value="0"></div>
                        <div class="form-group col-md-1"><label class="small">{{ __('Unit cost') }}</label><input type="number" name="unit_cost" class="form-control form-control-sm" min="0" step="0.01"></div>
                        <div class="form-group col-md-2"><label class="small">{{ __('Supplier') }}</label><input name="supplier" class="form-control form-control-sm" maxlength="191"></div>
                        <div class="form-group col-md-1"><button class="btn btn-sm btn-theme btn-block" type="submit">{{ __('Add') }}</button></div>
                    </div>
                </form>
            </div></div>
        @endcan

        <div class="card"><div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Ingredient') }}</th><th>{{ __('In stock') }}</th><th>{{ __('Reorder at') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Record movement') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $item)
                    @php($low = (float) $item->current_stock <= (float) $item->reorder_level)
                    <tr class="{{ $low ? 'table-warning' : '' }}">
                        <td>{{ $item->name }}</td>
                        <td>{{ rtrim(rtrim(number_format($item->current_stock, 3), '0'), '.') }} {{ $item->unit }} @if ($low)<span class="badge badge-danger">{{ __('low') }}</span>@endif</td>
                        <td>{{ rtrim(rtrim(number_format($item->reorder_level, 3), '0'), '.') }}</td>
                        <td>{{ $item->supplier }}</td>
                        <td>
                            @can('canteen-edit')
                                <form data-canteen-form data-after="reload" action="{{ route('canteen.inventory.move', $item->id) }}" method="POST" class="form-inline" style="gap: 4px;">
                                    <select name="reason" class="form-control form-control-sm"><option>Purchase</option><option>Usage</option><option>Wastage</option><option>Correction</option></select>
                                    <select name="direction" class="form-control form-control-sm"><option value="in">+</option><option value="out">−</option></select>
                                    <input type="number" name="quantity" class="form-control form-control-sm" min="0.001" step="0.001" style="width: 90px;" required>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">{{ __('Save') }}</button>
                                </form>
                            @endcan
                        </td>
                        <td>@can('canteen-delete')<button type="button" class="btn btn-xs btn-link text-danger" data-canteen-delete="{{ route('canteen.inventory.destroy', $item->id) }}"><i class="fa fa-trash"></i></button>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('No ingredients recorded yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="small text-muted mb-0">{{ __('Purchase adds stock, Usage and Wastage remove it; Correction follows the +/− choice. Every movement is logged.') }}</p>
        </div></div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
