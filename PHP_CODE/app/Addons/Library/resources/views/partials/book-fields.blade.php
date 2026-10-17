{{-- Book form fields; $p is an id prefix so the create form and the edit modal can share them. --}}
<div class="row">
    <div class="form-group col-md-6">
        <label>{{ __('Title') }} <span class="text-danger">*</span></label>
        <input type="text" name="title" id="{{ $p }}title" class="form-control" required maxlength="191">
    </div>
    <div class="form-group col-md-6">
        <label>{{ __('Subtitle') }}</label>
        <input type="text" name="subtitle" id="{{ $p }}subtitle" class="form-control" maxlength="191">
    </div>
    <div class="form-group col-md-4">
        <label>{{ __('Author') }}</label>
        <input type="text" name="author" id="{{ $p }}author" class="form-control" maxlength="191">
    </div>
    <div class="form-group col-md-4">
        <label>{{ __('Publisher') }}</label>
        <input type="text" name="publisher" id="{{ $p }}publisher" class="form-control" maxlength="191">
    </div>
    <div class="form-group col-md-4">
        <label>{{ __('ISBN') }}</label>
        <input type="text" name="isbn" id="{{ $p }}isbn" class="form-control" maxlength="17" placeholder="978-…">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Category') }}</label>
        <select name="category_id" id="{{ $p }}category_id" class="form-control library-category-select">
            <option value="">—</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Edition') }}</label>
        <input type="text" name="edition" id="{{ $p }}edition" class="form-control" maxlength="64">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Year') }}</label>
        <input type="number" name="publication_year" id="{{ $p }}publication_year" class="form-control" min="1000" max="{{ date('Y') + 1 }}">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Language') }}</label>
        <input type="text" name="language" id="{{ $p }}language" class="form-control" maxlength="64">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Price') }} <small class="text-muted">({{ __('replacement cost') }})</small></label>
        <input type="number" name="price" id="{{ $p }}price" class="form-control" min="0" step="0.01">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Dewey code') }}</label>
        <input type="text" name="dewey_decimal_code" id="{{ $p }}dewey_decimal_code" class="form-control" maxlength="32" placeholder="530.1">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Rack') }}</label>
        <input type="text" name="rack_location" id="{{ $p }}rack_location" class="form-control" maxlength="64" placeholder="B4">
    </div>
    <div class="form-group col-md-3">
        <label>{{ __('Shelf') }}</label>
        <input type="text" name="shelf_number" id="{{ $p }}shelf_number" class="form-control" maxlength="64" placeholder="2">
    </div>
    <div class="form-group col-md-6">
        <label>{{ __('E-book file') }} <small class="text-muted">(PDF / EPUB, {{ __('served only to signed-in users') }})</small></label>
        <input type="file" name="digital_file" class="form-control" accept=".pdf,.epub,application/pdf,application/epub+zip">
    </div>
    <div class="form-group col-md-6">
        <label>{{ __('Cover image') }}</label>
        <input type="file" name="cover_image" class="form-control" accept="image/png,image/jpeg,image/webp">
    </div>
    <div class="form-group col-md-12">
        <label>{{ __('Description') }}</label>
        <textarea name="description" id="{{ $p }}description" class="form-control" rows="2" maxlength="5000"></textarea>
    </div>
</div>
