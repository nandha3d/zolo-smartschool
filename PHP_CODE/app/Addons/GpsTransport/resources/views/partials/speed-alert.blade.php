{{--
    Overspeed popup with siren, included on every admin page by layouts/master.
    Polls gps.alerts.live (no websocket server is needed) and shows the newest
    unacknowledged overspeed alert. Dismissing hides it for this browser tab only;
    Acknowledge records who saw it.
--}}
<div id="gpsSpeedAlertModal" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" aria-labelledby="gpsSpeedAlertTitle">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border:2px solid #dc3545;">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title font-weight-bold" id="gpsSpeedAlertTitle">
                    <i class="fa fa-exclamation-triangle mr-2"></i>{{ __('Vehicle overspeed alert') }}
                </h5>
                <button type="button" class="close text-white" id="gpsSpeedAlertClose" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body text-center p-3">
                <div class="mb-2">
                    <span class="badge badge-danger p-2 font-weight-bold" style="font-size:20px;" id="gpsSaSpeed">-</span>
                    <div class="text-muted mt-1">{{ __('Allowed') }}: <span id="gpsSaLimit">-</span> km/h · {{ __('Peak') }}: <span id="gpsSaPeak">-</span> km/h</div>
                </div>
                <h6 class="font-weight-bold" id="gpsSaVehicle">-</h6>
                <p class="text-muted mb-2">{{ __('Driver') }}: <span id="gpsSaDriver">-</span></p>
                <div class="p-2 rounded bg-light mb-3 text-left" style="font-size:13px;">
                    <div><strong>{{ __('Route') }}:</strong> <span id="gpsSaRoute">-</span></div>
                    <div><strong>{{ __('Location') }}:</strong> <span id="gpsSaLocation">-</span></div>
                    <div><strong>{{ __('Time') }}:</strong> <span id="gpsSaTime">-</span></div>
                </div>
                <textarea id="gpsSaRemarks" class="form-control form-control-sm mb-2" rows="2" maxlength="500" placeholder="{{ __('Remarks (optional), e.g. called the driver') }}"></textarea>
                <div class="d-flex justify-content-center flex-wrap" style="gap:8px;">
                    <a href="#" id="gpsSaCall" class="btn btn-primary btn-sm"><i class="fa fa-phone mr-1"></i>{{ __('Call driver') }}</a>
                    <a href="#" id="gpsSaMap" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener"><i class="fa fa-map-marker mr-1"></i>{{ __('Live map') }}</a>
                    <button type="button" class="btn btn-success btn-sm" id="gpsSaAck"><i class="fa fa-check mr-1"></i>{{ __('Acknowledge') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var liveUrl = @json(route('gps.alerts.live'));
        var ackUrl = @json(route('gps.alerts.acknowledge', ['id' => '__ID__']));
        var current = null;
        var siren = null;

        function dismissed() {
            try { return JSON.parse(sessionStorage.getItem('gpsSpeedAlertDismissed') || '[]'); } catch (e) { return []; }
        }
        function remember(id) {
            try {
                var ids = dismissed();
                ids.push(id);
                sessionStorage.setItem('gpsSpeedAlertDismissed', JSON.stringify(ids.slice(-50)));
            } catch (e) {}
        }

        // Two-tone siren generated in the browser, so no audio file has to be shipped.
        function startSiren() {
            try {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx || siren) { return; }
                var ctx = new Ctx();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                gain.gain.value = 0.15;
                osc.type = 'square';
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                var high = true;
                var timer = setInterval(function () {
                    osc.frequency.setValueAtTime(high ? 960 : 620, ctx.currentTime);
                    high = !high;
                }, 450);
                siren = {ctx: ctx, osc: osc, timer: timer};
                // Stop on its own after 20 seconds so a forgotten tab does not wail forever.
                setTimeout(stopSiren, 20000);
            } catch (e) {}
        }
        function stopSiren() {
            if (!siren) { return; }
            clearInterval(siren.timer);
            try { siren.osc.stop(); siren.ctx.close(); } catch (e) {}
            siren = null;
        }

        function show(a) {
            current = a;
            $('#gpsSaSpeed').text(a.speed + ' km/h');
            $('#gpsSaLimit').text(a.speed_limit !== null ? a.speed_limit : '-');
            $('#gpsSaPeak').text(a.peak_speed);
            $('#gpsSaVehicle').text(a.vehicle_number);
            $('#gpsSaDriver').text(a.driver_name + (a.driver_phone ? ' (' + a.driver_phone + ')' : ''));
            $('#gpsSaRoute').text(a.route_name);
            $('#gpsSaLocation').text(a.latitude !== null ? a.latitude + ', ' + a.longitude : '-');
            $('#gpsSaTime').text(a.created_at);
            $('#gpsSaRemarks').val('');
            var phone = String(a.driver_phone || '').replace(/[^0-9+]/g, '');
            $('#gpsSaCall').attr('href', phone ? 'tel:' + phone : '#').toggleClass('disabled', !phone);
            $('#gpsSaMap').attr('href', a.map_url);
            $('#gpsSpeedAlertModal').modal('show');
            startSiren();
        }

        function close() {
            stopSiren();
            if (current) { remember(current.id); }
            current = null;
            $('#gpsSpeedAlertModal').modal('hide');
        }

        function poll() {
            if (current) { return; }
            $.get(liveUrl).done(function (res) {
                if (!res || res.error || !res.alerts) { return; }
                var skip = dismissed();
                var next = res.alerts.find(function (a) { return skip.indexOf(a.id) === -1; });
                if (next) { show(next); }
            });
        }

        $('#gpsSpeedAlertClose').on('click', close);
        $('#gpsSaAck').on('click', function () {
            if (!current) { return; }
            var btn = $(this).prop('disabled', true);
            $.post(ackUrl.replace('__ID__', current.id), {
                _token: $('meta[name="csrf-token"]').attr('content'),
                remarks: $('#gpsSaRemarks').val()
            }).always(function () {
                btn.prop('disabled', false);
                close();
            });
        });

        $(function () {
            poll();
            setInterval(poll, 15000);
        });
    })();
</script>
