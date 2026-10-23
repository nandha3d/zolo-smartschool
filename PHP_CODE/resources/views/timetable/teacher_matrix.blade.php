@extends('layouts.master')

@section('title')
    {{ __('Teacher Matrix') }}
@endsection

@section('css')
<style>
    .tm-wrap { overflow: auto; max-height: 72vh; border: 1px solid #e5e7eb; border-radius: 10px; }
    .tm-table { border-collapse: separate; border-spacing: 0; font-size: 12px; min-width: 100%; }
    .tm-table th, .tm-table td { border-right: 1px solid #eef0f3; border-bottom: 1px solid #eef0f3; padding: 3px; vertical-align: top; }
    .tm-table thead th { position: sticky; background: var(--theme-color, #22577A); color: #ffffff; z-index: 2; text-align: center; white-space: nowrap; }
    .tm-table thead tr:first-child th { top: 0; font-weight: 700; font-size: 13px; }
    .tm-table thead tr:nth-child(2) th { top: 26px; font-weight: 600; color: rgba(255, 255, 255, 0.88); background: #1B4461; }
    .tm-table .tm-teacher { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 170px; font-weight: 600; white-space: nowrap; }
    .tm-table thead .tm-teacher { z-index: 3; background: #1B4461; color: #ffffff; }
    .tm-day-start { border-left: 2px solid #cbd5e1 !important; }
    .tm-cell { min-width: 92px; height: 46px; }
    .tm-cell.tm-break { background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 6px, #f1f5f9 6px, #f1f5f9 12px); }
    .tm-cell.tm-drop-ok { background: #dcfce7; }
    .tm-cell.tm-drop-swap { background: #fef9c3; }
    .tm-cell.tm-drop-bad { background: #fee2e2; }
    .tm-lesson { border-radius: 6px; padding: 3px 5px; color: #fff; line-height: 1.25; cursor: grab; margin-bottom: 2px; }
    .tm-lesson.tm-locked { cursor: not-allowed; opacity: .8; }
    .tm-lesson.tm-clash { outline: 2px solid #dc2626; outline-offset: 1px; }
    .tm-lesson small { display: block; opacity: .9; }
    .tm-load { font-weight: 400; color: #64748b; font-size: 11px; }
    .tm-legend span { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: middle; margin: 0 4px 0 12px; }
</style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Teacher Matrix') }}</h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-end mb-3" style="gap: 10px;">
                            <div>
                                <label class="small font-weight-bold mb-1">{{ __('Find teacher') }}</label>
                                <input type="search" id="tm-search" class="form-control form-control-sm" placeholder="{{ __('Name…') }}">
                            </div>
                            <div>
                                <label class="small font-weight-bold mb-1">{{ __('Day') }}</label>
                                <select id="tm-day-filter" class="form-control form-control-sm">
                                    <option value="">{{ __('All days') }}</option>
                                </select>
                            </div>
                            <div class="custom-control custom-checkbox mb-1">
                                <input type="checkbox" class="custom-control-input" id="tm-clash-only">
                                <label class="custom-control-label small" for="tm-clash-only">{{ __('Only teachers with clashes') }}</label>
                            </div>
                            <div class="ml-auto d-flex" style="gap: 6px;">
                                <button type="button" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#tm-substitute-modal">
                                    <i class="fa fa-user-plus mr-1"></i> {{ __('Find Substitutes') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tm-reload">
                                    <i class="fa fa-refresh"></i>
                                </button>
                            </div>
                        </div>

                        <div id="tm-summary" class="small text-muted mb-2"></div>
                        <div class="small text-muted mb-2 tm-legend">
                            @if($canEdit)
                                {{ __('Drag a lesson to another period in the same row to move it.') }}
                            @endif
                            <span style="background:#dcfce7"></span>{{ __('free') }}
                            <span style="background:#fef9c3"></span>{{ __('swap with the class\'s lesson') }}
                            <span style="background:#fee2e2"></span>{{ __('clash') }}
                            <span style="outline:2px solid #dc2626"></span>{{ __('double-booked now') }}
                        </div>

                        <div class="tm-wrap">
                            <table class="tm-table" id="tm-table">
                                <thead></thead>
                                <tbody>
                                    <tr><td class="p-4 text-center text-muted">{{ __('Loading…') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tm-substitute-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fa fa-user-plus text-primary mr-1"></i> {{ __('Substitute Teacher Finder') }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">{{ __('Absent teacher') }}</label>
                            <select id="tm-sub-teacher" class="form-control form-control-sm"></select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">{{ __('Date') }}</label>
                            <input type="date" id="tm-sub-date" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-md-2 form-group d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-primary btn-block" id="tm-sub-find">{{ __('Find') }}</button>
                        </div>
                    </div>
                    <div id="tm-sub-result" class="small"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.ZoloTeacherMatrix = {
            dataUrl: "{{ route('timetable.teacher-matrix.data') }}",
            moveUrl: "{{ route('timetable.move') }}",
            substitutesUrl: "{{ route('timetable.substitutes') }}",
            csrf: "{{ csrf_token() }}",
            canEdit: @json($canEdit),
        };
    </script>
    <script src="{{ asset('assets/js/custom/timetable-matrix.js') }}"></script>
@endsection
