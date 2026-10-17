<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SectionItem;
use App\Models\User;
use Database\Seeders\GlobalindoDemoContentSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;
use Tests\TestCase;

class GlobalindoDemoContentTest extends TestCase
{
    use DatabaseMigrations;

    public function test_demo_sections_render_in_order_with_labels_and_without_fake_documents_or_location(): void
    {
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $companyBefore = $company->getAttributes();
        $brand = $company->site->getAttributes();
        $this->seed(GlobalindoDemoContentSeeder::class);

        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSeeInOrder(['id="cluster-home-clients"', 'id="cluster-home-capacity"', 'id="cluster-home-csr"', 'id="cluster-about-us-vision-mission"', 'id="cluster-about-us-certifications"', 'id="cluster-about-us-the-group"', 'id="cluster-contact-map"', 'id="cluster-contact-contact"'], false)
            ->assertSee('Logo Klien 04 — Demo')->assertSee('Data simulasi')
            ->assertSee('bukan misi resmi Globalindo')->assertSee('Dokumen sertifikasi belum tersedia')
            ->assertSee('Placeholder peta — bukan lokasi Globalindo.')
            ->assertDontSee('maps.google.com', false)->assertDontSee('Lihat sertifikat PDF');
        $this->assertSame($companyBefore, $company->fresh()->getAttributes());
        $this->assertSame($brand, $company->site->fresh()->getAttributes());
        $this->assertSame(18, SectionItem::count());
        preg_match_all('/\bid="([^"]+)"/', $response->getContent(), $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
        $this->get(route('company.show', 'multipack'))->assertDontSee('Logo Klien 01 — Demo')->assertDontSee('Placeholder peta')->assertDontSee('Demo — lokasi perusahaan belum ditentukan.');
    }

    public function test_existing_items_are_preserved_and_reseeding_does_not_restore_deleted_demo_or_override_edits(): void
    {
        $this->seed();
        $site = Company::where('slug', 'globalindo')->firstOrFail()->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $clients = $home->sections()->where('key', 'clients')->firstOrFail();
        $existing = SectionItem::factory()->for($clients, 'section')->create(['title' => 'Konten admin']);
        $this->seed(GlobalindoDemoContentSeeder::class);
        $this->assertSame('Konten admin', $existing->fresh()->title);
        $this->assertSame(1, $clients->items()->count());
        $this->assertFalse($clients->fresh()->is_active);
        $csr = $home->sections()->where('key', 'csr')->firstOrFail();
        $item = $csr->items()->firstOrFail();
        $item->update(['title' => 'Draf suntingan admin']);
        $csr->update(['is_active' => false]);
        $csr->items()->where('id', '!=', $item->id)->delete();

        $this->seed(GlobalindoDemoContentSeeder::class);

        $this->assertSame('Draf suntingan admin', $item->fresh()->title);
        $this->assertSame(1, $csr->items()->count());
        $this->assertFalse($csr->fresh()->is_active);
    }

    public function test_admin_can_replace_demo_content_and_real_map_replaces_placeholder(): void
    {
        $this->seed();
        $this->seed(GlobalindoDemoContentSeeder::class);
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $site = $company->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $clients = $home->sections()->where('key', 'clients')->firstOrFail();
        $item = $clients->items()->firstOrFail();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.sites.pages.sections.items.update', [$site, $home, $clients, $item]), [
                'key' => $item->key, 'title' => 'Nama dari editor', 'sort_order' => 1, 'is_active' => true,
            ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Nama dari editor', $item->fresh()->title);
        $company->update(['map_query' => 'Lokasi uji']);
        $this->get(route('company.show', 'globalindo'))->assertSee('Nama dari editor')
            ->assertSee('maps.google.com/maps?q=Lokasi%20uji', false)->assertDontSee('Placeholder peta')->assertDontSee('Demo — lokasi perusahaan belum ditentukan.');
    }

    public function test_demo_map_is_hidden_when_company_is_no_longer_demo(): void
    {
        $this->seed();
        $this->seed(GlobalindoDemoContentSeeder::class);
        Company::where('slug', 'globalindo')->firstOrFail()->update(['is_demo' => false]);
        $this->get(route('company.show', 'globalindo'))->assertDontSee('id="cluster-contact-map"', false);
    }

    public function test_seeder_refuses_verified_company(): void
    {
        $this->seed();
        Company::where('slug', 'globalindo')->firstOrFail()->update(['is_demo' => false]);
        $this->expectException(RuntimeException::class);
        $this->seed(GlobalindoDemoContentSeeder::class);
    }

    public function test_seeder_refuses_production_environment(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        try {
            app(GlobalindoDemoContentSeeder::class)->run();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
