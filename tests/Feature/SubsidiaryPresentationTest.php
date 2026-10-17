<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SubsidiaryPresentationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['multipack'])]
    #[TestWith(['hte-rotopack'])]
    #[TestWith(['maxtech'])]
    #[TestWith(['sinar-jaya'])]
    #[TestWith(['top-printing'])]
    public function test_shared_layout_preserves_brand_content_and_navigation(string $slug): void
    {
        $this->seed();
        $company = Company::where('slug', $slug)->firstOrFail();
        $site = $company->site;
        $brand = $site->getAttributes();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $hero = $home->sections()->where('key', 'hero')->firstOrFail();
        SectionItem::factory()->for($hero, 'section')->create(['title' => 'Slide tambahan uji', 'sort_order' => 2]);
        $company->products()->firstOrFail()->update(['image_path' => 'media/images/'.$slug.'-product.png']);
        $step = $company->processSteps()->firstOrFail();
        $step->update(['image_path' => 'media/images/'.$slug.'-process.png', 'image_alt' => 'Foto proses <uji>']);

        $response = $this->get(route('company.show', $slug))->assertOk()
            ->assertSee('theme-'.$slug.' layout-subsidiary', false)
            ->assertSee('--red:'.$site->primary_color, false)
            ->assertSee('--brand-secondary:'.$site->secondary_color, false)
            ->assertSee($site->logo_path, false)
            ->assertSee('gp-group-grid', false)->assertDontSee('data-journey', false)
            ->assertSee('data-carousel-controls', false)->assertSee('Slide tambahan uji')
            ->assertSee('Foto belum tersedia')->assertSee('Foto proses <uji>')
            ->assertSee('Contoh proses — belum diverifikasi sebagai proses resmi.')
            ->assertSee('/storage/media/images/'.$slug.'-process.png', false)
            ->assertSee('gp-product-detail-image', false)
            ->assertSee('href="'.route('company.show', $slug).'#cluster-contact-contact"', false)
            ->assertDontSee('id="cluster-home-clients"', false)
            ->assertSeeInOrder(['id="cluster-home"', 'id="cluster-about-us"', 'id="cluster-service"', 'id="cluster-service-products"', 'id="cluster-service-process"', 'id="cluster-contact"'], false);
        $this->assertSame(2, substr_count($response->getContent(), 'src="'.url('/storage/media/images/'.$slug.'-product.png').'"'));
        preg_match_all('/\bid="([^"]+)"/', $response->getContent(), $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
        $this->assertSame($brand, $site->fresh()->getAttributes());
        $this->get(route('company.page', [$slug, 'services']))
            ->assertRedirect(route('company.show', $slug).'#cluster-service');

        $step->update(['is_active' => false]);
        $this->get(route('company.show', $slug))->assertDontSee('/storage/media/images/'.$slug.'-process.png', false);
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.sites.pages.preview', [$site, $home]))
            ->assertSee('/storage/media/images/'.$slug.'-process.png', false)
            ->assertSee('layout-subsidiary', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('class="inquiry-form"', false);
    }

    public function test_optional_content_uses_shared_components_and_stays_in_its_own_company(): void
    {
        $this->seed();
        $site = Company::where('slug', 'hte-rotopack')->firstOrFail()->site;
        foreach (['clients', 'capacity', 'csr', 'vision_mission', 'certifications'] as $type) {
            $section = $site->pages->flatMap->sections->firstWhere('type', $type);
            $section->update(['is_active' => true]);
            SectionItem::factory()->for($section, 'section')->create([
                'title' => 'Konten khusus HTE '.$type, 'image_path' => null, 'file_path' => null,
                'key' => 'vision', 'value' => '100',
            ]);
        }

        $response = $this->get(route('company.show', 'hte-rotopack'))->assertOk();
        foreach (['clients', 'capacity', 'csr', 'vision_mission', 'certifications'] as $type) {
            $response->assertSee('gp-items-'.$type, false)->assertSee('Konten khusus HTE '.$type);
        }
        $response->assertSee('Logo belum tersedia')->assertSee('Dokumentasi belum tersedia')->assertSee('Dokumen belum tersedia');
        $this->get(route('company.show', 'multipack'))->assertDontSee('Konten khusus HTE');
        $this->get('/')->assertOk()->assertSee('data-journey', false)
            ->assertDontSee('layout-subsidiary', false)->assertDontSee('gp-group-grid', false);
    }
}
