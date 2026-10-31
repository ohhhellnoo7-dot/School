{{-- Apply the saved preference before styles or body content render. --}}
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
<link href="{{ asset('css/scholar-design.css') }}?v={{ filemtime(public_path('css/scholar-design.css')) }}" rel="stylesheet">
<script src="{{ asset('js/scholar-ui.js') }}?v={{ filemtime(public_path('js/scholar-ui.js')) }}" defer></script>
