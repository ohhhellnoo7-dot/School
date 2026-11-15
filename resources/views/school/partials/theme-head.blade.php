{{-- Apply the saved preference before styles or body content render. --}}
<script type="application/json" id="scholar-theme-registry">@json(config('scholar-themes'))</script>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
<link href="{{ asset('css/scholar-design.css') }}?v={{ filemtime(public_path('css/scholar-design.css')) }}" rel="stylesheet">
<link href="{{ asset('css/theme-motion.css') }}?v={{ filemtime(public_path('css/theme-motion.css')) }}" rel="stylesheet">
<link href="{{ asset('css/sidebar-light.css') }}?v={{ filemtime(public_path('css/sidebar-light.css')) }}" rel="stylesheet">
<link href="{{ asset('css/workspace-themes.css') }}?v={{ filemtime(public_path('css/workspace-themes.css')) }}" rel="stylesheet">
@include('school.partials.theme-tokens')
<link href="{{ asset('css/khmer-heritage.css') }}?v={{ filemtime(public_path('css/khmer-heritage.css')) }}" rel="stylesheet">
<link href="{{ asset('css/theme-components.css') }}?v={{ filemtime(public_path('css/theme-components.css')) }}?v={{ filemtime(public_path('css/theme-components.css')) }}" rel="stylesheet">
<script src="{{ asset('js/scholar-ui.js') }}?v={{ filemtime(public_path('js/scholar-ui.js')) }}" defer></script>
<script src="{{ asset('js/hover-select.js') }}?v={{ filemtime(public_path('js/hover-select.js')) }}" defer></script>
<script src="{{ asset('js/workspace-motion.js') }}?v={{ filemtime(public_path('js/workspace-motion.js')) }}" defer></script>
<link href="{{ asset('css/christmas.css') }}?v={{ filemtime(public_path('css/christmas.css')) }}" rel="stylesheet">
<script src="{{ asset('js/christmas.js') }}?v={{ filemtime(public_path('js/christmas.js')) }}" defer></script>
<link href="{{ asset('css/autumn.css') }}?v={{ filemtime(public_path('css/autumn.css')) }}" rel="stylesheet">
<script src="{{ asset('js/autumn.js') }}?v={{ filemtime(public_path('js/autumn.js')) }}" defer></script>
<link href="{{ asset('css/ocean-life.css') }}?v={{ filemtime(public_path('css/ocean-life.css')) }}" rel="stylesheet">
<script src="{{ asset('js/ocean-life.js') }}?v={{ filemtime(public_path('js/ocean-life.js')) }}" defer></script>
<link href="{{ asset('css/ocean-shark.css') }}?v={{ filemtime(public_path('css/ocean-shark.css')) }}" rel="stylesheet">
<script src="{{ asset('js/ocean-shark.js') }}?v={{ filemtime(public_path('js/ocean-shark.js')) }}" defer></script>
<link href="{{ asset('css/ocean-surface.css') }}?v={{ filemtime(public_path('css/ocean-surface.css')) }}" rel="stylesheet">
<script src="{{ asset('js/ocean-surface.js') }}?v={{ filemtime(public_path('js/ocean-surface.js')) }}" defer></script>
<link href="{{ asset('css/ocean-parachute.css') }}?v={{ filemtime(public_path('css/ocean-parachute.css')) }}" rel="stylesheet">
<script src="{{ asset('js/ocean-parachute.js') }}?v={{ filemtime(public_path('js/ocean-parachute.js')) }}" defer></script>
<link href="{{ asset('css/khmer-apsara.css') }}?v={{ filemtime(public_path('css/khmer-apsara.css')) }}" rel="stylesheet">
<script src="{{ asset('js/khmer-apsara.js') }}?v={{ filemtime(public_path('js/khmer-apsara.js')) }}" data-apsara-image="{{ asset('images/khmer-apsara-smooth-cycle.png') }}" defer></script>
