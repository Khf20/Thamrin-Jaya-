# Kontrak Desain Frontend — Thamrin Jaya Group

Status: **spesifikasi pilot Globalindo disetujui; implementasi bertahap belum dimulai**  
Tanggal: 16 Oktober 2026

Dokumen ini menetapkan batas desain untuk portal holding dan enam situs anak perusahaan. Arah visual per brand di bawah adalah usulan berdasarkan logo dan warna yang sudah tersimpan pada data seed, bukan klaim bahwa palet atau tipografi tersebut telah disahkan pemilik brand.

## Tujuan

- Menjaga satu CMS dan satu kontrak konten, dengan presentasi yang jelas berbeda untuk holding dan setiap anak perusahaan.
- Menjadikan setiap `template_key` sebuah desain/layout nyata, bukan hanya nama tema atau perubahan warna.
- Menyediakan susunan halaman default yang konsisten dan siap dikelola; konten tetap dapat diubah melalui CMS.
- Tidak mengarang informasi perusahaan. Data klien, kapasitas, CSR, sertifikasi, sejarah, alamat, dan foto hanya ditampilkan jika konten valid tersedia dan dipublikasikan.

## Batas sistem desain

### Bersama untuk seluruh situs

- Header, navigasi CMS, footer, metadata/SEO, feedback form, status publikasi, dan mekanisme inquiry tetap menggunakan komponen serta perilaku bersama.
- Struktur informasi anak perusahaan: **Home**, **About Us**, **Service**, dan **Contact**. Holding memakai halaman yang sesuai untuk gateway, profil group, services, dan contact.
- Section tetap berasal dari `ContentRegistry`; template hanya memilih komposisi visual, urutan presentasi, tipografi, bentuk media, dan treatment komponen.
- Logo, warna utama/sekunder, footer, SEO, menu, konten, serta kontak tetap bersumber dari record `Site`, `Company`, `Page`, `PageSection`, `SectionItem`, `Service`, dan katalog yang sudah dimiliki CMS.
- Tidak menerima HTML, CSS, JavaScript, path Blade, atau nama class bebas dari isian admin.

### Diferensiasi yang wajib terlihat

Setiap situs harus berbeda minimal dalam tiga aspek struktural, bukan hanya warna:

1. Komposisi hero dan rasio/penempatan media.
2. Bentuk navigasi atau treatment navigasi/CTA.
3. Sistem heading/tipografi dan hierarki editorial.
4. Bentuk kartu, grid, atau presentasi katalog/proses.
5. Ritme section, treatment latar, dan footer.

Semua situs tetap harus terasa sebagai satu grup melalui kualitas, aksesibilitas, dan perilaku interaksi yang konsisten.

## Arah visual yang diusulkan

| Template key | Arah desain | Warna default dari seed | Tipografi awal | Ciri layout yang membedakan |
|---|---|---|---|---|
| `group-gateway` | Editorial holding; menghubungkan keahlian unit dalam satu grup | Cyan `#13add8`, charcoal `#101820` | Inter | Hero gateway/journey, navigasi group, transisi menuju enam unit, halaman holding lebih editorial daripada katalog. |
| `globalindo` | Produksi offset dan packaging; presisi cetak dan material | Amber `#f3ac18`, charcoal `#242424` | Montserrat | Layout editorial asimetris, aksen garis/registrasi cetak, panel proses dan kemasan. |
| `multipack` | Kardus dan karton; konstruksi, perlindungan, dan struktur | Cyan `#09add8`, navy `#12354a` | Roboto | Grid modular tegas, geometri panel/kotak, katalog dan proses berstruktur. |
| `hte-rotopack` | Flexible packaging; produksi dan spesifikasi | Red `#d71920`, graphite `#20282e` | Roboto | Hero kontras dengan fokus fotografi produk, panel spesifikasi, aksen industri; permukaan gelap hanya jika kontras teks lolos. |
| `maxtech` | Mesin dan solusi teknis | Indigo `#414b92`, navy `#171e35` | Inter | Hierarki teknis, grid terukur, blok spesifikasi/proses dan CTA konsultasi yang menonjol. |
| `sinar-jaya` | Material industri; pilihan bahan dan konsistensi | Cyan `#00a9cd`, red `#df1234` | Montserrat | Katalog/material-led, pembagian kategori yang jelas, aksen warna dipakai terukur. |
| `top-printing` | Digital printing; portofolio dan hasil visual | Red `#ed1b2f`, cyan `#15a6d1` | Poppins | Hero portofolio, grid hasil cetak/media, aksen CMYK dan komposisi yang lebih ekspresif. |

