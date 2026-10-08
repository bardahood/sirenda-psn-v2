@props(['judul' => null, 'subjudul' => null])
@include('layouts.app', ['judul' => $judul, 'subjudul' => $subjudul, 'slot' => $slot, 'filter' => $filter ?? null])
