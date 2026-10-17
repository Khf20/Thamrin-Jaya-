<?php

namespace App\Console\Commands;

use App\Actions\Cms\GlobalindoPilot;
use App\Models\Company;
use Illuminate\Console\Command;
use RuntimeException;

class AlignGlobalindoPilot extends Command
{
    protected $signature = 'cms:align-globalindo {--apply : Terapkan struktur pilot tanpa mengganti isi atau branding}';

    protected $description = 'Periksa struktur Globalindo; hanya --apply mengubah urutan, section, dan menu pilot';

    public function handle(GlobalindoPilot $pilot): int
    {
        $site = Company::where('slug', 'globalindo')->first()?->site;
        if (! $site) {
            $this->error('Situs Globalindo belum tersedia.');

            return self::FAILURE;
        }
        if ($this->option('apply')) {
            try {
                $changes = $pilot->align($site);
            } catch (RuntimeException $error) {
                $this->error($error->getMessage());

                return self::FAILURE;
            }
            foreach ($changes as $change) {
                $this->line($change);
            }
            $this->info(count($changes).' penyesuaian disimpan.');
        }
        foreach (GlobalindoPilot::SECTIONS as $slug => $definitions) {
            $page = $site->pages()->where('slug', $slug)->first();
            $this->line($slug.' — target: '.implode(' → ', array_column($definitions, 2)));
            foreach ($page?->sections()->withCount('items')->get() ?? [] as $section) {
                $this->line('  '.$section->sort_order.'. '.$section->key.' ['.($section->is_active ? 'aktif' : 'nonaktif').'] '.$section->items_count.' item manual');
            }
        }
        $this->line('Section kosong/nonaktif tetap tidak tampil publik. Isi sumber perusahaan/relasi tidak dihitung sebagai item manual.');

        return self::SUCCESS;
    }
}