Nama font di atas adalah fallback implementasi dari font yang sudah diizinkan CMS, bukan pernyataan font resmi brand. Perubahan palet/font seed harus mengikuti konfirmasi brand dan tidak boleh menimpa nilai kustom yang sudah disimpan admin.

## Komposisi halaman default

Susunan berikut merupakan default template, bukan kewajiban menampilkan section kosong. Section nonaktif atau tanpa konten sah harus dilewati atau ditampilkan sebagai placeholder hanya di preview admin.

| Halaman | Holding | Anak perusahaan |
|---|---|---|
| Home | Gateway → ringkasan group → layanan → kontak/CTA. | Hero/carousel → about singkat → layanan → katalog unggulan bila tersedia → CTA kontak. |
| About Us | Sejarah/profil holding → visi/misi bila tersedia → group/unit → sertifikasi/CSR hanya jika ada data. | About/history perusahaan → keunggulan → visi/misi atau sertifikasi hanya jika datanya tersedia. |
| Service | Layanan group → unit penyedia layanan. | Layanan terkait → katalog produk → proses. |
| Contact | Kontak holding → peta/inquiry bila informasinya tersedia. | Kontak perusahaan → peta/WhatsApp/inquiry hanya untuk field yang terisi. |

Tidak ada statistik placeholder atau jumlah klien/kapasitas rekaan. Item tanpa konten yang cukup tidak menghasilkan kartu kosong pada halaman publik.

## Spesifikasi pilot one-page Globalindo — disepakati

Globalindo menjadi pilot sebelum pola ini diterapkan ke anak perusahaan lain. Halaman publik Globalindo memakai satu halaman panjang dengan empat navigasi tingkat atas. Navigasi mengarah ke cluster/anchor pada halaman yang sama; halaman inti CMS tetap dipakai sebagai sumber pengelolaan dan preview admin.

| Navigasi/cluster | Urutan isi yang disepakati | Sumber data CMS dan perlakuan media |
|---|---|---|
| **Home** | Carousel → About Us → Our Service → Client → Capacity → CSR | Carousel memakai item slide dan gambar slide; About Us memakai profil perusahaan dan gambar about; Our Service memakai layanan yang terhubung ke Globalindo; Client memakai item/logo klien; Capacity memakai item nilai, satuan, dan ikon; CSR memakai item kegiatan, gambar, dan tanggal jika tersedia. |
| **About Us** | History → Visi & Misi → Sertifikasi → The Group | History memakai riwayat perusahaan; visi/misi memakai item bertipe vision dan mission; sertifikasi memakai item sertifikat, penerbit/tanggal, gambar dan/atau PDF; The Group memakai relasi perusahaan yang memang diizinkan untuk ditampilkan. Section Keunggulan tidak termasuk susunan pilot ini. |
| **Service** | Our Service + mega-menu → Katalog Produk → Proses | Layanan berasal dari relasi layanan Globalindo dan menu mega-menu hanya menjadi navigasi/pintasan, bukan pengganti konten layanan. Katalog memakai produk milik Globalindo beserta kategori, deskripsi, spesifikasi, dan foto jika tersedia. Proses memakai langkah dan deskripsi proses Globalindo. |
| **Contact** | Map → Form Contact | Map memakai alamat/query peta dari kontak perusahaan; form memakai mekanisme inquiry yang sudah ada. Map hanya tampil bila lokasi tersedia, dan data kontak yang kosong tidak menghasilkan placeholder rekaan. |

### Ketentuan implementasi pilot

