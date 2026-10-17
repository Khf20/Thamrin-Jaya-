<?php

namespace Database\Seeders;

use App\Actions\Cms\GlobalindoPilot;
use App\Actions\Cms\SeedOnce;
use App\Models\Company;
use Illuminate\Database\Seeder;
use RuntimeException;

class GlobalindoDemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Konten demo Globalindo hanya untuk local/testing.');
        }
        $company = Company::where('slug', 'globalindo')->firstOrFail();
        if (! $company->is_demo || ! $company->site) {
            throw new RuntimeException('Globalindo harus memiliki situs dan masih berstatus demo.');
        }

        SeedOnce::run('globalindo-demo-content-v1', function () use ($company): void {
            $site = $company->site;
            $site->newQuery()->whereKey($site->id)->lockForUpdate()->firstOrFail();
            $definitions = [
                'home' => [
                    'clients' => array_map(fn (int $number): array => [
                        'key' => 'demo-client-'.$number,
                        'title' => 'Logo Klien 0'.$number.' — Demo',
                    ], range(1, 4)),
                    'capacity' => [
                        ['key' => 'demo-volume', 'title' => 'Volume produksi — Data simulasi', 'value' => '100.000', 'unit' => 'unit/bulan (simulasi)', 'settings' => ['icon' => 'factory']],
                        ['key' => 'demo-lines', 'title' => 'Lini produksi — Data simulasi', 'value' => '3', 'unit' => 'lini (simulasi)', 'settings' => ['icon' => 'factory']],
                        ['key' => 'demo-quality', 'title' => 'Tahap pemeriksaan — Data simulasi', 'value' => '4', 'unit' => 'tahap (simulasi)', 'settings' => ['icon' => 'quality']],
                    ],
                    'csr' => [
                        ['key' => 'demo-csr-environment', 'title' => 'Demo CSR — Kepedulian lingkungan', 'body' => 'Contoh materi kegiatan: edukasi pemilahan sampah dan penggunaan material secara bertanggung jawab. Ini bukan laporan kegiatan Globalindo. Foto, tanggal, dan rincian kegiatan resmi belum tersedia.'],
                        ['key' => 'demo-csr-community', 'title' => 'Demo CSR — Berbagi pengetahuan', 'body' => 'Contoh materi kegiatan: pengenalan desain kemasan kepada komunitas. Ini hanya draf untuk meninjau tampilan, bukan klaim kegiatan Globalindo. Dokumentasi resmi belum tersedia.'],
                    ],
                ],
                'about-us' => [
                    'vision-mission' => [
                        ['key' => 'vision', 'title' => 'Draf Visi — Demo', 'body' => 'Menjadi mitra solusi cetak dan kemasan yang mengutamakan kualitas serta kebutuhan pelanggan. Draf contoh ini belum disetujui perusahaan dan bukan visi resmi Globalindo.'],
                        ['key' => 'mission', 'title' => 'Draf Misi — Demo', 'body' => "Mengembangkan solusi kemasan sesuai kebutuhan pelanggan.\nMenjaga komunikasi dan perhatian pada mutu pekerjaan.\nMendorong perbaikan proses serta penggunaan material yang bertanggung jawab.\nDraf ini belum disetujui perusahaan dan bukan misi resmi Globalindo."],
                    ],
                    'certifications' => [
                        ['key' => 'demo-certificate', 'title' => 'Demo — Dokumen sertifikasi belum tersedia', 'body' => 'Area ini disiapkan untuk sertifikat yang telah diverifikasi. Belum ada klaim sertifikasi, penerbit, nomor, tanggal, atau dokumen resmi. Ganti item ini dan unggah gambar/PDF melalui CMS setelah dokumen tersedia.'],
                    ],
                ],
            ];
            foreach ($definitions as $slug => $sections) {
                $page = $site->pages()->where('slug', $slug)->firstOrFail();
                foreach ($sections as $key => $items) {
                    $section = $page->sections()->where('key', $key)->lockForUpdate()->firstOrFail();
                    $type = $key === 'vision-mission' ? 'vision_mission' : $key;
                    if ($section->type !== $type || ($section->settings['source'] ?? null) !== 'items') {
                        throw new RuntimeException('Sumber section tidak sesuai: '.$slug.'/'.$key);
                    }
                    if ($section->items()->exists()) {
                        continue;
                    }
                    foreach ($items as $index => $attributes) {
                        $section->items()->create([...$attributes, 'sort_order' => $index + 1, 'is_active' => true]);
                    }
                    $section->update(['is_active' => true]);
                    if ($slug === 'about-us') {
                        $site->menus()->where('key', 'header')->first()?->items()
                            ->where('page_id', $page->id)->where('anchor', $key)
                            ->where('key', 'about-'.$key)->update(['is_active' => true]);
                    }
                }
            }
            $map = $site->pages()->where('slug', 'contact')->firstOrFail()->sections()->where('key', 'map')->lockForUpdate()->firstOrFail();
            if ($map->type !== 'map') {
                throw new RuntimeException('Jenis section peta tidak sesuai.');
            }
            if (blank($company->map_query) && blank($map->subtitle)) {
                $map->update(['subtitle' => GlobalindoPilot::DEMO_MAP_SUBTITLE, 'is_active' => true]);
            }
        });
    }
}
