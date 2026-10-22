<!DOCTYPE html>
@php
    $lang = Session::get('language');
@endphp
<html lang="{{ $lang->code ?? 'en' }}" dir="{{ $lang && $lang->is_rtl ? 'rtl' : 'ltr' }}">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('/assets/bootstrap5/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/home_page/css/style.css') }}" rel="stylesheet">

    <title>{{ __('login') }} || {{ config('app.name') }}</title>

    @include('layouts.include')

    <style>
        :root {
            --primary-color:
                {{ $systemSettings['theme_primary_color'] ?? '#56cc99' }}
            ;
            --secondary-color:
                {{ $systemSettings['theme_secondary_color'] ?? '#215679' }}
            ;
            --secondary-color1:
                {{ $systemSettings['theme_secondary_color_1'] ?? '#38a3a5' }}
            ;
            --primary-background-color:
                {{ $systemSettings['theme_primary_background_color'] ?? '#f2f5f7' }}
            ;
            --text--secondary-color:
                {{ $systemSettings['theme_text_secondary_color'] ?? '#5c788c' }}
            ;

        }

        .modal .modal-dialog {
            margin-top: unset !important;
        }

        a {
            color: #007bff !important;
        }

        .form-check .form-check-label input {
            opacity: 1 !important;
        }
    <style>
        :root {
            --primary-color: {{ $systemSettings['theme_primary_color'] ?? '#2563EB' }};
            --secondary-color: {{ $systemSettings['theme_secondary_color'] ?? '#1E293B' }};
        }

        .modal .modal-dialog {
            margin-top: unset !important;
        }

        a {
            color: #2563EB !important;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }

        /* Smart Login Layout */
        .zolo-login-shell {
            display: flex;
            min-height: 100vh;
            background: #F8FAFC;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Left Showcase Aside */
        .zolo-login-aside {
            flex: 1 1 540px;
            background: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.22) 0%, transparent 45%),
                        radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.18) 0%, transparent 45%),
                        #0B132B;
            color: #FFFFFF;
            padding: 48px 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .zolo-login-aside::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            opacity: 0.6;
        }

        .smart-aside-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }

        .zolo-login-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .zolo-login-logo {
            height: 38px;
            max-width: 200px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .zolo-login-mark {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }

        .zolo-login-brand-name {
            font-weight: 700;
            font-size: 19px;
            letter-spacing: -0.4px;
            color: #FFFFFF;
        }

        .smart-pulse-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 13px;
            background: rgba(37, 99, 235, 0.16);
            border: 1px solid rgba(96, 165, 250, 0.35);
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            color: #93C5FD;
            backdrop-filter: blur(8px);
        }

        .smart-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10B981;
            box-shadow: 0 0 8px #10B981;
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        .smart-aside-hero {
            position: relative;
            z-index: 2;
            margin: 36px 0;
        }

        .smart-hero-kicker {
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #60A5FA;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .smart-hero-title {
            font-size: 32px;
            line-height: 1.22;
            font-weight: 800;
            letter-spacing: -1px;
            color: #FFFFFF;
            margin-bottom: 12px;
            max-width: 22ch;
        }

        .smart-gradient-text {
            background: linear-gradient(135deg, #60A5FA 0%, #A78BFA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .smart-hero-desc {
            font-size: 14.5px;
            line-height: 1.6;
            color: #94A3B8;
            max-width: 48ch;
            margin-bottom: 24px;
        }

        /* 2D Vector Graphic Presentation Frame */
        .smart-vector-frame {
            position: relative;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 8px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .smart-vector-img {
            width: 100%;
            height: auto;
            max-height: 250px;
            object-fit: cover;
            border-radius: 12px;
            display: block;
            transition: transform 0.4s ease;
        }

        .smart-vector-frame:hover .smart-vector-img {
            transform: scale(1.02);
        }

        .smart-vector-floating-card {
            position: absolute;
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 9px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            z-index: 3;
            transition: all 0.3s ease;
        }

        .smart-vector-floating-card.top-left {
            top: 18px;
            left: 18px;
        }

        .smart-vector-floating-card.bottom-right {
            bottom: 18px;
            right: 18px;
        }

        .smart-vector-floating-card .card-num {
            font-size: 13px;
            font-weight: 700;
            color: #FFFFFF;
            line-height: 1.1;
        }

        .smart-vector-floating-card .card-lbl {
            font-size: 10px;
            color: #94A3B8;
        }

        .smart-feature-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .smart-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            font-size: 12.5px;
            color: #E2E8F0;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .smart-chip:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(96, 165, 250, 0.4);
        }

        .smart-chip .mdi {
            font-size: 17px;
            color: #60A5FA;
        }

        .smart-aside-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            color: #64748B;
            position: relative;
            z-index: 2;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
        }

        /* Right Authentication Panel */
        .zolo-login-panel {
            flex: 1 1 480px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            background: #F8FAFC;
        }

        .zolo-login-card {
            width: 100%;
            max-width: 440px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 38px 36px;
            box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .zolo-login-h2 {
            font-size: 25px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.6px;
            margin: 0;
        }

        .zolo-login-sub {
            font-size: 13.5px;
            color: #64748B;
            margin-top: 5px;
            margin-bottom: 22px;
        }

        /* Segmented Demo Role Bar */
        .smart-role-selector {
            background: #F1F5F9;
            padding: 4px;
            border-radius: 12px;
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border: 1px solid #E2E8F0;
        }

        .smart-role-btn {
            flex: 1;
            padding: 8px 6px;
            background: transparent;
            border: none;
            border-radius: 9px;
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .smart-role-btn:hover {
            color: #0F172A;
            background: rgba(255, 255, 255, 0.6);
        }

        .smart-role-btn.active {
            background: #FFFFFF;
            color: #2563EB;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.07);
        }

        .smart-role-btn .mdi {
            font-size: 16px;
        }

        .smart-role-badge-tip {
            font-size: 11px;
            padding: 4px 10px;
            background: #EFF6FF;
            color: #1D4ED8;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
        }

        /* Form Inputs */
        .zolo-login-field {
            margin-bottom: 16px;
        }

        .zolo-login-field-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .zolo-login-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .zolo-login-label-row .zolo-login-field-label {
            margin-bottom: 0;
        }

        .zolo-login-link {
            font-size: 12px;
            font-weight: 600;
            color: #2563EB !important;
        }

        .zolo-login-input-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 3px 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .zolo-login-input-wrap:focus-within {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
        }

        .zolo-login-input-wrap input {
            flex: 1;
            min-width: 0;
            border: 0 !important;
            outline: none;
            background: none !important;
            font-size: 14px;
            color: #0F172A;
            box-shadow: none !important;
            padding: 10px 0 !important;
        }

        .zolo-login-input-wrap .mdi {
            font-size: 19px;
            color: #94A3B8;
        }

        .zolo-toggle-eye {
            cursor: pointer;
            color: #64748B;
            padding: 4px;
        }

        .zolo-login-submit {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            font-size: 14.5px !important;
            font-weight: 700 !important;
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .zolo-login-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.45);
        }

        .zolo-login-signup {
            text-align: center;
            margin-top: 16px;
            font-size: 12.5px;
            color: #64748B;
        }

        /* Clean integrated Security Badge */
        .smart-security-badge {
            margin-top: 20px;
            padding: 11px 14px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            font-size: 11.5px;
            line-height: 1.5;
            color: #475569;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .smart-security-badge .mdi {
            font-size: 17px;
            color: #10B981;
            flex-shrink: 0;
            margin-top: 1px;
        }

        @media (max-width: 991px) {
            .zolo-login-shell {
                flex-direction: column;
            }
            .zolo-login-aside {
                padding: 32px 24px;
            }
            .smart-vector-frame {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="zolo-login-shell">

        <!-- Left Showcase Panel with 2D Vector Graphic -->
        <div class="zolo-login-aside">
            <div class="smart-aside-header">
                <div class="zolo-login-brand">
                    @if ($schoolSettings['horizontal_logo'] ?? $systemSettings['login_page_logo'] ?? $systemSettings['horizontal_logo'] ?? '')
                        <img src="{{ $schoolSettings['horizontal_logo'] ?? $systemSettings['login_page_logo'] ?? $systemSettings['horizontal_logo'] }}"
                            alt="{{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? 'logo' }}" class="zolo-login-logo">
                    @else
                        <div class="zolo-login-mark">{{ Str::substr($schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? 'Z', 0, 1) }}</div>
                        <div class="zolo-login-brand-name">{{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? config('app.name') }}</div>
                    @endif
                </div>
                <div class="smart-pulse-pill">
                    <span class="smart-pulse-dot"></span>
                    <span>Smart Campus v3.2 Active</span>
                </div>
            </div>

            <div class="smart-aside-hero">
                <div class="smart-hero-kicker">{{ __('Next-Gen School Intelligence') }}</div>
                <h1 class="smart-hero-title">
                    {{ __('Automate campus operations with') }} <span class="smart-gradient-text">{{ __('intelligent workflows.') }}</span>
                </h1>
                <p class="smart-hero-desc">
                    {{ __('Conflict-free timetable generation, real-time teacher specialization matching, automated gradebooks, and smart campus analytics in one unified console.') }}
                </p>

                <!-- 2D Vector Illustration Display -->
                <div class="smart-vector-frame">
                    <img src="{{ asset('assets/images/smart_school_vector.jpg') }}" alt="Smart School Intelligence 2D Vector" class="smart-vector-img">
                    <div class="smart-vector-floating-card top-left">
                        <i class="mdi mdi-calendar-check text-primary" style="font-size: 19px;"></i>
                        <div>
                            <div class="card-num">{{ __('0 Conflict Timetable') }}</div>
                            <div class="card-lbl">{{ __('AI Constraint Solver') }}</div>
                        </div>
                    </div>
                    <div class="smart-vector-floating-card bottom-right">
                        <i class="mdi mdi-account-group text-success" style="font-size: 19px;"></i>
                        <div>
                            <div class="card-num">{{ __('Smart Teacher Match') }}</div>
                            <div class="card-lbl">{{ __('Subject & Class Teacher') }}</div>
                        </div>
                    </div>
                </div>

                <div class="smart-feature-chips">
                    <div class="smart-chip"><i class="mdi mdi-magic-staff"></i>{{ __('Conflict-Free Timetable AI') }}</div>
                    <div class="smart-chip"><i class="mdi mdi-bus-clock"></i>{{ __('GPS Live Fleet Tracking') }}</div>
                    <div class="smart-chip"><i class="mdi mdi-shield-check"></i>{{ __('Role-Scoped 2FA Security') }}</div>
                </div>
            </div>

            <div class="smart-aside-footer">
                <div>&copy; {{ date('Y') }} {{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? config('app.name') }}</div>
                <div><i class="mdi mdi-lock-outline"></i> {{ __('256-Bit Bank-Grade TLS Encryption') }}</div>
            </div>
        </div>

        <!-- Right Sign In Card -->
        <div class="zolo-login-panel">
            <div class="zolo-login-card">
                <h2 class="zolo-login-h2">{{ __('Sign in') }}</h2>
                <div class="zolo-login-sub">{{ __('Enter your school credentials or use instant demo access.') }}</div>

                @if (\Session::has('emailSuccess'))
                    <div class="alert alert-success text-center mt-2" role="alert">{{ \Session::get('emailSuccess') }}.</div>
                @endif
                @if (\Session::has('success'))
                    <div class="alert alert-success text-center mt-2" role="alert">{{ \Session::get('success') }}.</div>
                @endif
                @if (\Session::has('emailError'))
                    <div class="alert alert-danger text-center mt-2" role="alert">{{ \Session::get('emailError') }}.</div>
                @endif
                @if (\Session::has('error'))
                    <div class="alert alert-danger text-center mt-2" role="alert">{{ \Session::get('error') }}.</div>
                @endif

                @if (config('app.demo_mode'))
                    <!-- Smart Segmented Role Selector -->
                    <div class="smart-role-selector">
                        <button type="button" class="smart-role-btn active" id="tab_schooladmin" onclick="switchDemoRole('schooladmin')">
                            <i class="mdi mdi-shield-account"></i>
                            <span>Admin</span>
                        </button>
                        <button type="button" class="smart-role-btn" id="tab_teacher" onclick="switchDemoRole('teacher')">
                            <i class="mdi mdi-teach"></i>
                            <span>Teacher</span>
                        </button>
                        <button type="button" class="smart-role-btn" id="tab_superadmin" onclick="switchDemoRole('superadmin')">
                            <i class="mdi mdi-crown"></i>
                            <span>Super</span>
                        </button>
                        <button type="button" class="smart-role-btn" id="tab_staff" onclick="switchDemoRole('staff')">
                            <i class="mdi mdi-account-tie"></i>
                            <span>Staff</span>
                        </button>
                    </div>
                    <div class="smart-role-badge-tip" id="roleDescriptionTip">
                        <span id="roleTitleSpan">🏫 School Administrator</span>
                        <span class="badge bg-primary text-white" style="font-size: 10px; cursor: pointer;" onclick="$('#frmLogin').submit()">⚡ Quick Login</span>
                    </div>
                @endif

                <form action="{{ route('login') }}" id="frmLogin" method="POST" class="zolo-login-form">
                    @csrf
                    <div class="zolo-login-field">
                        <div class="zolo-login-field-label">{{ __('email') }}</div>
                        <div class="zolo-login-input-wrap">
                            <i class="mdi mdi-email-outline"></i>
                            <input id="email" type="text" name="email"
                                value="{{ isset($school) && !empty($school) && $school->type == 'demo' ? $school->user->email : old('email') }}"
                                required autocomplete="email" autofocus placeholder="{{ __('email_or_mobile') }}">
                        </div>
                    </div>
                    <div class="zolo-login-field">
                        <div class="zolo-login-label-row">
                            <div class="zolo-login-field-label">{{ __('password') }}</div>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="zolo-login-link">{{ __('forgot_password') }}</a>
                            @endif
                        </div>
                        <div class="zolo-login-input-wrap">
                            <i class="mdi mdi-lock-outline"></i>
                            <input id="password" type="password" name="password" required
                                value="{{ isset($school) && !empty($school) && $school->type == 'demo' ? $school->user->mobile : '' }}"
                                autocomplete="current-password" placeholder="{{ __('password') }}">
                            <span class="zolo-toggle-eye" id="togglePasswordShowHide"><i class="fa fa-eye-slash" id="togglePassword"></i></span>
                        </div>
                    </div>

                    @if ($school ?? '')
                        <div class="zolo-login-field d-none">
                            <div class="zolo-login-field-label">{{ __('school_code') }}</div>
                            <div class="zolo-login-input-wrap">
                                <input id="school_code" type="text" name="code" value="{{ $school->code }}" autocomplete="school_code" placeholder="{{ __('school_code') }}">
                            </div>
                        </div>
                    @else
                        <div class="zolo-login-field">
                            <div class="zolo-login-field-label">{{ __('school_code') }}</div>
                            <div class="zolo-login-input-wrap">
                                <i class="mdi mdi-office-building"></i>
                                <input id="school_code" type="text" name="code" value="{{ old('school_code') }}" autocomplete="school_code" placeholder="{{ __('school_code') }}">
                            </div>
                        </div>
                    @endif

                    <button type="submit" name="btnlogin" id="login_btn"
                        class="btn btn-primary zolo-login-submit">{{ __('login') }}</button>

                    <div class="zolo-login-signup">
                        <a href="#" data-bs-toggle="modal" data-bs-dismiss="offcanvas" data-bs-target="#staticBackdrop">
                            {{ __('New user Sign up to manage your school activities seamlessly') }}
                        </a>
                    </div>
                </form>

                <div class="smart-security-badge">
                    <i class="mdi mdi-shield-check"></i>
                    <div>
                        <strong>{{ __('Two-Factor Authentication (2FA) Enforced') }}</strong><br>
                        {{ __('Admin sessions are authenticated via 6-digit verification code with 256-bit SSL protection.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('registration_form')

    <script src="{{ asset('/assets/js/vendor.bundle.base.js') }}"></script>
    <script src="{{ asset('/assets/js/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.js') }}"></script>
    <script src="{{ asset('/assets/js/custom/common.js') }}"></script>
    <script src="{{ asset('/assets/js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('/assets/js/custom/function.js') }}"></script>

    <script src="{{ asset('/assets/bootstrap5/bootstrap.bundle.min.js') }}"></script>

    <script type='text/javascript'>
        $("#frmLogin").validate({
            rules: {
                username: "required",
                password: "required",
            },
            success: function (label, element) {
                $(element).parent().removeClass('has-danger')
                $(element).removeClass('form-control-danger')
            },
            errorPlacement: function (label, element) {
                if (label.text()) {
                    if ($(element).attr("name") == "password") {
                        label.insertAfter(element.parent()).addClass('text-danger mt-2');
                    } else {
                        label.addClass('mt-2 text-danger');
                        label.insertAfter(element);
                    }
                }
            },
            highlight: function (element, errorClass) {
                $(element).parent().addClass('has-danger')
                $(element).addClass('form-control-danger')
            }
        });

        const togglePassword = document.querySelector("#togglePasswordShowHide");
        const password = document.querySelector("#password");

        togglePassword.addEventListener("click", function () {
            const type = password.getAttribute("type") === "password" ? "text" : "password";
            password.setAttribute("type", type);
            // this.classList.toggle("fa-eye");
            if (password.getAttribute("type") === 'password') {
                $('#togglePassword').addClass('fa-eye-slash');
                $('#togglePassword').removeClass('fa-eye');
            } else {
                $('#togglePassword').removeClass('fa-eye-slash');
                $('#togglePassword').addClass('fa-eye');
            }
        });

        function switchDemoRole(role) {
            $('.smart-role-btn').removeClass('active');
            $('#tab_' + role).addClass('active');

            if (role === 'schooladmin') {
                $('#email').val('admin@demo.test');
                $('#password').val('Demo@12345');
                $('#school_code').val('DEMO001');
                $('#roleTitleSpan').html('🏫 <strong>School Admin</strong> (Full Campus Control)');
            } else if (role === 'teacher') {
                $('#email').val('teacher@demo.test');
                $('#password').val('Demo@12345');
                $('#school_code').val('DEMO001');
                $('#roleTitleSpan').html('👩‍🏫 <strong>Teacher Portal</strong> (Timetable, Marks & Attendance)');
            } else if (role === 'superadmin') {
                $('#email').val('superadmin@gmail.com');
                $('#password').val('Zolo!Schools2026x');
                $('#school_code').val('');
                $('#roleTitleSpan').html('👑 <strong>Super Admin</strong> (Multi-Tenant System Owner)');
            } else if (role === 'staff') {
                $('#email').val('parent@demo.test');
                $('#password').val('Demo@12345');
                $('#school_code').val('DEMO001');
                $('#roleTitleSpan').html('👨‍👩‍👧 <strong>Parent / Guardian</strong> (Student Portal)');
            }
        }

        @if (config('app.demo_mode'))
            $(document).ready(function() {
                switchDemoRole('schooladmin');
            });

            // Super admin panel fallbacks
            $('#superadmin_btn').on('click', function (e) {
                switchDemoRole('superadmin');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            });

            $('#superadmin_staff_btn').on('click', function (e) {
                $('#email').val('mahesh@gmail.com');
                $('#password').val('staff@123');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            });

            // School Panel fallbacks
            $('#schooladmin_btn').on('click', function (e) {
                switchDemoRole('schooladmin');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            });

            $('#teacher_btn').on('click', function (e) {
                switchDemoRole('teacher');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            });

            $('#schooladmin_staff_btn').on('click', function (e) {
                switchDemoRole('staff');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            });
        @endif

        const please_wait = "{{__('Please wait')}}"
        const processing_your_request = "{{__('Processing your request')}}"
    </script>
</body>

@if (Session::has('error'))
    <script type='text/javascript'>
        $.toast({
            text: '{{ Session::get('error') }}',
            showHideTransition: 'slide',
            icon: 'error',
            loaderBg: '#f2a654',
            position: 'top-right'
        });
    </script>
@endif

@if ($errors->any())
    @foreach ($errors->all() as $error)
        <script type='text/javascript'>
            $.toast({
                text: '{{ $error }}',
                showHideTransition: 'slide',
                icon: 'error',
                loaderBg: '#f2a654',
                position: 'top-right'
            });
        </script>
    @endforeach
@endif

</html>