<section class="cms-hero" id="{{ $sectionAnchor }}" aria-label="{{ $section->title ?: $page->title }}">
@if($preview && !$section->is_active)<p class="demo-note shell">Section nonaktif</p>@endif
<div class="cms-hero-track" id="{{ $sectionAnchor }}-slides" @if($records->count() > 1) tabindex="0" role="region" aria-label="{{ $section->title ?: $page->title }}" @endif>
@forelse($records as $item)
<div class="cms-hero-slide">
<div class="shell cms-hero-copy">
@if($company?->sector)<p class="cms-kicker">{{ $company->sector }}</p>@endif
<{{ $loop->first ? 'h1' : 'h2' }}>{{ $item->title }}</{{ $loop->first ? 'h1' : 'h2' }}>
@if($item->subtitle)<p class="cms-hero-sub">{{ $item->subtitle }}</p>@endif
@if($item->body)<p class="cms-hero-lead cms-copy">{{ $item->body }}</p>@endif
@if($link = $content->link($item->link_url, $page, $preview))<div class="cms-hero-actions"><a class="button" href="{{ $link }}">{{ $item->link_label ?: 'Selengkapnya' }} <span aria-hidden="true">→</span></a></div>@endif
</div>
@php($image = \App\Actions\Cms\MediaManager::url($item->image_path))
@php($illustration = $company && in_array($company->illustration, ['boxes', 'carton', 'pouch', 'machine', 'rolls', 'print'], true) ? asset('images/'.$company->illustration.'.svg') : null)
@if($image || $illustration)<div class="shell cms-hero-visual"><img class="cms-hero-image" src="{{ $image ?: $illustration }}" alt="{{ $image ? ($item->image_alt ?: $item->title) : 'Ilustrasi bidang '.$company->sector }}" @if(!$loop->first) loading="lazy" @endif>@if(!$image)<span class="cms-image-label">Ilustrasi bidang</span>@endif</div>@endif
</div>
@empty
<div class="cms-hero-slide"><div class="shell cms-hero-copy"><h1>{{ $section->title ?: $page->title }}</h1></div></div>
@endforelse
</div>
@if($subsidiaryLayout && $records->count() > 1)
<div class="shell gp-carousel-controls" data-carousel-controls hidden>
<button type="button" class="gp-carousel-button" data-carousel-prev aria-controls="{{ $sectionAnchor }}-slides" aria-label="Slide sebelumnya">←</button>
<span data-carousel-status aria-live="polite" aria-atomic="true">1 / {{ $records->count() }}</span>
<button type="button" class="gp-carousel-button" data-carousel-next aria-controls="{{ $sectionAnchor }}-slides" aria-label="Slide berikutnya">→</button>
</div>
@endif
</section>
