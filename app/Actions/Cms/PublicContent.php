<?php

namespace App\Actions\Cms;

use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class PublicContent
{
    /** @var array<string, Collection> */
    private array $sectionCache = [];

    /** @var array<int, Collection> */
    private array $serviceCache = [];

    private ?Collection $companyCache = null;

    public function siteVisible(Site $site): bool
    {
        return $site->is_active && (! $site->company_id || ($site->company?->is_active && $site->pages()->where('slug', 'home')->published()->exists()));
    }

    public function pageVisible(Page $page): bool
    {
        return $page->is_published && $this->siteVisible($page->site);
    }

    public function pageUrl(Page $page, bool $preview = false): string
    {
        if ($preview) {
            return route('admin.sites.pages.preview', [$page->site_id, $page]);
        }
        if ($page->site->company_id) {
            $home = route('company.show', $page->site->company->slug);

            return match ($page->slug) {
                'home' => $home.'#cluster-home',
                'about-us' => $home.'#cluster-about-us',
                'services' => $home.'#cluster-service',
                'contact' => $home.'#cluster-contact',
                default => route('company.page', [$page->site->company->slug, $page->slug]),
            };
        }

        return match ($page->slug) {
            'home' => route('home'),
            'about-us', 'services', 'contact' => route('group.'.$page->slug),
            default => route('group.page', $page->slug),
        };
    }

    public function companies(): Collection
    {
        return $this->companyCache ??= Company::visible()->with('site')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function services(Site $site): Collection
    {
        if (isset($this->serviceCache[$site->id])) {
            return $this->serviceCache[$site->id];
        }
        $query = Service::active()->whereHas('companies', fn (Builder $query): Builder => $query->visible())
            ->with(['companies' => fn (BelongsToMany $query): BelongsToMany => $query->visible()->with('site')->orderBy('companies.id')])->orderBy('sort_order')->orderBy('id');
        if ($site->company_id) {
            $query->join('company_service', 'company_service.service_id', '=', 'services.id')
                ->where('company_service.company_id', $site->company_id)->select('services.*')
                ->reorder()->orderBy('company_service.sort_order')->orderBy('services.id');
        }

        return $this->serviceCache[$site->id] = $query->get();
    }

    public function sections(Page $page, bool $preview): Collection
    {
        return $this->sectionCache[$page->id.':'.(int) $preview] ??= $page->sections()->when(! $preview, fn (Builder $query): Builder => $query->active())->get()
            ->filter(function (PageSection $section) use ($page, $preview): bool {
                return in_array($section->type, ContentRegistry::availableSectionTypes($page->site->company_id !== null), true)
                    && $section->variant === 'default'
                    && ($section->settings['source'] ?? null) === ContentRegistry::source($section->type, $page->site->company_id !== null)
                    && ($preview || ! $this->isEmpty($section, $page->site, $preview));
            });
    }

    /** @return array<string, list<array{page: Page, section: PageSection}>> */
    public function onePageClusters(Page $home, bool $preview): array
    {
        $site = $home->site;
        $pages = $site->pages()->whereIn('slug', ['home', 'about-us', 'services', 'contact'])->get()->keyBy('slug');
        $clusters = ['home' => [], 'about-us' => [], 'service' => [], 'contact' => []];
        $clusterPages = ['home' => 'home', 'about-us' => 'about-us', 'service' => 'services', 'contact' => 'contact'];

        foreach ($clusterPages as $cluster => $pageSlug) {
            $page = $pages->get($pageSlug);
            if (! $page || (! $preview && ! $this->pageVisible($page))) {
                continue;
            }
            $page->setRelation('site', $site);
            foreach ($this->sections($page, $preview) as $section) {
                $clusters[$cluster][] = ['page' => $page, 'section' => $section, 'anchor' => $this->sectionAnchor($page, $section->key, $preview)];
            }
        }

        return $clusters;
    }

    /**
     * Text and image of an about/history section: company profile for subsidiaries, the section itself for the holding.
     *
     * @return array{body: ?string, image: ?string}
     */
    public function narrative(PageSection $section, Site $site): array
    {
        if ($site->company) {
            return $section->type === 'about'
                ? ['body' => $site->company->about, 'image' => $site->company->about_image_path]
                : ['body' => $site->company->history, 'image' => null];
        }

        return ['body' => $section->body, 'image' => $section->image_path];
    }

    /** Sections that would render only a heading are left out of the public page. */
    private function isEmpty(PageSection $section, Site $site, bool $preview): bool
    {
        if ($section->type === 'map') {
            return blank($site->resolvedContactDetails()['map_query'] ?? null) && ! $this->demoMapPlaceholder($section, $site);
        }
        if (in_array($section->type, ['about', 'history'], true)) {
            $narrative = $this->narrative($section, $site);

            return blank($narrative['body']) && blank($narrative['image']);
        }

        return in_array($section->type, [
            'carousel', 'services', 'products', 'pillars', 'process',
            'clients', 'capacity', 'csr', 'vision_mission', 'certifications',
        ], true) && $this->records($section, $site, $preview)->isEmpty();
    }

    public function demoMapPlaceholder(PageSection $section, Site $site): bool
    {
        return $section->type === 'map'
            && GlobalindoPilot::applies($site)
            && $site->company->is_demo
            && $section->subtitle === GlobalindoPilot::DEMO_MAP_SUBTITLE
            && blank($site->resolvedContactDetails()['map_query'] ?? null);
    }

    public function records(PageSection $section, Site $site, bool $preview): Collection
    {
        $limit = max(1, min(100, (int) ($section->settings['limit'] ?? 100)));
        $query = match ($section->type) {
            'products' => $site->company?->products(),
            'pillars' => $site->company?->pillars(),
            'process' => $site->company?->processSteps(),
            default => ($section->settings['source'] ?? null) === 'items' ? $section->items() : null,
        };
        if ($query) {
            return $query->when(! $preview, fn (Builder $query): Builder => $query->active())->limit($limit)->get()
                ->filter(fn ($record): bool => $this->recordIsPresentable($section, $record));
        }

        return match ($section->type) {
            'gateway', 'group' => $this->companies()->take($limit),
            'services' => $this->services($site)->take($limit),
            default => collect(),
        };
    }

    private function recordIsPresentable(PageSection $section, mixed $record): bool
    {
        return match ($section->type) {
            'products', 'services' => filled($record->name ?? null),
            'capacity' => filled($record->title ?? null) && filled($record->value ?? null),
            'vision_mission' => in_array($record->key ?? null, ['vision', 'mission'], true)
                && filled($record->title ?? null) && filled($record->body ?? null),
            default => filled($record->title ?? null),
        };
    }

    public function link(?string $url, Page $page, bool $preview = false): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        if (! preg_match('/^#([a-z0-9]+(?:-[a-z0-9]+)*)$/', $url, $match)) {
            return null;
        }
        $key = $match[1];
        if ($this->sections($page, $preview)->contains('key', $key)) {
            return $this->sectionUrl($page, $key, $preview);
        }
        /** Resolve stable catalog/contact anchors across the current site. */
        if (in_array($key, ['products', 'contact'], true)) {
            $target = $page->site->pages()->where('slug', $key === 'products' ? 'services' : 'contact')->first();
            if ($target && ($preview || $this->pageVisible($target)) && $this->sections($target, $preview)->contains('key', $key)) {
                return $this->sectionUrl($target, $key, $preview);
            }
        }

        return null;
    }

    public function sectionUrlFor(Page $page, string $anchor, bool $preview = false): string
    {
        return $this->sectionUrl($page, $anchor, $preview);
    }

    public function menu(Site $site, string $key, bool $preview): array
    {
        $menu = $site->menus()->where('key', $key)->when(! $preview, fn (Builder $query): Builder => $query->active())->first();
        if (! $menu) {
            return [];
        }
        $items = $menu->items()->with(['page.site.company', 'service'])->get();
        $build = function (?int $parent, array $visited = []) use (&$build, $items, $site, $preview): array {
            if (count($visited) >= 3) {
                return [];
            }
            $nodes = [];
            foreach ($items->where('parent_id', $parent) as $item) {
                if ((! $preview && ! $item->is_active) || in_array($item->id, $visited, true)) {
                    continue;
                }
                $children = $build($item->id, [...$visited, $item->id]);
                $url = $this->menuTarget($item, $site, $preview);
                $hasTarget = $item->page_id || $item->service_id || $item->url;
                if (($hasTarget && ! $url) || (! $hasTarget && ! $children) || ($item->type === 'mega_menu' && ! $children)) {
                    continue;
                }
                $nodes[] = ['label' => $item->label, 'url' => $url, 'new_tab' => $item->open_in_new_tab, 'children' => $children];
            }

            return $nodes;
        };

        return $build(null);
    }

    private function menuTarget(MenuItem $item, Site $site, bool $preview): ?string
    {
        if (count(array_filter([$item->page_id, $item->service_id, $item->url])) > 1) {
            return null;
        }
        if ($item->page_id) {
            $page = $item->page;
            if (! $page || $page->site_id !== $site->id || (! $preview && ! $this->pageVisible($page))) {
                return null;
            }
            if ($item->anchor && ! $this->sections($page, $preview)->contains('key', $item->anchor)) {
                return null;
            }

            return $item->anchor
                ? $this->sectionUrl($page, $item->anchor, $preview)
                : $this->pageUrl($page, $preview);
        }
        if ($item->service_id) {
            if (! $this->services($site)->contains('id', $item->service_id)) {
                return null;
            }
            $page = $site->pages()->where('slug', 'services')->first();
            if (! $page || (! $preview && ! $this->pageVisible($page))) {
                return null;
            }
            $section = $this->sections($page, $preview)->firstWhere('type', 'services');
            if (! $section || ! $this->records($section, $site, $preview)->contains('id', $item->service_id)) {
                return null;
            }

            return $this->sectionUrl($page, $section->key.'-service-'.$item->service_id, $preview);
        }

        return $item->url && preg_match('#^https?://#i', $item->url) && filter_var($item->url, FILTER_VALIDATE_URL) ? $item->url : null;
    }

    private function sectionUrl(Page $page, string $anchor, bool $preview): string
    {
        $baseUrl = match (true) {
            $preview => $this->pageUrl($page, true),
            $page->site->company_id !== null => route('company.show', $page->site->company->slug),
            default => $this->pageUrl($page),
        };

        return $baseUrl.'#'.$this->sectionAnchor($page, $anchor, $preview);
    }

    public function sectionAnchor(Page $page, string $anchor, bool $preview = false): string
    {
        if ($preview || ! $page->site->company_id) {
            return $anchor;
        }

        $cluster = match ($page->slug) {
            'home' => 'home',
            'about-us' => 'about-us',
            'services' => 'service',
            'contact' => 'contact',
            default => null,
        };

        return $cluster ? 'cluster-'.$cluster.'-'.$anchor : $anchor;
    }
}
