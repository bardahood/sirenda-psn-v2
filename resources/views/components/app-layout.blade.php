@props(['judul' => null, 'subjudul' => null, 'layarPenuh' => false])
@include('layouts.app', ['judul' => $judul, 'subjudul' => $subjudul, 'layarPenuh' => $layarPenuh, 'slot' => $slot, 'filter' => $filter ?? null, 'aksi' => $aksi ?? null])
