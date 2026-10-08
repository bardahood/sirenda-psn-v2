@props(['judul' => null])
@include('layouts.app', ['judul' => $judul, 'slot' => $slot, 'filter' => $filter ?? null])
