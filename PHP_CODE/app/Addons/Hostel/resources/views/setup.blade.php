@extends('layouts.master')

@section('title')
    {{ __('Blocks & Rooms') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Blocks & Rooms') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="row">
            <div class="col-lg-7 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Blocks') }}</h4>
                        <table class="table table-sm small">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Floors') }}</th><th>{{ __('Warden') }}</th><th>{{ __('Rooms') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($blocks as $block)
                                <tr>
                                    <td><strong>{{ $block->name }}</strong> <span class="text-muted">{{ $block->code }}</span></td>
                                    <td>{{ $block->type }}</td>
                                    <td>{{ $block->total_floors }}</td>
                                    <td>{{ $block->warden?->full_name }}</td>
                                    <td>{{ $block->rooms_count }}</td>
                                    <td class="text-nowrap">
                                        @can('hostel-delete')
                                            <button type="button" class="btn btn-xs btn-link text-danger" data-hostel-delete="{{ route('hostel.blocks.destroy', $block->id) }}"><i class="fa fa-trash"></i></button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">{{ __('No blocks yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                        @can('hostel-create')
                            <form data-hostel-form data-after="reload" action="{{ route('hostel.blocks.store') }}" method="POST" class="border-top pt-3">
                                <div class="row">
                                    <div class="form-group col-md-4"><label class="small">{{ __('Name') }} *</label><input name="name" class="form-control form-control-sm" required maxlength="191" placeholder="{{ __('e.g. Tagore House') }}"></div>
                                    <div class="form-group col-md-2"><label class="small">{{ __('Code') }}</label><input name="code" class="form-control form-control-sm" maxlength="32"></div>
                                    <div class="form-group col-md-2"><label class="small">{{ __('Type') }}</label>
                                        <select name="type" class="form-control form-control-sm">@foreach (\App\Addons\Hostel\Models\HostelBlock::TYPES as $type)<option>{{ $type }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-1"><label class="small">{{ __('Floors') }}</label><input type="number" name="total_floors" class="form-control form-control-sm" value="1" min="1" max="100"></div>
                                    <div class="form-group col-md-3"><label class="small">{{ __('Warden') }}</label>
                                        <select name="warden_id" class="form-control form-control-sm"><option value="">—</option>@foreach ($wardens as $w)<option value="{{ $w->id }}">{{ $w->full_name }}</option>@endforeach</select></div>
                                </div>
                                <button class="btn btn-sm btn-theme" type="submit"><i class="fa fa-plus mr-1"></i> {{ __('Add block') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="col-lg-5 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Room types & fees') }}</h4>
                        <table class="table table-sm small">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Amenities') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse ($roomTypes as $type)
                                <tr>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ number_format($type->base_fee, 2) }} / {{ strtolower($type->fee_cycle) }}</td>
                                    <td>{{ implode(', ', $type->amenities ?? []) }}</td>
                                    <td>@can('hostel-delete')<button type="button" class="btn btn-xs btn-link text-danger" data-hostel-delete="{{ route('hostel.room-types.destroy', $type->id) }}"><i class="fa fa-trash"></i></button>@endcan</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">{{ __('No room types yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                        @can('hostel-create')
                            <form data-hostel-form data-after="reload" action="{{ route('hostel.room-types.store') }}" method="POST" class="border-top pt-3">
                                <div class="row">
                                    <div class="form-group col-md-6"><label class="small">{{ __('Name') }} *</label><input name="name" class="form-control form-control-sm" required placeholder="{{ __('AC 2-bed attached') }}"></div>
                                    <div class="form-group col-md-3"><label class="small">{{ __('Fee') }}</label><input type="number" name="base_fee" class="form-control form-control-sm" min="0" step="0.01" value="0"></div>
                                    <div class="form-group col-md-3"><label class="small">{{ __('Cycle') }}</label>
                                        <select name="fee_cycle" class="form-control form-control-sm"><option>Monthly</option><option>Quarterly</option><option>Annual</option></select></div>
                                    <div class="form-group col-md-12"><label class="small">{{ __('Amenities (comma separated)') }}</label><input name="amenities" class="form-control form-control-sm" placeholder="Wi-Fi, AC, Attached bath, Study desk"></div>
                                </div>
                                <button class="btn btn-sm btn-theme" type="submit"><i class="fa fa-plus mr-1"></i> {{ __('Add room type') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center flex-wrap mb-3" style="gap: 10px;">
                            <h4 class="card-title mb-0">{{ __('Rooms') }}</h4>
                            <select id="setup-block" class="form-control form-control-sm" style="max-width: 260px;">
                                @foreach ($blocks as $block)<option value="{{ $block->id }}">{{ $block->name }}</option>@endforeach
                            </select>
                        </div>
                        @can('hostel-create')
                            @if ($blocks->isNotEmpty())
                                <form data-hostel-form data-after="rooms" action="{{ route('hostel.rooms.store') }}" method="POST" class="mb-3" id="setup-room-form">
                                    <input type="hidden" name="block_id" id="setup-room-block">
                                    <div class="row align-items-end">
                                        <div class="form-group col-md-2"><label class="small">{{ __('Room no.') }} *</label><input name="room_number" class="form-control form-control-sm" required placeholder="101"></div>
                                        <div class="form-group col-md-2"><label class="small">{{ __('to (optional)') }}</label><input type="number" name="room_number_to" class="form-control form-control-sm" placeholder="110"></div>
                                        <div class="form-group col-md-2"><label class="small">{{ __('Floor') }}</label><input type="number" name="floor" class="form-control form-control-sm" value="1" min="0"></div>
                                        <div class="form-group col-md-2"><label class="small">{{ __('Beds per room') }}</label><input type="number" name="bed_capacity" class="form-control form-control-sm" value="2" min="1" max="30"></div>
                                        <div class="form-group col-md-2"><label class="small">{{ __('Room type') }}</label>
                                            <select name="room_type_id" class="form-control form-control-sm"><option value="">—</option>@foreach ($roomTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                                        <div class="form-group col-md-2"><button class="btn btn-sm btn-theme btn-block" type="submit">{{ __('Add rooms') }}</button></div>
                                    </div>
                                </form>
                            @endif
                        @endcan
                        <div id="setup-rooms" class="row"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.HostelSetup = {
            roomsUrl: "{{ route('hostel.rooms') }}",
            roomUrl: "{{ url('hostel/rooms') }}/:id",
            canEdit: @json(Auth::user()->can('hostel-edit')),
            canDelete: @json(Auth::user()->can('hostel-delete')),
        };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
