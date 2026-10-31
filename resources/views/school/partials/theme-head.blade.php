{{-- Apply the saved preference before styles or body content render. --}}
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
<link href="{{ asset('css/scholar-design.css') }}?v={{ filemtime(public_path('css/scholar-design.css')) }}" rel="stylesheet">
<link href="{{ asset('css/theme-motion.css') }}?v={{ filemtime(public_path('css/theme-motion.css')) }}" rel="stylesheet">
<script src="{{ asset('js/scholar-ui.js') }}?v={{ filemtime(public_path('js/scholar-ui.js')) }}" defer></script>
<script src="{{ asset('js/hover-select.js') }}?v={{ filemtime(public_path('js/hover-select.js')) }}" defer></script>
<script src="{{ asset('js/workspace-motion.js') }}?v={{ filemtime(public_path('js/workspace-motion.js')) }}" defer></script>
