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
    </style>
</head>

<body>
    <div class="zolo-login-shell">

        <div class="zolo-login-aside">
            <div class="zolo-login-brand">
                @if ($schoolSettings['horizontal_logo'] ?? $systemSettings['login_page_logo'] ?? $systemSettings['horizontal_logo'] ?? '')
                    <img src="{{ $schoolSettings['horizontal_logo'] ?? $systemSettings['login_page_logo'] ?? $systemSettings['horizontal_logo'] }}"
                        alt="{{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? 'logo' }}" class="zolo-login-logo">
                @else
                    <div class="zolo-login-mark">{{ Str::substr($schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? 'Z', 0, 1) }}</div>
                    <div class="zolo-login-brand-name">{{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? config('app.name') }}</div>
                @endif
            </div>
            <div class="zolo-login-mid">
                <div class="zolo-login-kicker">{{ __('School management console') }}</div>
                <h1 class="zolo-login-title">{{ __('One console for the whole campus.') }}</h1>
                <div class="zolo-login-desc">{{ __('Admissions, attendance, fees, exams, payroll and parent communication — with per-role privileges on every module.') }}</div>
                <div class="zolo-login-features">
                    <div class="zolo-login-feature"><i class="mdi mdi-shield-check"></i>{{ __('Per-role privileges on every module') }}</div>
                    <div class="zolo-login-feature"><i class="mdi mdi-cash-multiple"></i>{{ __('Fees, payroll and expenses in one place') }}</div>
                    <div class="zolo-login-feature"><i class="mdi mdi-forum-outline"></i>{{ __('Built-in parent and staff communication') }}</div>
                </div>
            </div>
            <div class="zolo-login-foot">&copy; {{ date('Y') }} {{ $schoolSettings['school_name'] ?? $systemSettings['system_name'] ?? config('app.name') }}</div>
        </div>

        <div class="zolo-login-panel">
            <div class="zolo-login-card">
                <h2 class="zolo-heading zolo-login-h2">{{ __('Sign in') }}</h2>
                <div class="zolo-login-sub">{{ __('Use your school credentials to continue.') }}</div>

                @if (\Session::has('emailSuccess'))
                    <div class="alert alert-success text-center mt-3" role="alert">{{ \Session::get('emailSuccess') }}.</div>
                @endif
                @if (\Session::has('success'))
                    <div class="alert alert-success text-center mt-3" role="alert">{{ \Session::get('success') }}.</div>
                    <div class="alert alert-success text-center mt-2" role="alert">
                        Please ensure you use your registered email for login, and your contact number as the password.
                    </div>
                @endif
                @if (\Session::has('emailError'))
                    <div class="alert alert-danger text-center mt-3" role="alert">{{ \Session::get('emailError') }}.</div>
                @endif
                @if (\Session::has('error'))
                    <div class="alert alert-danger text-center mt-3" role="alert">{{ \Session::get('error') }}.</div>
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
                @include('registration_form')

                @if (config('app.demo_mode'))
                    <div class="zolo-demo-divider">{{ __('SIGN IN AS DEMO ROLE') }}</div>
                    @if (empty($school) ?? '')
                        <div class="zolo-demo-group-label">Super Admin Panels</div>
                        <div class="zolo-demo-grid">
                            <button type="button" class="zolo-demo-role" id="superadmin_btn">
                                <i class="mdi mdi-shield-account"></i>
                                <span class="zolo-demo-role-text"><span class="zolo-demo-role-label">Super Admin</span><span class="zolo-demo-role-scope">System owner</span></span>
                            </button>
                            <button type="button" class="zolo-demo-role" id="superadmin_staff_btn">
                                <i class="mdi mdi-account-box"></i>
                                <span class="zolo-demo-role-text"><span class="zolo-demo-role-label">Staff</span><span class="zolo-demo-role-scope">Support staff</span></span>
                            </button>
                        </div>
                    @endif
                    <div class="zolo-demo-group-label">School Admin Panels</div>
                    <div class="zolo-demo-grid">
                        <button type="button" class="zolo-demo-role" id="schooladmin_btn">
                            <i class="mdi mdi-account-settings"></i>
                            <span class="zolo-demo-role-text"><span class="zolo-demo-role-label">School Admin</span><span class="zolo-demo-role-scope">Full school access</span></span>
                        </button>
                        <button type="button" class="zolo-demo-role" id="teacher_btn">
                            <i class="mdi mdi-teach"></i>
                            <span class="zolo-demo-role-text"><span class="zolo-demo-role-label">Teacher</span><span class="zolo-demo-role-scope">Class-scoped</span></span>
                        </button>
                        <button type="button" class="zolo-demo-role" id="schooladmin_staff_btn">
                            <i class="mdi mdi-account-box"></i>
                            <span class="zolo-demo-role-text"><span class="zolo-demo-role-label">Staff</span><span class="zolo-demo-role-scope">Limited access</span></span>
                        </button>
                    </div>
                @endif

                <div class="zolo-security-note">
                    <i class="mdi mdi-shield-check"></i>
                    <span>{{ __('Two-factor authentication is enforced for admin accounts. A 6-digit code is sent to the registered number.') }}</span>
                </div>
            </div>
        </div>
    </div>

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

        @if (config('app.demo_mode'))
            // Super admin panel
            $('#superadmin_btn').on('click', function (e) {
                $('#email').val('superadmin@gmail.com');
                $('#password').val('superadmin');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            })

            $('#superadmin_staff_btn').on('click', function (e) {
                $('#email').val('mahesh@gmail.com');
                $('#password').val('staff@123');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            })

            // School Panel
            $('#schooladmin_btn').on('click', function (e) {
                $('#email').val('school1@gmail.com');
                $('#password').val('school@123');
                $('#school_code').val('SCH202412');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            })
            $('#teacher_btn').on('click', function (e) {
                $('#email').val('teacher@gmail.com');
                $('#password').val('0111111111');
                $('#school_code').val('SCH202412');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            })

            $('#schooladmin_staff_btn').on('click', function (e) {
                $('#email').val('smitc@gmail.com');
                $('#password').val('965555885');
                $('#school_code').val('SCH202412');
                $('#login_btn').attr('disabled', true);
                $(this).attr('disabled', true);
                $('#frmLogin').submit();
            })
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