<?php

namespace Tests\Feature;

use App\Actions\Cms\CreateCompanyWithSite;
use App\Actions\Cms\GlobalindoPilot;
use App\Models\Company;
use App\Models\PageSection;
use App\Models\SectionItem;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GlobalindoPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_is_read_only_and_alignment_preserves_content_brand_and_other_companies(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $about = $site->pages()->where('slug', 'about-us')->firstOrFail();
        $history = $about->sections()->where('key', 'history')->firstOrFail();
        $history->update(['title' => 'Judul pilihan admin', 'sort_order' => 9]);
        $pillars = PageSection::factory()->for($about)->create(['key' => 'pillars', 'type' => 'pillars', 'settings' => ['source' => 'company'], 'is_active' => true]);
        $clients = $home->sections()->where('key', 'clients')->firstOrFail();
        $client = SectionItem::factory()->for($clients, 'section')->create(['title' => 'Klien uji', 'image_path' => 'media/images/client.png']);
        $brand = $site->getAttributes();
        $company = $site->company->getAttributes();
        $other = Site::where('company_id', '!=', $site->company_id)->firstOrFail();
        $otherSections = PageSection::whereIn('page_id', $other->pages()->pluck('id'))->get()->toArray();

        $this->artisan('cms:align-globalindo')->assertSuccessful();
        $this->assertTrue($pillars->fresh()->is_active);
        $this->assertSame(9, $history->fresh()->sort_order);
        $this->artisan('cms:align-globalindo', ['--apply' => true])->assertSuccessful();

        $this->assertFalse($pillars->fresh()->is_active);
        $this->assertSame('Judul pilihan admin', $history->fresh()->title);
        $this->assertSame(1, $history->fresh()->sort_order);
        $this->assertFalse($clients->fresh()->is_active);
        $this->assertSame('media/images/client.png', $client->fresh()->image_path);
        $this->assertSame($brand, $site->fresh()->getAttributes());
        $this->assertSame($company, $site->company->fresh()->getAttributes());
        $this->assertSame($otherSections, PageSection::whereIn('page_id', $other->pages()->pluck('id'))->get()->toArray());
        $this->assertSame([], app(GlobalindoPilot::class)->align($site));
    }

    public function test_missing_section_is_created_inactive_and_conflicting_section_rolls_back_changes(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $home->sections()->where('key', 'csr')->delete();
        app(GlobalindoPilot::class)->align($site);
        $this->assertFalse($home->sections()->where('key', 'csr')->firstOrFail()->is_active);
        $hero = $home->sections()->where('key', 'hero')->firstOrFail();
        $hero->update(['sort_order' => 9]);
        $capacity = $home->sections()->where('key', 'capacity')->firstOrFail();
        $capacity->update(['type' => 'clients', 'settings' => ['source' => 'items']]);

        $this->artisan('cms:align-globalindo', ['--apply' => true])->assertFailed();

        $this->assertSame(9, $hero->fresh()->sort_order);
        $this->assertSame('clients', $capacity->fresh()->type);
    }

    public function test_menu_conflict_does_not_overwrite_custom_destination_or_partially_apply(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $about = $site->pages()->where('slug', 'about-us')->firstOrFail();
        $pillars = PageSection::factory()->for($about)->create(['key' => 'pillars', 'type' => 'pillars', 'settings' => ['source' => 'company'], 'is_active' => true]);
        $link = $site->menus()->where('key', 'header')->firstOrFail()->items()->where('key', 'home')->firstOrFail();
        $link->update(['page_id' => null, 'url' => 'https://example.com']);

        $this->artisan('cms:align-globalindo', ['--apply' => true])->assertFailed();

        $this->assertTrue($pillars->fresh()->is_active);
        $this->assertSame('https://example.com', $link->fresh()->url);
    }

    public function test_seeded_pilot_has_expected_order_and_admin_guidance_with_working_service_shortcuts(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $expected = [
            'home' => ['hero', 'about', 'services', 'clients', 'capacity', 'csr'],
            'about-us' => ['history', 'vision-mission', 'certifications', 'the-group'],
            'services' => ['services', 'products', 'process'],
            'contact' => ['map', 'contact'],
        ];
        foreach ($expected as $slug => $keys) {
            $page = $site->pages()->where('slug', $slug)->firstOrFail();
            $this->assertSame($keys, $page->sections()->pluck('key')->all());
        }
        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSeeInOrder(['id="cluster-home"', 'id="cluster-about-us"', 'id="cluster-service"', 'id="cluster-contact"'], false)
            ->assertSee('id="cluster-about-us-the-group"', false)
            ->assertSee(route('company.show', 'globalindo').'#cluster-about-us-the-group', false)
            ->assertSee(route('company.show', 'globalindo').'#cluster-service-products', false)
            ->assertSee(route('company.show', 'globalindo').'#cluster-service-process', false)
            ->assertDontSee('id="cluster-about-us-pillars"', false)
            ->assertDontSee('id="cluster-home-clients"', false);
        preg_match_all('/\bid="([^"]+)"/', $response->getContent(), $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->get(route('admin.sites.pages.sections.index', [$site, $home]))->assertOk()->assertSee('Acuan pilot Globalindo');
        $section = $home->sections()->where('key', 'clients')->firstOrFail();
        $this->get(route('admin.sites.pages.sections.edit', [$site, $home, $section]))->assertOk()->assertSee('logo klien yang sudah mendapat izin');
    }

    public function test_new_globalindo_uses_pilot_structure_without_publishing_or_changing_default_brand(): void
    {
        $attributes = Company::factory()->make(['slug' => 'globalindo'])->getAttributes();
        $company = app(CreateCompanyWithSite::class)->create($attributes);
        $site = $company->site;
        $this->assertFalse($company->is_active);
        $this->assertSame('default', $site->template_key);
        $this->assertSame(4, $site->pages()->where('is_published', false)->count());
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->assertSame(['hero', 'about', 'services', 'clients', 'capacity', 'csr'], $home->sections()->pluck('key')->all());
        $this->assertFalse($home->sections()->where('key', 'clients')->firstOrFail()->is_active);
        $about = $site->pages()->where('slug', 'about-us')->firstOrFail();
        $this->assertSame(['history', 'vision-mission', 'certifications', 'the-group'], $about->sections()->pluck('key')->all());
    }

    public function test_other_company_cannot_be_aligned_as_globalindo(): void
    {
        $site = Site::factory()->create();
        $this->expectException(RuntimeException::class);
        app(GlobalindoPilot::class)->align($site);
    }
}
