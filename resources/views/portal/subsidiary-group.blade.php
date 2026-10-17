<section class="cms-section gp-group" id="{{ $sectionAnchor }}">
<div class="shell">
@if($preview && !$section->is_active)<p class="demo-note">Section nonaktif</p>@endif
<div class="cms-section-head"><h2>{{ $section->title ?: 'The Group' }}</h2>@if($section->subtitle)<p>{{ $section->subtitle }}</p>@endif</div>
<div class="gp-group-grid">
@forelse($records as $unit)
<article class="gp-group-unit">
<div class="gp-unit-logo {{ $unit->site?->appearance() === 'light' ? 'on-light' : 'on-dark' }}">
@if($logo = \App\Actions\Cms\MediaManager::url($unit->site?->logo_path))<img src="{{ $logo }}" alt="{{ $unit->site->logo_alt ?: $unit->name }}" loading="lazy">@else<strong>{{ $unit->short_name }}</strong>@endif
</div>
<span class="cms-kicker">{{ $unit->sector }}</span><h3>{{ $unit->name }}</h3>
<a class="cms-link" href="{{ route('company.show', $unit->slug) }}">Lihat profil <span aria-hidden="true">→</span></a>
</article>
@empty<p class="empty-state">Informasi unit bisnis sedang diperbarui.</p>@endforelse
</div>
</div>
</section>
