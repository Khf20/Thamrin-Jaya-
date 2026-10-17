<?php

namespace App\Actions\Cms;

use App\Models\Company;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompanyWithSite
{
    public function create(array $attributes): Company
    {
        return DB::transaction(function () use ($attributes): Company {
            $company = Company::create([...$attributes, 'is_active' => false, 'is_demo' => false]);
            $site = $company->site()->create([
                'slug' => 'company-'.Str::uuid(), 'name' => $company->name,
                'template_key' => 'default', 'primary_color' => $company->accent ?? '#2563eb',
                'secondary_color' => '#101820', 'font_family' => 'Inter', 'is_active' => true,
            ]);
            $pages = [];
            foreach (['home' => 'Home', 'about-us' => 'About Us', 'services' => 'Service', 'contact' => 'Contact'] as $slug => $title) {
                $pages[] = $site->pages()->create(['slug' => $slug, 'title' => $title, 'is_published' => false, 'sort_order' => count($pages) + 1]);
            }
            $this->createDefaultSections($pages);
            foreach (['header' => 'Navigasi Utama', 'footer' => 'Navigasi Footer'] as $key => $name) {
                $menu = $site->menus()->create(['key' => $key, 'name' => $name]);
                foreach ($pages as $page) {
                    $menu->items()->create(['key' => $page->slug, 'page_id' => $page->id, 'label' => $page->title, 'type' => 'link', 'sort_order' => $page->sort_order]);
                }
            }

            if (GlobalindoPilot::applies($site)) {
                app(GlobalindoPilot::class)->align($site);
            }

            return $company;
        });
    }

    /** @param list<Page> $pages */
    private function createDefaultSections(array $pages): void
    {
        $definitions = [
            'home' => [
                ['hero', 'carousel', 'Welcome'],
                ['about', 'about', 'About Us'],
                ['services', 'services', 'Our Services'],
                ['featured-products', 'products', 'Featured Products'],
            ],
            'about-us' => [
                ['history', 'history', 'Company History'],
                ['strengths', 'pillars', 'Our Strengths'],
                ['vision-mission', 'vision_mission', 'Vision & Mission'],
                ['certifications', 'certifications', 'Certifications'],
            ],
            'services' => [
                ['services', 'services', 'Our Services'],
                ['products', 'products', 'Product Catalog'],
                ['process', 'process', 'Our Process'],
            ],
            'contact' => [
                ['map', 'map', 'Our Location'],
                ['contact', 'contact', 'Contact Us'],
            ],
        ];

        if ($pages !== [] && GlobalindoPilot::applies($pages[0]->site)) {
            $definitions = GlobalindoPilot::SECTIONS;
        }

        foreach ($pages as $page) {
            foreach ($definitions[$page->slug] ?? [] as $index => [$key, $type, $title]) {
                $page->sections()->create([
                    'key' => $key,
                    'type' => $type,
                    'variant' => 'default',
                    'title' => $title,
                    'settings' => ['source' => ContentRegistry::source($type, true)],
                    'sort_order' => $index + 1,
                    'is_active' => $definitions[$page->slug][$index][3] ?? true,
                ]);
            }
        }
    }
}
