<link href="{{ asset('assets/home_page/css/style.css') }}" rel="stylesheet">

<!-- bootstrap  -->
{{-- <link href="{{ asset('/assets/bootstrap5/bootstrap.min.css') }}" rel="stylesheet"> --}}
<link rel="stylesheet" href="{{ asset('assets/home_page/css/bootstrap.min.css') }}">

<!-- swiper -->
<link rel="stylesheet" href="{{ asset('assets/home_page/css/owl.carousel.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/home_page/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('/assets/jquery-toast-plugin/jquery.toast.min.css') }}">

<link rel="shortcut icon" href="{{$systemSettings['favicon'] ?? url('assets/vertical-logo.svg') }}"/>
<script>
    var baseUrl = "{{ url('/') }}";
    const onErrorImage = (e) => {
        e.target.src = "{{ asset('/assets/no_image_available.jpg') }}";
    };
</script>