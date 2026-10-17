<?php

namespace App\Actions\Cms;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GlobalindoPilot
{
    public const DEMO_MAP_SUBTITLE = 'Demo — lokasi perusahaan belum ditentukan.';

    /** @var array<string, list<array{string, string, string, bool}>> */
    public const SECTIONS = [
        'home' => [
            ['hero', 'carousel', 'Carousel', true],
            ['about', 'about', 'About Us', true],
            ['services', 'services', 'Our Service', true],
            ['clients', 'clients', 'Client', false],
            ['capacity', 'capacity', 'Capacity', false],
            ['csr', 'csr', 'CSR', false],
        ],
        'about-us' => [
            ['history', 'history', 'History', true],
            ['vision-mission', 'vision_mission', 'Visi & Misi', false],
            ['certifications', 'certifications', 'Sertifikasi', false],
            ['the-group', 'group', 'The Group', true],
        ],
        'services' => [
            ['services', 'services', 'Our Service', true],
            ['products', 'products', 'Katalog Produk', true],
            ['process', 'process', 'Proses', true],
        ],
        'contact' => [
            ['map', 'map', 'Map', true],
            ['contact', 'contact', 'Form Contact', true],
        ],
    ];

    /** @var array<string, string> */
    public const GUIDANCE = [
        'carousel' => 'Kelola item slide: foto resmi, judul singkat, deskripsi, dan tombol. Jangan gunakan ilustrasi sebagai foto fasilitas.',
        'about' => 'Kelola ringkasan dan foto pendamping melalui profil perusahaan. Home berfungsi sebagai pengantar.',
        'services' => 'Kelola layanan yang terhubung ke Globalindo. Home berisi pengantar; Service memuat layanan, katalog, dan proses. Foto layanan opsional.',
        'clients' => 'Isi nama dan logo klien yang sudah mendapat izin publikasi melalui item section.',
        'capacity' => 'Isi angka kapasitas, satuan, dan judul yang terverifikasi melalui item section. Ikon tersedia; foto tidak wajib.',
        'csr' => 'Isi kegiatan nyata, cerita, tanggal, dan foto dokumentasi melalui item section.',
        'history' => 'Isi riwayat resmi melalui profil perusahaan. Foto tidak wajib.',
        'vision_mission' => 'Isi teks resmi pada item Visi dan Misi. Foto tidak diperlukan.',
        'certifications' => 'Isi nama sertifikat, penerbit, gambar dan/atau PDF melalui item section.',
        'group' => 'Identitas unit diambil dari perusahaan yang dipublikasikan. Kelola logo dan profil pada situs masing-masing.',
        'products' => 'Kelola katalog dan foto asli pada Produk perusahaan. Ilustrasi bukan foto produk.',
        'process' => 'Kelola urutan, nama, deskripsi, serta foto opsional pada Proses perusahaan. Verifikasi teks contoh sebelum menganggapnya resmi.',
        'map' => 'Isi lokasi peta melalui kontak perusahaan. Peta tidak muncul jika lokasi kosong.',
        'contact' => 'Kontak berasal dari profil perusahaan; form mengirim inquiry ke inbox Globalindo.',
    ];

    public static function applies(Site $site): bool
    {
        return $site->company?->slug === 'globalindo';
    }

