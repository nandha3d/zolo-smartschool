{{-- Menu item fields; $p prefixes ids so the create form and the edit modal can share them. --}}
<div class="row">
    <div class="form-group col-md-4"><label>{{ __('Name') }} *</label><input name="name" id="{{ $p }}name" class="form-control" required maxlength="191"></div>
    <div class="form-group col-md-2"><label>{{ __('Code / barcode') }}</label><input name="code" id="{{ $p }}code" class="form-control" maxlength="32"></div>
    <div class="form-group col-md-3"><label>{{ __('Category') }}</label>
        <select name="category_id" id="{{ $p }}category_id" class="form-control"><option value="">—</option>
            @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
        </select></div>
    <div class="form-group col-md-3"><label>{{ __('Type') }}</label>
        <select name="dietary_type" id="{{ $p }}dietary_type" class="form-control">
            @foreach (\App\Addons\Canteen\Models\CanteenItem::DIETARY as $diet)<option>{{ $diet }}</option>@endforeach
        </select></div>
    <div class="form-group col-md-2"><label>{{ __('Price') }} *</label><input type="number" name="price" id="{{ $p }}price" class="form-control" min="0" step="0.01" required></div>
    <div class="form-group col-md-2"><label>{{ __('Cost') }}</label><input type="number" name="cost_price" id="{{ $p }}cost_price" class="form-control" min="0" step="0.01"></div>
    <div class="form-group col-md-2"><label>{{ __('Calories') }}</label><input type="number" name="calories" id="{{ $p }}calories" class="form-control" min="0"></div>
    <div class="form-group col-md-3"><label>{{ __('Photo') }}</label><input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
    <div class="form-group col-md-3">
        <label class="d-block">{{ __('Stock') }}</label>
        <div class="custom-control custom-checkbox d-inline-block mr-2">
            <input type="hidden" name="track_stock" value="0">
            <input type="checkbox" class="custom-control-input" id="{{ $p }}track_stock" name="track_stock" value="1">
            <label class="custom-control-label small" for="{{ $p }}track_stock">{{ __('count portions') }}</label>
        </div>
        <input type="number" name="stock_qty" id="{{ $p }}stock_qty" class="form-control form-control-sm d-inline-block" style="width: 90px;" min="0" value="0">
    </div>
    <div class="form-group col-md-12">
        <label class="d-block">{{ __('Allergens') }}</label>
        @foreach (\App\Addons\Canteen\Models\CanteenItem::ALLERGENS as $allergen)
            <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input {{ $p }}allergen" id="{{ $p }}allergen_{{ \Illuminate\Support\Str::slug($allergen) }}" name="allergens[]" value="{{ $allergen }}">
                <label class="custom-control-label small" for="{{ $p }}allergen_{{ \Illuminate\Support\Str::slug($allergen) }}">{{ $allergen }}</label>
            </div>
        @endforeach
    </div>
    <div class="form-group col-md-12">
        <div class="custom-control custom-checkbox">
            <input type="hidden" name="is_available" value="0">
            <input type="checkbox" class="custom-control-input" id="{{ $p }}is_available" name="is_available" value="1" checked>
            <label class="custom-control-label small" for="{{ $p }}is_available">{{ __('Available on the menu') }}</label>
        </div>
    </div>
</div>
