<?php

namespace Tests\Feature;

use App\Actions\Cms\MediaManager;
use App\Models\Company;
use App\Models\ProcessStep;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProcessMediaTest extends TestCase
{
    use DatabaseMigrations;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sample.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    /** @return array{title: string, description: string, sort_order: int, is_active: bool} */
    private function data(): array
    {
        return ['title' => 'Tahap uji', 'description' => 'Contoh tahap untuk pengujian.', 'sort_order' => 1, 'is_active' => true];
    }

    public function test_admin_can_create_preview_replace_remove_and_delete_process_photos(): void
    {
        Storage::fake('public');
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post(route('admin.content.store', [$company, 'process-steps']), [
            ...$this->data(), 'image' => $this->image(), 'image_alt' => 'Foto tahap uji',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.content.index', [$company, 'process-steps']));
        $step = $company->processSteps()->firstOrFail();
        $original = $step->image_path;
        Storage::disk('public')->assertExists($original);
        $this->assertSame('Foto tahap uji', $step->image_alt);
        $this->get(route('admin.content.edit', [$company, 'process-steps', $step]))
            ->assertOk()->assertSee('src="'.url('/storage/'.$original).'"', false)->assertSee('Foto tahap uji');

        $url = route('admin.content.update', [$company, 'process-steps', $step]);
        $this->put($url, [...$this->data(), 'image' => $this->image()])->assertSessionHasNoErrors();
        $replacement = $step->fresh()->image_path;
        $this->assertNotSame($original, $replacement);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($replacement);

        $this->put($url, [...$this->data(), 'remove_image' => 1])->assertSessionHasNoErrors();
        $this->assertNull($step->fresh()->image_path);
        Storage::disk('public')->assertMissing($replacement);

        $this->put($url, [...$this->data(), 'image' => $this->image()])->assertSessionHasNoErrors();
        $last = $step->fresh()->image_path;
        $this->delete(route('admin.content.destroy', [$company, 'process-steps', $step]))->assertRedirect();
        $this->assertModelMissing($step);
        Storage::disk('public')->assertMissing($last);
    }

    public function test_legacy_process_without_image_can_be_edited_without_changing_its_example_text(): void
    {
        $step = ProcessStep::factory()->create(['description' => 'Contoh tahap yang dapat disesuaikan.']);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.content.edit', [$step->company, 'process-steps', $step]))
            ->assertOk()->assertSee('Belum ada foto tahap proses.')->assertSee($step->description);
        $this->put(route('admin.content.update', [$step->company, 'process-steps', $step]), [
            ...$this->data(), 'description' => $step->description,
        ])->assertSessionHasNoErrors();
        $this->assertSame($step->description, $step->fresh()->description);
        $this->assertNull($step->fresh()->image_path);
        $this->assertNull($step->fresh()->image_alt);
    }

    /** @return array<string, array{string}> */
    public static function invalidUploads(): array
    {
        return ['oversized' => ['oversized'], 'svg' => ['svg'], 'script disguised as png' => ['script'], 'upload plus removal' => ['conflict']];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_upload_does_not_replace_process_photo(string $case): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/images/original.png', 'original');
        $step = ProcessStep::factory()->create(['image_path' => 'media/images/original.png']);
        $file = match ($case) {
            'oversized' => $this->image()->size(5121),
            'svg' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'script' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo "unsafe";'),
            default => $this->image(),
        };
        $source = $file;
        $file = new UploadedFile($source->getPathname(), $source->getClientOriginalName(), null, null, true);
        if ($case === 'oversized') {
            $file = $this->image()->size(5121);
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.content.update', [$step->company, 'process-steps', $step]), [
                ...$this->data(), 'image' => $file, 'remove_image' => $case === 'conflict',
            ])->assertSessionHasErrors('image');
        $this->assertSame('media/images/original.png', $step->fresh()->image_path);
        $this->assertSame(['media/images/original.png'], Storage::disk('public')->allFiles());
    }

    public function test_media_fields_cannot_bypass_validation_or_company_scope(): void
    {
        Storage::fake('public');
        $step = ProcessStep::factory()->create();
        $other = Company::factory()->create();
        $url = route('admin.content.update', [$step->company, 'process-steps', $step]);
        $this->put($url, [...$this->data(), 'image' => $this->image()])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put($url, [...$this->data(), 'image' => $this->image()])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->put(route('admin.content.update', [$other, 'process-steps', $step]), [...$this->data(), 'image' => $this->image()])->assertNotFound();
        $this->put($url, [...$this->data(), 'image_path' => '../secret', 'image_alt' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['image_path', 'image_alt']);
        $this->assertNull($step->fresh()->image_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_company_deletion_cleans_process_media_but_preserves_shared_files(): void
    {
        Storage::fake('public');
        foreach (['media/images/unique.png', 'media/images/shared.png'] as $path) {
            Storage::disk('public')->put($path, 'image');
        }
        $company = Company::factory()->create();
        $step = ProcessStep::factory()->for($company)->create(['image_path' => 'media/images/unique.png']);
        ProcessStep::factory()->for($company)->create(['image_path' => 'media/images/shared.png']);
        $other = ProcessStep::factory()->create(['image_path' => 'storage/media/images/shared.png']);
        app(MediaManager::class)->delete($company);
        $this->assertModelMissing($step);
        Storage::disk('public')->assertMissing('media/images/unique.png');
        Storage::disk('public')->assertExists('media/images/shared.png');
        $this->artisan('media:cleanup', ['path' => 'media/images/shared.png', '--delete' => true])->assertSuccessful();
        Storage::disk('public')->assertExists('media/images/shared.png');
        app(MediaManager::class)->delete($other);
        Storage::disk('public')->assertMissing('media/images/shared.png');
    }

    public function test_products_show_uploaded_thumbnail_and_empty_status_and_keep_replacement_flow(): void
    {
        Storage::fake('public');
        $company = Company::factory()->create();
        $product = Product::factory()->for($company)->create(['image_path' => null]);
        Product::factory()->for($company)->create(['image_path' => null]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $data = [...$product->only(['name', 'category', 'description', 'sort_order']), 'is_active' => true];
        $url = route('admin.content.update', [$company, 'products', $product]);
        $this->put($url, [...$data, 'image' => $this->image()])->assertSessionHasNoErrors();
        $original = $product->fresh()->image_path;
        $this->get(route('admin.content.index', [$company, 'products']))->assertOk()
            ->assertSee('src="'.url('/storage/'.$original).'"', false)->assertSee('Belum ada foto produk.')
            ->assertDontSee('/images/boxes.svg', false);
        $this->get(route('admin.content.edit', [$company, 'products', $product]))
            ->assertOk()->assertSee('src="'.url('/storage/'.$original).'"', false);
        $this->put($url, [...$data, 'image' => $this->image()])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_globalindo_renders_uploaded_process_photo_in_service_without_changing_brand_or_anchors(): void
    {
        Storage::fake('public');
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $site = $company->site;
        $brand = $site->only(['template_key', 'primary_color', 'secondary_color', 'font_family', 'logo_path']);
        $step = $company->processSteps()->firstOrFail();
        $description = $step->description;
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->put(route('admin.content.update', [$company, 'process-steps', $step]), [
            ...$step->only(['title', 'description', 'sort_order', 'is_active']),
            'image' => $this->image(), 'image_alt' => 'Mesin uji "><script>alert(1)</script>',
        ])->assertSessionHasNoErrors();
        $path = $step->fresh()->image_path;
        Storage::disk('public')->assertExists($path);
        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('src="'.url('/storage/'.$path).'"', false)
            ->assertSee('Mesin uji "><script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Contoh proses — belum diverifikasi sebagai proses resmi.')
            ->assertSee($description)
            ->assertSee('theme-globalindo', false)
            ->assertSeeInOrder(['id="cluster-service"', 'id="cluster-service-products"', 'id="cluster-service-process"', 'id="cluster-contact"'], false);
        preg_match_all('/\bid="([^"]+)"/', $response->getContent(), $ids);
        $this->assertSame($ids[1], array_values(array_unique($ids[1])));
        $this->assertSame($brand, $site->fresh()->only(array_keys($brand)));
        $this->get(route('company.page', ['globalindo', 'services']))->assertRedirect(route('company.show', 'globalindo').'#cluster-service');
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->get(route('admin.sites.pages.preview', [$site, $home]))
            ->assertOk()->assertSee('src="'.url('/storage/'.$path).'"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('class="inquiry-form"', false);
    }

    public function test_process_without_photo_keeps_text_and_other_brand_renders_uploaded_photo(): void
    {
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $company->update(['is_demo' => false]);
        $response = $this->get(route('company.show', 'globalindo'))->assertOk()
            ->assertSee('Contoh proses — belum diverifikasi sebagai proses resmi.');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@id="cluster-service-process"]//img')->length);
        foreach ($company->processSteps as $step) {
            $response->assertSee($step->title)->assertSee($step->description);
        }
        $other = Company::where('slug', 'multipack')->firstOrFail();
        $other->processSteps()->firstOrFail()->update(['image_path' => 'media/images/other.png']);
        $this->get(route('company.show', 'multipack'))->assertOk()
            ->assertSee($other->processSteps()->firstOrFail()->title)
            ->assertSee('src="'.url('/storage/media/images/other.png').'"', false);
    }

    public function test_migration_preserves_existing_process_records_with_nullable_media(): void
    {
        $migration = require database_path('migrations/2026_10_16_135513_add_images_to_process_steps_table.php');
        $migration->down();
        $step = ProcessStep::factory()->create(['description' => 'Contoh proses lama.']);
        $before = $step->fresh()->getAttributes();

        $migration->up();

        $after = $step->fresh();
        $this->assertSame($before, array_intersect_key($after->getAttributes(), $before));
        $this->assertNull($after->image_path);
        $this->assertNull($after->image_alt);
    }

    public function test_process_photo_falls_back_to_title_and_hidden_step_is_only_in_preview(): void
    {
        $this->seed();
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        $step = $company->processSteps()->firstOrFail();
        $step->update(['image_path' => 'media/images/process.png', 'image_alt' => null]);
        $this->get(route('company.show', 'globalindo'))->assertSee('alt="'.$step->title.'"', false);
        $step->update(['is_active' => false]);
        $this->get(route('company.show', 'globalindo'))->assertDontSee('/storage/media/images/process.png', false);
        $site = $company->site;
        $home = $site->pages()->where('slug', 'home')->firstOrFail();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.sites.pages.preview', [$site, $home]))
            ->assertSee('/storage/media/images/process.png', false);
    }
}