- Empat cluster tingkat atas dan urutan di tabel ini menjadi struktur pilot; section kosong/nonaktif tetap tidak tampil di halaman publik, tetapi dapat dikelola/ditinjau melalui CMS dan preview admin.
- Menu tingkat atas Home, About Us, Service, Contact, item mega-menu, CTA, dan tautan dari produk harus menuju anchor yang benar di halaman Globalindo yang sama, tanpa fragment ganda atau ID yang bentrok.
- Field konten tetap dapat diedit melalui bagian admin yang sesuai dengan sumber data. Jangan menduplikasi satu konten yang sama sebagai teks hard-coded pada view.
- Prioritaskan foto resmi/upload untuk carousel, About Us, layanan, produk, klien, CSR, sertifikasi, dan The Group. History, visi/misi, capacity, map, dan form tidak diwajibkan memakai foto.
- Ilustrasi sektor boleh menjadi fallback sementara, tetapi harus diberi label jelas sebagai ilustrasi. Jangan menyajikannya sebagai foto produk, fasilitas, klien, atau sertifikasi yang sebenarnya.
- Pertahankan `template_key` Globalindo, palet/typography site yang sudah tersimpan, logo, layout identity, dan responsivitas yang ada. Penyesuaian tahap pilot mengubah susunan konten/navigasi, bukan mengganti brand skin Globalindo.
- Setelah Globalindo lulus verifikasi admin → database → halaman publik, spesifikasi ini baru dijadikan pola adaptasi untuk anak perusahaan lain; konten dan visual brand tiap perusahaan tetap berbeda.

## Aturan UX, aksesibilitas, dan responsif

- Mobile-first; navigasi, formulir, katalog, dan CTA harus dapat digunakan pada lebar 360 px tanpa scroll horizontal.
- Mendukung keyboard, focus-visible, skip link, label form, alt text, dan struktur heading semantik.
- Target kontras teks normal minimal WCAG AA (4.5:1); warna brand tidak boleh otomatis dipakai sebagai warna teks jika gagal kontras.
- Gerakan dekoratif harus menghormati `prefers-reduced-motion`; konten tetap lengkap tanpa JavaScript.
- Gambar bersifat responsif, memakai rasio yang sesuai layout dan `loading="lazy"` di luar area awal.
- Status preview/draft harus terlihat hanya untuk admin; preview tetap `noindex` dan tidak mengaktifkan submit inquiry.

## Keputusan implementasi tahap berikutnya

- Petakan `template_key` ke allowlist layout/partial server-side. Jangan menerima nama view dari database sebagai path bebas.
- Pakai renderer/resolver konten saat ini dan buat adapter komponen per template. Jangan duplikasi aturan publikasi, URL, izin, atau resolver data.
- Pertahankan jenis section dan sumber data yang ada; variasi desain dilakukan pada template/komponen presentasi, bukan menggandakan data CMS.
- Sebelum merender, hilangkan section/item kosong atau tidak valid secara konsisten. Preview admin boleh menunjukkan status kosong/nonaktif.
- Buat visual regression/screenshot acceptance untuk tujuh template di desktop dan mobile, selain tes fungsional yang sudah ada.

## Kriteria penerimaan kontrak

1. Holding dan enam anak perusahaan masing-masing memiliki layout dan hierarki tipografi yang dapat dibedakan tanpa melihat warna/logo saja.
2. Semua layout menggunakan data yang sama dari CMS; mengganti konten tidak memerlukan perubahan Blade atau deploy.
3. Halaman default tidak menampilkan section kosong, data demo yang belum diverifikasi, atau klaim bisnis yang dibuat oleh template.
4. Navigasi, CTA internal, SEO, publikasi/preview, inquiry, dan status aktif tetap berfungsi pada setiap template.
5. Tiap template lulus pemeriksaan keyboard, kontras, reduced-motion, viewport 360 px, tablet, dan desktop.

## Hal yang menunggu persetujuan pemilik brand

- Apakah palet seed di atas benar untuk digunakan sebagai warna default resmi.
- Apakah tipografi usulan diterima atau tersedia panduan brand/font resmi.
- Foto produk, fasilitas, mesin, karya cetak, serta izin penggunaannya untuk masing-masing situs.
- Apakah layout gelap untuk HTE Rotopack/Maxtech sesuai identitas dan keterbacaan yang diinginkan.
- Konten resmi untuk section opsional: klien, kapasitas, CSR, sertifikasi, sejarah, visi/misi, alamat, dan kontak.

Jika belum ada konfirmasi, implementasi sebaiknya menggunakan nilai seed sebagai fallback visual sementara dan menjaga semua nilai tetap dapat dikonfigurasi melalui CMS.