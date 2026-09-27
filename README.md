# Patek Life Perde

Toptan perde satışı yapan bir firma için geliştirilmiş **tanıtım sitesi, ürün kataloğu ve stok / cari takip sistemi**. Tek bir Laravel uygulamasıdır: herkese açık site Blade ile, yönetim paneli Filament ile yazılmıştır.

**Canlı site:** https://pateklifeperde.com

> A short English summary is available [at the bottom](#english-summary).

## Genel bakış

Proje iki bölümden oluşur:

| Bölüm | Adres | Kim kullanır | Ne yapar |
|---|---|---|---|
| Tanıtım sitesi ve katalog | `/`, `/urunler` | Herkes | Firmayı tanıtır, aktif ürünleri fiyat ve stok göstermeden listeler |
| Yönetim paneli | `/panel` | Giriş yapan personel | Ürün, stok, fiş, cari hesap, tahsilat, geçmiş kayıtlar ve yedekleme |

Sistem bir dükkânın günlük işleyişine göre tasarlandı: mal gelir (stok girişi), müşteriye fiş kesilir (stok düşer, müşteri borçlanır), ödeme alınır (tahsilat, borç kapanır). Her adım kayıt altındadır ve geriye dönük izlenebilir.

Fişler resmi fatura değildir; iç kullanım belgesidir. E-fatura veya e-arşiv entegrasyonu yoktur.

## Özellikler

### Tanıtım sitesi ve katalog

- Slider, ürün grupları, "neden biz", hakkımızda ve iletişim bölümlerinden oluşan ana sayfa
- Kategori filtresi ve arama içeren ürün kataloğu; yalnızca aktif ürünler görünür
- WhatsApp, Instagram ve Google Haritalar bağlantıları (`.env` üzerinden açılıp kapanır)
- SEO: canonical adres, Open Graph etiketleri, JSON-LD yapısal veri
- `robots.txt` ve `sitemap.xml` statik dosya değildir, veritabanındaki ürünlere göre rotadan üretilir
- Türkçe hata sayfaları (403, 404, 419, 429, 500, 503)
- Site metinleri `config/site.php`, firma bilgileri `.env` içinde tutulur; kod değiştirmeden güncellenir

### Yönetim paneli

| Menü grubu | Sayfa | Açıklama |
|---|---|---|
| Ürünler | Ürün Kartları | Ürün ekleme, düzenleme, görsel yükleme; silinen ürünler "Son silinenler" sekmesinden geri alınır |
| Ürünler | Kategoriler | Kategori tanımları ve sıralaması |
| Stok | Stok Giriş | Tek seferde birden fazla ürün için giriş |
| Stok | Canlı Stok | 30 saniyede bir yenilenen stok listesi; kritik seviyenin altındaki ürünler kırmızı |
| Stok | Stok Çıkışı | Satış dışı çıkışlar (fire, numune, iade, sayım); sebep zorunlu |
| Satış | Fiş Kayıt | Fiş kesme, görüntüleme, iptal, termal ve A4 PDF çıktısı |
| Cari | Müşteriler | Müşteri kartı, güncel bakiye, cari hareketler, tarih aralıklı ekstre PDF'i |
| Cari | Tahsilatlar | Tahsilat kaydı, makbuz numarası, iptal |
| Geçmiş | Stok Geçmişi, Fiş Geçmişi, Cari Hareketler | Salt okunur listeler; tarih aralığı filtresi ve CSV indirme |
| Sistem | Log Hareketleri | Kim, ne zaman, neyi değiştirdi |
| Sistem | Kullanıcılar | Kullanıcı hesapları, aktif/pasif durumu |
| Sistem | Yedekleme | Tam yedek (ZIP) ve Excel kopyası indirme |

**Ana sayfa (dashboard):** bugünkü satış, aylık satış, açık alacak ve kritik stok özetleri; satış ve stok hareketi grafikleri; son fişler ve kritik stoktaki ürünler.

**Fiş kesme**

- Ödeme tipi: açık hesap (tutar müşterinin borcuna yazılır) veya peşin (fişle birlikte tahsilat makbuzu kesilir)
- Birim fiyat ürün kartından dolu gelir, fişte değiştirilebilir; sonradan ürün fiyatı değişse de fiş değişmez
- Müşteri seçilince güncel bakiye ve fiş sonrası bakiye önizlemesi görünür
- Bir kalemde stok yetersizse fiş hiç oluşmaz; hangi üründe kaç birim eksik olduğu gösterilir
- Fiş çıktısında cari hesap durumu yer alır: önceki bakiye, fiş tutarı, varsa peşin tahsilat, fiş sonrası bakiye

**PDF çıktıları**

- 80 mm termal fiş (baskı alanı 72 mm, sayfa boyu içeriğe göre ayarlanır, tek sayfa)
- A4 fiş
- A4 müşteri ekstresi (devreden bakiye, hareketler, kapanış bakiyesi)
- İptal edilmiş fişte belirgin "İPTAL" damgası

**Giriş ve güvenlik**

- Kullanıcı adı veya e-posta ile giriş
- Giriş denemesi sınırı
- İsteğe bağlı iki adımlı doğrulama (uygulama tabanlı, kurtarma kodlu)
- Pasif kullanıcı giriş yapamaz

## Teknolojiler

| Katman | Teknoloji |
|---|---|
| Dil | PHP 8.3 |
| Çatı | Laravel 13 |
| Yönetim paneli | Filament 5 (Livewire, SPA modu) |
| Veritabanı | MySQL 8 |
| Ön yüz | Blade, Tailwind CSS 4, Vite |
| PDF | barryvdh/laravel-dompdf |
| İşlem kayıtları | spatie/laravel-activitylog |
| Excel yedeği | OpenSpout |
| Testler | Pest 4 |
| Barındırma | Hostinger paylaşımlı hosting |

## Mimari ve tasarım kararları

Projenin en önemli kısmı stok ve para hesaplarının doğruluğudur. Bunu sağlayan kurallar:

**1. Özet alanlar hareketlerden türetilir.**
`urunler.mevcut_stok` ve `musteriler.bakiye` alanları hiçbir formdan düzenlenemez. Yalnızca servisler günceller ve her güncelleme bir hareket kaydıyla birlikte yapılır. Stok, `stok_hareketleri` tablosunun; bakiye, `cari_hareketler` tablosunun toplamıdır.

**2. İş mantığı servis katmanındadır.**
Filament sayfaları iş mantığı içermez, servisleri çağırır.

| Servis | Görevi |
|---|---|
| `StokServisi` | Stok girişi ve çıkışı |
| `CariServisi` | Borçlandırma, alacaklandırma, ekstre, açılış bakiyesi düzeltme |
| `FisServisi` | Fiş kesme ve iptal |
| `TahsilatServisi` | Tahsilat kaydı ve iptal |
| `YedekServisi` | ZIP ve Excel yedeği |

**3. Her işlem tek transaction içindedir ve satır kilitlenir.**
Fiş kesilirken fiş, kalemler, stok düşümleri, cari borç ve varsa peşin tahsilat aynı `DB::transaction()` içinde çalışır. Herhangi bir adım başarısız olursa hiçbiri kaydedilmez. İlgili ürün ve müşteri satırları `lockForUpdate()` ile kilitlenir; aynı anda iki kişi işlem yapsa bile stok eksiye düşmez.

**4. Hesaplamalarda `float` kullanılmaz.**
Para ve miktar hesapları `bcmath` ile yapılır. `0,1 + 0,2` gibi ondalıklı işlemlerde kayma oluşmaz.

**5. Kayıtlar silinmez ve düzenlenmez.**
Fiş ve tahsilat yalnızca iptal edilir; iptal ters yönlü hareket üretir. Hatalı stok hareketi `sayim`, hatalı cari hareket `duzeltme` kaydıyla düzeltilir. Ürün ve müşteriler soft delete ile pasife alınır. Yabancı anahtarlarda `cascadeOnDelete` yoktur.

**6. Sıralı belge numaraları.**
Fiş ve makbuz numaraları `YYYY-NNNNNN` biçimindedir (örn. `2026-000125`). Sayaç tablosundan kilitlenerek alınır; atlamaz, tekrar etmez, her yıl 1'den başlar.

**7. Tutarlılık denetimi.**
`stok:dogrula` ve `cari:dogrula` komutları özet alanları hareketlerden yeniden hesaplar ve fark varsa raporlar.

**8. Paylaşımlı hostinge uygunluk.**
Kuyruk işçisi ve websocket kullanılmaz. CSV dışa aktarma ve yedekleme istek içinde, eşzamanlı çalışır. Yayın paketi Git ve Composer gerektirmeden ZIP olarak yüklenir.

## Veritabanı

| Tablo | İçerik |
|---|---|
| `users` | Panel kullanıcıları |
| `kategoriler` | Ürün kategorileri |
| `urunler` | Ürün kartları, fiyatlar, kritik stok ve mevcut stok |
| `stok_hareketleri` | Her stok giriş ve çıkışı (yön, sebep, miktar, kullanıcı) |
| `musteriler` | Müşteri kartları, açılış bakiyesi ve güncel bakiye |
| `fisler`, `fis_kalemleri` | Satış fişleri ve kalemleri |
| `tahsilatlar` | Tahsilat makbuzları |
| `cari_hareketler` | Her borç ve alacak kaydı, işlem sonrası bakiye |
| `fis_numaralari`, `makbuz_numaralari` | Yıllık sıra numarası sayaçları |
| `activity_log` | İşlem kayıtları |

Tablo ve sütun adları Türkçedir (ASCII karakterlerle). Sabit değer kümeleri PHP enum olarak tanımlıdır (`app/Enums`). Ayrıntılı şema ve tüm iş kuralları [CLAUDE.md](CLAUDE.md) dosyasındadır.

## Kurulum

### Gereksinimler

- PHP 8.3 veya üstü; `bcmath`, `gd`, `zip`, `mbstring`, `intl`, `pdo_mysql` uzantıları
- Composer
- Node.js ve npm
- MySQL 8

### Adımlar

```bash
git clone https://github.com/erkamtasdemirr11/patek-life-perde.git
cd patek-life-perde

composer install
npm install
```

`.env.example` dosyasını `.env` adıyla kopyalayın:

```bash
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

MySQL'de `patek_perde` adında bir veritabanı oluşturun ve `.env` içindeki `DB_*` alanlarını kendi bilgilerinize göre düzenleyin. Ardından:

```bash
php artisan migrate
php artisan storage:link
npm run build

# İlk yönetici hesabı (ad, e-posta ve şifre sorar)
php artisan make:filament-user

php artisan serve
```

- Site: http://localhost:8000
- Panel: http://localhost:8000/panel

### Ortam değişkenleri

| Değişken | Açıklama |
|---|---|
| `FIRMA_ADI`, `FIRMA_ADRES`, `FIRMA_TELEFON`, `FIRMA_EPOSTA` | Sitede ve fiş çıktılarında görünen firma bilgileri |
| `SITE_WHATSAPP` | 90 ile başlayan, boşluksuz numara; boşsa WhatsApp butonu görünmez |
| `SITE_INSTAGRAM` | Instagram adresi |
| `SITE_HARITA_EMBED` | Google Haritalar yerleştirme adresi; boşsa harita görünmez |
| `GOOGLE_SITE_VERIFICATION` | Search Console doğrulama kodu |

## Testler

Testler Pest ile yazılmıştır ve ayrı bir veritabanında çalışır. Önce MySQL'de `patek_perde_test` adında boş bir veritabanı oluşturun.

```bash
php artisan test
```

Kapsanan başlıca durumlar:

- Stok girişi ve çıkışı; yetersiz stokta işlemin tamamen geri alınması
- Ondalıklı miktar ve tutarlarda hesap kayması olmaması
- Fiş kesme: stok düşümü, toplamlar, sıralı numara, müşteri bakiyesi
- Bir kalemde stok yetersizse fişin, diğer stok düşümlerinin ve cari kaydın hiç oluşmaması
- Peşin fişte tahsilatın aynı işlemde kesilmesi; iptalde bağlı tahsilatın da iptali
- Fiş ve tahsilat iptali; iptal edilmiş kaydın tekrar iptal edilememesi
- Açılış bakiyesi ve açılış bakiyesi düzeltme
- Fiş PDF'inin termal genişlikte ve tek sayfa üretilmesi
- `stok:dogrula` ve `cari:dogrula` komutlarının tutarsızlığı yakalaması
- Panel sayfaları, giriş, katalog, site, yedekleme ve yayın paketi

## Komutlar

| Komut | Açıklama |
|---|---|
| `php artisan serve` | Yerel sunucu |
| `php artisan test` | Testler |
| `npm run dev` / `npm run build` | Ön yüz dosyaları |
| `php artisan stok:dogrula` | Stok tutarlılık denetimi (`--duzelt` ile düzeltir) |
| `php artisan cari:dogrula` | Cari bakiye tutarlılık denetimi (`--duzelt` ile düzeltir) |
| `php artisan yayin:paketle` | Sunucuya yüklenecek ZIP paketi (`--vendorsuz` seçeneği var) |

## Yayına alma

Proje Hostinger paylaşımlı hostingde çalışır. `php artisan yayin:paketle` komutu kod, `vendor` ve derlenmiş `public/build` klasörünü içeren bir ZIP üretir; `.env`, testler ve loglar pakete girmez. Paket hPanel Dosya Yöneticisi'nden yüklenir.

Adım adım rehber ve sorun giderme: [YAYIN.md](YAYIN.md). Canlı ortam ayar örneği: [.env.production.example](.env.production.example).

## Yedekleme

Yedekler panelden, **Sistem → Yedekleme** sayfasından indirilir:

- **Tam yedek (ZIP):** Veritabanının SQL dökümü ve ürün görselleri. Döküm `mysqldump` gerektirmez, PHP ile üretilir. İçinde geri yükleme talimatı bulunur.
- **Excel kopyası (.xlsx):** Tabloların okunabilir kopyası. Geri yükleme için kullanılmaz.

Yedek dosyaları sunucuda tutulmaz; üretilir, indirilir, silinir. Her indirme log hareketlerine yazılır.

## Klasör yapısı

```
app/
├── Console/Commands/    stok:dogrula, cari:dogrula, yayin:paketle
├── Enums/               Birim, StokYonu, StokSebebi, FisDurumu, OdemeTipi ...
├── Exceptions/          YetersizStokException, FisStokYetersizException
├── Filament/            Panel: sayfalar, resource'lar, widget'lar, giriş ekranı
├── Http/Controllers/    Site, fiş PDF'i, müşteri ekstresi
├── Models/              Urun, Kategori, Musteri, Fis, FisKalemi, Tahsilat ...
├── Services/            İş mantığı
└── Support/             Sayı ve biçim yardımcıları
config/
├── firma.php            Firma bilgileri (.env'den okunur)
└── site.php             Tanıtım sitesi metinleri
database/migrations/     Tablo tanımları
resources/views/
├── site/                Tanıtım sitesi ve katalog
├── pdf/                 Termal fiş, A4 fiş, ekstre
└── errors/              Türkçe hata sayfaları
tests/Feature/           Pest testleri
```

## Kullanım ve lisans

Bu depo inceleme ve portfolyo amacıyla paylaşılmıştır. Açık kaynak lisansı içermez; kodun kopyalanması veya ticari kullanımı için izin alınmalıdır.

- "Patek Life Perde" adı ve logosu ilgili firmaya aittir.
- Slider fotoğrafları CC0 lisanslıdır; kaynakları [public/images/slider/KUNYE.txt](public/images/slider/KUNYE.txt) dosyasında yazılıdır.

## Geliştirici

**Erkam Taşdemir** · [github.com/erkamtasdemirr11](https://github.com/erkamtasdemirr11)

## English summary

A website and inventory management system built for a wholesale curtain business in Istanbul.

- **Public site:** company landing page and a product catalogue with category filter and search. Prices and stock levels are not shown publicly.
- **Admin panel (`/panel`):** product cards, stock in/out, live stock view, sales receipts, customer accounts (balances, payments, statements), history pages with CSV export, activity log and downloadable backups.
- **Stack:** PHP 8.3, Laravel 13, Filament 5, MySQL 8, Tailwind CSS 4, Pest.
- **Design highlights:** stock and customer balances are derived from immutable movement records and are only changed through service classes; every operation runs in a single database transaction with row locking; all money and quantity maths use `bcmath` instead of floats; receipts and payments are never deleted, only cancelled with a reversing entry; receipt numbers are sequential and gap-free.
- **Output:** receipts as PDF for 80 mm thermal printers and A4, customer statements as PDF.
- **Hosting:** runs on shared hosting without queue workers or websockets.

The interface, database naming and documentation are in Turkish.