    /**
     * Only structure is changed. Existing copy, media, branding and publication choices are preserved.
     * Missing sections are inactive so this operation never publishes unreviewed content.
     *
     * @return list<string>
     */
    public function align(Site $site): array
    {
        if (! self::applies($site)) {
            throw new RuntimeException('Penyelarasan ini hanya untuk Globalindo.');
        }

        return DB::transaction(function () use ($site): array {
            $site->newQuery()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $changes = [];
            $pages = $site->pages()->lockForUpdate()->get()->keyBy('slug');
            foreach (self::SECTIONS as $slug => $definitions) {
                $page = $pages->get($slug);
                if (! $page) {
                    throw new RuntimeException('Halaman inti belum tersedia: '.$slug.'. Tidak ada perubahan disimpan.');
                }
                $sections = $page->sections()->lockForUpdate()->get();
                $keys = array_column($definitions, 0);
                foreach ($sections as $section) {
                    if (! in_array($section->key, $keys, true) && $section->is_active && $section->type !== 'pillars') {
                        throw new RuntimeException('Section kustom aktif perlu ditinjau: '.$slug.'/'.$section->key.'. Tidak ada perubahan disimpan.');
                    }
                }
                foreach ($definitions as $index => [$key, $type, $title]) {
                    $section = $sections->firstWhere('key', $key);
                    if ($section && ($section->type !== $type || ($section->settings['source'] ?? null) !== ContentRegistry::source($type, true))) {
                        throw new RuntimeException('Konflik jenis/sumber section: '.$slug.'/'.$key.'. Tidak ada perubahan disimpan.');
                    }
                    if (! $section) {
                        $page->sections()->create([
                            'key' => $key, 'type' => $type, 'title' => $title, 'sort_order' => $index + 1,
                            'settings' => ['source' => ContentRegistry::source($type, true)], 'is_active' => false,
                        ]);
                        $changes[] = 'Tambah section nonaktif '.$slug.'/'.$key;
                    } elseif ($section->sort_order !== $index + 1) {
                        $section->update(['sort_order' => $index + 1]);
                        $changes[] = 'Urutkan '.$slug.'/'.$key;
                    }
                }
                foreach ($sections->where('type', 'pillars')->where('is_active', true) as $section) {
                    $section->update(['is_active' => false]);
                    $changes[] = 'Nonaktifkan '.$slug.'/'.$section->key.' (data dipertahankan)';
                }
            }

            foreach (['header', 'footer'] as $menuKey) {
                $menu = $site->menus()->firstOrCreate(['key' => $menuKey], ['name' => $menuKey === 'header' ? 'Navigasi Utama' : 'Navigasi Footer']);
                foreach (['home' => 'Home', 'about-us' => 'About Us', 'services' => 'Service', 'contact' => 'Contact'] as $slug => $label) {
                    $position = array_search($slug, array_keys(self::SECTIONS), true) + 1;
                    $root = $this->menuItem($menu, $slug, [
                        'page_id' => $pages[$slug]->id, 'parent_id' => null, 'service_id' => null, 'anchor' => null, 'url' => null,
                    ], $label, $position, $changes);
                    if ($menuKey === 'header' && $slug === 'services') {
                        if ($root->type !== 'mega_menu') {
                            $root->update(['type' => 'mega_menu']);
                            $changes[] = 'Aktifkan bentuk mega-menu Service';
                        }
                        foreach (['products' => 'Katalog Produk', 'process' => 'Proses'] as $key => $title) {
                            $this->menuItem($menu, 'pilot-'.$key, [
                                'page_id' => $pages[$slug]->id, 'parent_id' => $root->id, 'service_id' => null, 'anchor' => $key, 'url' => null,
                            ], $title, $key === 'products' ? 90 : 91, $changes);
                        }
                    }
                }
            }

            return $changes;
        });
    }

    /**
     * Refuse conflicting targets rather than overwrite an editor's custom destination.
     *
     * @param  array{page_id: int, parent_id: ?int, service_id: null, anchor: ?string, url: null}  $target
     * @param  list<string>  $changes
     */
    private function menuItem(Menu $menu, string $key, array $target, string $label, int $order, array &$changes): MenuItem
    {
        $item = $menu->items()->where('key', $key)->lockForUpdate()->first();
        if (! $item) {
            $changes[] = 'Tambah menu '.$menu->key.'/'.$key;

            return $menu->items()->create([...$target, 'key' => $key, 'label' => $label, 'sort_order' => $order, 'type' => 'link', 'is_active' => true]);
        }
        foreach ($target as $field => $value) {
            if ($item->getAttribute($field) != $value) {
                throw new RuntimeException('Konflik target menu: '.$menu->key.'/'.$key.'. Tidak ada perubahan disimpan.');
            }
        }
        if ($item->sort_order !== $order) {
            $item->update(['sort_order' => $order]);
            $changes[] = 'Urutkan menu '.$menu->key.'/'.$key;
        }

        return $item;
    }
}
