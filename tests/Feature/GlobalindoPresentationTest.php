<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SectionItem;
use Database\Seeders\GlobalindoDemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalindoPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_globalindo_uses_content_specific_layouts_without_changing_holding(): void
    {
        $this->seed();
        $this->seed(GlobalindoDemoContentSeeder::class);
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $brand = $site->getAttributes();

        $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('gp-items-clients', false)->assertSee('gp-items-capacity', false)
            ->assertSee('gp-items-csr', false)->assertSee('gp-items-vision_mission', false)
            ->assertSee('gp-items-certifications', false)->assertSee('gp-group-grid', false)
            ->assertSee('Logo belum tersedia')->assertSee('Dokumentasi belum tersedia')
            ->assertSee('Dokumen belum tersedia')->assertSee('Data simulasi')
            ->assertSee('Foto belum tersedia')->assertDontSee('data-journey', false);
        $this->assertSame($brand, $site->fresh()->getAttributes());
        $this->get('/')->assertOk()->assertSee('data-journey', false)->assertDontSee('gp-group-grid', false);
        $this->get(route('company.show', 'multipack'))->assertOk()
            ->assertDontSee('data-journey', false)->assertSee('gp-group-grid', false)->assertDontSee('gp-items-', false);
    }

    public function test_uploaded_client_and_certificate_media_remain_accessible_and_escaped(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $about = $site->pages()->where('slug', 'about-us')->firstOrFail();
        $clients = $home->sections()->where('key', 'clients')->firstOrFail();
        $certificates = $about->sections()->where('key', 'certifications')->firstOrFail();
        $clients->update(['is_active' => true]);
        $certificates->update(['is_active' => true]);
        SectionItem::factory()->for($clients, 'section')->create([
            'title' => 'Client <script>bad()</script>', 'image_path' => 'media/images/client.png',
            'image_alt' => 'Logo "><script>bad()</script>', 'link_url' => 'https://example.com',
        ]);
        SectionItem::factory()->for($certificates, 'section')->create([
            'title' => 'Dokumen uji', 'image_path' => 'media/images/certificate.png',
            'file_path' => 'media/documents/certificate.pdf', 'settings' => ['issuer' => 'Penerbit uji'],
        ]);

        $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('Client <script>bad()</script>')->assertDontSee('<script>bad()</script>', false)
            ->assertSee('alt="Logo &quot;&gt;&lt;script&gt;bad()&lt;/script&gt;"', false)
            ->assertSee('src="'.url('/storage/media/images/client.png').'"', false)
            ->assertSee('src="'.url('/storage/media/images/certificate.png').'"', false)
            ->assertSee('href="'.url('/storage/media/documents/certificate.pdf').'"', false)
            ->assertSee('Penerbit uji')->assertSee('Lihat sertifikat PDF')->assertSee('https://example.com', false);
    }

    public function test_carousel_controls_only_render_for_multiple_globalindo_slides(): void
    {
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $home = $company->site->pages()->where('slug', 'home')->firstOrFail();
        $hero = $home->sections()->where('key', 'hero')->firstOrFail();
        $this->get(route('company.show', 'globalindo'))->assertDontSee('data-carousel-controls', false);
        SectionItem::factory()->for($hero, 'section')->create(['title' => 'Slide kedua', 'sort_order' => 2]);

        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('Slide kedua')->assertSee('data-carousel-controls', false)
            ->assertSee('aria-controls="cluster-home-hero-slides"', false)
            ->assertSee('id="cluster-home-hero-slides"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
    }

    public function test_catalog_dialog_preserves_uploaded_photo_and_contact_destination(): void
    {
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $company->products()->firstOrFail()->update(['image_path' => 'media/images/product.png']);

        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('gp-product-detail-image', false)
            ->assertSee('href="'.route('company.show', 'globalindo').'#cluster-contact-contact"', false);
        $this->assertSame(2, substr_count($response->getContent(), 'src="'.url('/storage/media/images/product.png').'"'));
    }
}
