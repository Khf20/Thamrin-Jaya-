@extends('layouts.admin')
@section('title', ($item->exists ? 'Edit' : 'Tambah').' konten')
@section('content')
@php($label = ['products' => 'produk', 'pillars' => 'keunggulan', 'process-steps' => 'tahap proses'][$type])
<a class="text-link" href="{{ route('admin.content.index', [$company, $type]) }}">← {{ $company->short_name }}</a><div class="admin-heading"><div><span class="eyebrow">{{ $company->name }}</span><h1>{{ $item->exists ? 'Edit' : 'Tambah' }} {{ $label }}</h1></div></div><form class="panel form-panel narrow-panel" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.content.update', [$company, $type, $item->id]) : route('admin.content.store', [$company, $type]) }}">@csrf @if($item->exists) @method('PUT') @endif
@if($type === 'products')<x-field name="name" label="Nama produk" :value="$item->name" required maxlength="150"/><x-field name="category" label="Kategori" :value="$item->category" required maxlength="100"/>@else<x-field name="title" label="Judul" :value="$item->title" required maxlength="150"/>@endif
<x-field name="description" type="textarea" label="Deskripsi" :value="$item->description" required maxlength="10000"/>
@if($type === 'process-steps')<p class="demo-note">Gunakan foto tahap proses milik perusahaan yang telah disetujui. Verifikasi nama, urutan, dan deskripsi dengan tim perusahaan terkait sebelum publikasi; teks “Contoh” atau “dapat disesuaikan” masih merupakan data demonstrasi.</p>@endif
@if($type === 'products')<x-field name="specifications" type="textarea" label="Spesifikasi" :value="$item->specifications" hint="Tulis satu spesifikasi per baris, misalnya Material: Kertas ivory."/>@endif
@if(in_array($type, ['products', 'process-steps'], true))
<x-field name="image" type="file" :label="$type === 'products' ? 'Foto produk' : 'Foto tahap proses (opsional)'" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WebP. Maksimal 5 MB. Untuk mengganti foto, pilih file baru."/>
@if($type === 'process-steps')<x-field name="image_alt" label="Deskripsi foto" :value="$item->image_alt" maxlength="255" hint="Jelaskan isi foto secara singkat. Jika kosong, judul tahap digunakan sebagai teks alternatif."/>@endif
@if($image = \App\Actions\Cms\MediaManager::url($item->image_path))
<img src="{{ $image }}" class="upload-preview" alt="{{ $type === 'products' ? $item->name : ($item->image_alt ?: $item->title) }}">
<label class="checkbox-row"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))> Hapus foto saat disimpan</label>
@else<p class="demo-note">Belum ada foto {{ $type === 'products' ? 'produk' : 'tahap proses' }}.</p>@endif
@endif
<x-field name="sort_order" type="number" label="Urutan tampil" :value="$item->sort_order ?? 1" required min="0" max="999"/><label class="checkbox-row"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', session()->hasOldInput() ? false : ($item->exists ? $item->is_active : true)))> Aktif — tampil pada profil perusahaan yang dipublikasikan</label><div class="form-actions"><button class="button" type="submit">Simpan {{ $label }} ✓</button><a href="{{ route('admin.content.index', [$company, $type]) }}">Batal</a></div></form>
@endsection