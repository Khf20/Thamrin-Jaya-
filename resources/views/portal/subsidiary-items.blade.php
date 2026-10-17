<div class="gp-items gp-items-{{ $section->type }}">
@foreach($records as $item)
<article class="gp-item">
@if($preview && !$item->is_active)<p class="demo-note">Item nonaktif</p>@endif
@php($image = \App\Actions\Cms\MediaManager::url($item->image_path))
@if(in_array($section->type, ['clients', 'csr', 'certifications'], true))
<div class="gp-item-media">
@if($image)<img src="{{ $image }}" alt="{{ $item->image_alt ?: $item->title }}" loading="lazy">
@else<div class="gp-placeholder"><span class="gp-placeholder-mark" aria-hidden="true">{{ $section->type === 'certifications' ? '▤' : ($section->type === 'clients' ? '+' : '▧') }}</span><span>{{ match($section->type) { 'clients' => 'Logo belum tersedia', 'certifications' => 'Dokumen belum tersedia', default => 'Dokumentasi belum tersedia' } }}</span></div>@endif
</div>
@endif
<div class="gp-item-copy">
@if($section->type === 'vision_mission')<span class="gp-index" aria-hidden="true">{{ $item->key === 'vision' ? '01' : '02' }}</span><span class="cms-kicker">{{ $item->key === 'vision' ? 'Visi' : 'Misi' }}</span>@endif
<h3>{{ $item->title }}</h3>
@if($section->type === 'capacity')
<div class="gp-capacity-value">
@if($icon = match($item->settings['icon'] ?? null) { 'factory' => '▦', 'quality' => '✓', 'clock' => '◷', 'price' => '¤', 'leaf' => '❧', 'award' => '★', default => null })<span class="cms-capacity-icon" aria-hidden="true">{{ $icon }}</span>@endif
<strong>{{ $item->value }}</strong><span>{{ $item->unit }}</span>
</div>
@endif
@if($item->subtitle)<p>{{ $item->subtitle }}</p>@endif
@if($item->body)<p class="cms-copy">{{ $item->body }}</p>@endif
@if($item->occurred_on)<time datetime="{{ $item->occurred_on->format('Y-m-d') }}">{{ $item->occurred_on->format('d M Y') }}</time>@endif
@if($section->type === 'certifications')
@if($issuer = $item->settings['issuer'] ?? null)<p>{{ $issuer }}</p>@endif
@if($pdf = \App\Actions\Cms\MediaManager::url($item->file_path))<a class="cms-link" href="{{ $pdf }}" target="_blank" rel="noopener noreferrer">Lihat sertifikat PDF ↗</a>@endif
@endif
@if($link = $content->link($item->link_url, $page, $preview))<a class="cms-link" href="{{ $link }}">{{ $item->link_label ?: 'Selengkapnya' }} <span aria-hidden="true">→</span></a>@endif
</div>
</article>
@endforeach
</div>
