@extends('layouts.master')

@section('title')
    {{ __('id_card_photo_booth') }}
@endsection

@section('css')
    {{-- Colours, radii and type all come from the Zolo tokens in zolo-theme.css, so the
         booth follows the school's theme instead of carrying its own palette. --}}
    <style>
        .booth-stage {
            position: relative;
            background: var(--zolo-ink);
            border-radius: var(--zolo-radius-lg);
            overflow: hidden;
            aspect-ratio: 3 / 4;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .booth-stage video, .booth-stage canvas { width: 100%; height: 100%; object-fit: cover; }
        .booth-stage .booth-idle {
            color: var(--zolo-muted-3); font-size: 13px; text-align: center; padding: 20px;
        }
        /* Portrait guide so operators frame heads consistently across a whole class. */
        .booth-guide {
            position: absolute; inset: 0; pointer-events: none;
            border: 2px dashed rgba(255, 255, 255, .35);
            border-radius: var(--zolo-radius-lg);
            margin: 8% 14%;
        }
        .booth-roster { max-height: 620px; overflow-y: auto; }
        .booth-row {
            display: flex; align-items: center; gap: 12px; padding: 9px 12px;
            border-bottom: 1px solid var(--zolo-border-soft-2); cursor: pointer;
        }
        .booth-row:hover { background: var(--zolo-bg); }
        .booth-row.is-active { background: var(--zolo-accent-soft); }
        .booth-thumb {
            width: 40px; height: 40px; border-radius: var(--zolo-radius-sm); object-fit: cover;
            background: var(--zolo-border-soft); flex: none;
        }
        .booth-name { flex: 1; min-width: 0; font-size: 13.5px; font-weight: 600; }
        .booth-roll { font-size: 11.5px; color: var(--zolo-muted-2); }
    </style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="zolo-page-header">
            <h1 class="zolo-heading zolo-page-title">{{ __('id_card_photo_booth') }}</h1>
        </div>

        <div class="row">
            <div class="col-lg-5 col-xl-4 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('class_section') }}</h4>
                    <div class="form-group">
                        <select id="booth-class-section" class="form-control">
                            <option value="">{{ __('select_class_section') }}</option>
                            @foreach ($class_sections as $class_section)
                                <option value="{{ $class_section->id }}">{{ $class_section->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="booth-summary" class="zolo-card-subtitle"></div>
                    <div class="booth-roster" id="booth-roster"></div>
                </div></div>
            </div>

            <div class="col-lg-7 col-xl-8 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title" id="booth-heading">{{ __('photo_booth_select_student') }}</h4>
                    <div class="zolo-card-subtitle" id="booth-hint">{{ __('photo_booth_intro') }}</div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="booth-stage" id="booth-stage">
                                <div class="booth-idle" id="booth-idle">{{ __('photo_booth_camera_off') }}</div>
                                <video id="booth-video" autoplay playsinline muted hidden></video>
                                <canvas id="booth-canvas" hidden></canvas>
                                <div class="booth-guide" id="booth-guide" hidden></div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>{{ __('photo_booth_camera') }}</label>
                                <select id="booth-device" class="form-control"></select>
                            </div>
                            <button class="btn btn-secondary btn-block mb-2" id="booth-start">{{ __('photo_booth_start_camera') }}</button>
                            <button class="btn btn-primary btn-block mb-2" id="booth-capture" disabled>{{ __('photo_booth_capture') }}</button>
                            <button class="btn btn-secondary btn-block mb-2" id="booth-retake" disabled>{{ __('photo_booth_retake') }}</button>
                            <button class="btn btn-primary btn-block" id="booth-save" disabled>{{ __('photo_booth_save_photo') }}</button>
                            <div id="booth-status" class="zolo-card-subtitle mt-2"></div>
                        </div>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            const rosterUrl  = "{{ route('photo-booth.roster') }}";
            const captureUrl = "{{ route('photo-booth.capture') }}";
            const csrf       = "{{ csrf_token() }}";

            // Every string this script can put on screen, translated server-side, so the
            // panel stays in the operator's language instead of falling back to English.
            const T = @json([
                'summary'          => __('photo_booth_roster_summary'),
                'roll'             => __('photo_booth_roll_number'),
                'has_photo'        => __('photo_booth_has_photo'),
                'missing_photo'    => __('photo_booth_missing_photo'),
                'roster_failed'    => __('photo_booth_roster_failed'),
                'pick_student'     => __('photo_booth_pick_student'),
                'captured'         => __('photo_booth_captured'),
                'saving'           => __('photo_booth_saving'),
                'saved'            => __('photo_booth_saved'),
                'save_failed'      => __('photo_booth_save_failed'),
                'no_camera_api'    => __('photo_booth_no_camera_api'),
                'denied'           => __('photo_booth_permission_denied'),
                'camera_failed'    => __('photo_booth_camera_failed'),
            ]);

            const el = id => document.getElementById(id);
            const video = el('booth-video'), canvas = el('booth-canvas');
            let stream = null, selected = null, pendingBlob = null;

            function status(msg, kind) {
                el('booth-status').textContent = msg || '';
                el('booth-status').style.color = kind === 'bad' ? 'var(--zolo-bad-ink)'
                    : kind === 'ok' ? 'var(--zolo-ok-ink)' : 'var(--zolo-muted-2)';
            }

            function setButtons({ capture, retake, save }) {
                el('booth-capture').disabled = !capture;
                el('booth-retake').disabled = !retake;
                el('booth-save').disabled = !save;
            }

            // ---- roster -------------------------------------------------------

            /**
             * Built as DOM nodes rather than an HTML string: student names are
             * free text typed by school staff, and textContent cannot be talked
             * into becoming markup.
             */
            function buildRow(s) {
                const row = document.createElement('div');
                row.className = 'booth-row';
                row.dataset.id = s.student_id;
                row.dataset.name = s.name;

                const thumb = document.createElement('img');
                thumb.className = 'booth-thumb';
                thumb.alt = '';
                if (s.image) thumb.src = s.image;

                const name = document.createElement('div');
                name.className = 'booth-name';
                name.textContent = s.name;

                const roll = document.createElement('div');
                roll.className = 'booth-roll';
                roll.textContent = `${T.roll} ${s.roll_number ?? '—'}`;
                name.appendChild(roll);

                const badge = document.createElement('span');
                badge.className = 'zolo-badge ' + (s.has_photo ? 'zolo-badge-ok' : 'zolo-badge-warn');
                badge.textContent = s.has_photo ? T.has_photo : T.missing_photo;

                row.append(thumb, name, badge);
                return row;
            }

            el('booth-class-section').addEventListener('change', function () {
                const id = this.value;
                el('booth-roster').innerHTML = '';
                el('booth-summary').textContent = '';
                if (!id) return;

                fetch(`${rosterUrl}?class_section_id=${encodeURIComponent(id)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.error) { status(res.message, 'bad'); return; }
                        const rows = res.data || [];
                        const missing = rows.filter(s => !s.has_photo).length;
                        el('booth-summary').textContent = T.summary
                            .replace(':total', rows.length)
                            .replace(':missing', missing);
                        el('booth-roster').replaceChildren(...rows.map(buildRow));
                    })
                    .catch(() => status(T.roster_failed, 'bad'));
            });

            el('booth-roster').addEventListener('click', function (e) {
                const row = e.target.closest('.booth-row');
                if (!row) return;
                document.querySelectorAll('.booth-row').forEach(r => r.classList.remove('is-active'));
                row.classList.add('is-active');
                selected = row.dataset.id;
                el('booth-heading').textContent = row.dataset.name;
                pendingBlob = null;
                setButtons({ capture: !!stream, retake: false, save: false });
                status('');
            });

            // ---- camera -------------------------------------------------------
            async function listDevices() {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const cams = devices.filter(d => d.kind === 'videoinput');
                el('booth-device').innerHTML = cams
                    .map((c, i) => `<option value="${c.deviceId}">${c.label || 'Camera ' + (i + 1)}</option>`)
                    .join('');
            }

            el('booth-start').addEventListener('click', async function () {
                if (!navigator.mediaDevices?.getUserMedia) {
                    status(T.no_camera_api, 'bad');
                    return;
                }
                try {
                    if (stream) stream.getTracks().forEach(t => t.stop());
                    const deviceId = el('booth-device').value;
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'user' },
                        audio: false
                    });
                    video.srcObject = stream;
                    video.hidden = false; canvas.hidden = true;
                    el('booth-idle').hidden = true; el('booth-guide').hidden = false;
                    await listDevices();
                    setButtons({ capture: !!selected, retake: false, save: false });
                    status(selected ? '' : T.pick_student);
                } catch (err) {
                    // Denied permission and no camera present are different problems.
                    status(err && err.name === 'NotAllowedError' ? T.denied : T.camera_failed, 'bad');
                }
            });

            el('booth-capture').addEventListener('click', function () {
                if (!stream || !selected) return;
                // Crop the centre to a 3:4 portrait so every card is framed the same.
                const vw = video.videoWidth, vh = video.videoHeight;
                const targetRatio = 3 / 4;
                let sw = vw, sh = Math.round(vw / targetRatio);
                if (sh > vh) { sh = vh; sw = Math.round(vh * targetRatio); }
                const sx = Math.round((vw - sw) / 2), sy = Math.round((vh - sh) / 2);

                canvas.width = 600; canvas.height = 800;
                canvas.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, 600, 800);
                canvas.hidden = false; video.hidden = true;

                canvas.toBlob(blob => {
                    pendingBlob = blob;
                    setButtons({ capture: false, retake: true, save: true });
                    status(T.captured);
                }, 'image/jpeg', 0.9);
            });

            el('booth-retake').addEventListener('click', function () {
                pendingBlob = null;
                canvas.hidden = true; video.hidden = false;
                setButtons({ capture: true, retake: false, save: false });
                status('');
            });

            el('booth-save').addEventListener('click', function () {
                if (!pendingBlob || !selected) return;
                const body = new FormData();
                body.append('student_id', selected);
                body.append('photo', pendingBlob, 'capture.jpg');

                el('booth-save').disabled = true;
                status(T.saving);

                fetch(captureUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.error) { status(res.message, 'bad'); el('booth-save').disabled = false; return; }
                        status(T.saved, 'ok');
                        // Reflect it in the roster without a reload.
                        const row = document.querySelector(`.booth-row[data-id="${selected}"]`);
                        if (row) {
                            row.querySelector('.booth-thumb').src = res.image + '?t=' + Date.now();
                            const badge = row.querySelector('.zolo-badge');
                            badge.className = 'zolo-badge zolo-badge-ok';
                            badge.textContent = T.has_photo;
                        }
                        pendingBlob = null;
                        setButtons({ capture: true, retake: false, save: false });
                        canvas.hidden = true; video.hidden = false;
                    })
                    .catch(() => { status(T.save_failed, 'bad'); el('booth-save').disabled = false; });
            });

            window.addEventListener('beforeunload', () => {
                if (stream) stream.getTracks().forEach(t => t.stop());
            });
        })();
    </script>
@endsection
