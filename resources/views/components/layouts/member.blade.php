@props(['title' => null])

@include('layouts.member', [
    'title' => $title,
    'slot' => $slot,
])
