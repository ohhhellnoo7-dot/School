@props(['program'])
@php($flag = config('program-flags.'.mb_strtolower(trim($program))))
@if($flag)
    <img {{ $attributes->class(['program-flag']) }} src="{{ asset('images/flags/'.$flag.'.svg') }}" width="36" height="24" alt="" aria-hidden="true">
@else
    <span {{ $attributes->class(['program-flag-fallback']) }} aria-hidden="true"><i class="bi bi-globe2"></i></span>
@endif
