# CLAUDE.md — Perde Toptancısı Web Sitesi ve Stok Takip Sistemi

Bu dosya projenin kurallarını ve yapısını tanımlar. Her oturumda önce bu dosyayı oku ve buradaki kurallara uy. Bir kural ile istenen iş çelişirse, kodu yazmadan önce bunu belirt.

## Proje özeti

Bir perde toptancısı için iki bölümlü tek bir Laravel projesi:

1. **Ön taraf:** Blade ile yapılmış tanıtım sitesi (`/`) ve herkese açık ürün kataloğu (`/urunler`). Katalog paneldeki **aktif** ürünleri kategori filtresi ve aramayla listeler; **fiyat ve stok gösterilmez**. Ana sayfadaki ürün şeridi görselli ürünleri öne alır, ürün yoksa `config/site.php` içindeki ürün gruplarını gösterir. Katalog kartlarında yalnızca kategori, ürün adı ve açıklama görünür; ürün kodu, renk ve birim gösterilmez. Navbar'da panele giden buton yoktur; panele footer'daki "Giriş" bağlantısından veya doğrudan `/panel` adresinden girilir. SEO: canonical, Open Graph ve JSON-LD etiketleri `site/layout.blade.php` ile `SiteController` içinde; `robots.txt` ve `sitemap.xml` statik dosya değil, rotadan üretilir. Türkçe hata sayfaları `resources/views/errors` altındadır.
2. **Panel (`/panel`):** Filament ile yapılmış, sadece giriş yapan kullanıcıların erişebildiği stok takip sistemi.

Panel modülleri: Ürün Kartları, Stok Giriş, Canlı Stok, Stok Çıkışı, Fiş Kayıt, Cari Hesap (müşteri bakiyesi, tahsilat, ekstre), Geçmiş Kayıtlar (stok, fiş ve cari geçmişi), Log Hareketleri.

Fişler resmi fatura DEĞİLDİR. İç kullanım belgesidir. GİB, e-fatura veya e-arşiv entegrasyonu yapılmayacak.

## Teknoloji

- Laravel (güncel kararlı sürüm), PHP 8.3+
- Filament (güncel kararlı sürüm), panel yolu: `/panel`
- MySQL (yerelde ve Hostinger'de)
- Pest (testler)
- Paketler:
  - `barryvdh/laravel-dompdf` → PDF fiş
  - `spatie/laravel-activitylog` → log hareketleri
  - `spatie/laravel-backup` → otomatik yedekleme
- Hosting: Hostinger paylaşımlı/cloud hosting (VPS değil). Kuyruk (queue) worker veya websocket gerektiren çözümler KULLANMA. Zamanlanmış işler sadece cron + `schedule:run` ile çalışır.

> Kurulan sürümler: Laravel `v13.33.0`, Filament `v5.8.4`, PHP `8.3.33`, MySQL `8.4.3`

## Dil ve bölge ayarları

- `APP_LOCALE=tr`, `APP_TIMEZONE=Europe/Istanbul`
- Arayüzdeki tüm metinler Türkçe.
- Para birimi TL. Sayılar Türkçe formatta gösterilir: `1.250,50 ₺`, `12,5 m`.
- Tarih formatı: `22.09.2026 14:30`

## İsimlendirme kuralları

- Tablo, sütun, model ve iş alanıyla ilgili isimler **Türkçe**, ASCII karakterlerle: `urunler`, `stok_hareketleri`, `fis_kalemleri`, `mevcut_stok`.
- Model isimleri tekil ve PascalCase: `Urun`, `Kategori`, `Musteri`, `Fis`, `FisKalemi`, `StokHareketi`.
- Laravel'in kendi yapıları İngilizce kalır: `users`, `created_at`, `deleted_at`, `timestamps`.
- Türkçe tablo isimleri Laravel'in çoğul kuralına uymadığı için her modelde `protected $table` açıkça belirtilir.
- Sabit değer kümeleri için PHP enum kullan: `App\Enums\StokYonu`, `App\Enums\StokSebebi`, `App\Enums\FisDurumu`, `App\Enums\Birim`, `App\Enums\CariTipi`, `App\Enums\CariKaynagi`, `App\Enums\OdemeYontemi`, `App\Enums\OdemeTipi`, `App\Enums\TahsilatDurumu`. Enum'lar Filament'te Türkçe etiketle görünsün (`HasLabel`).

## Kullanıcılar ve yetki

- Şimdilik tek rol var: **yönetici**. Giriş yapan her kullanıcı her işlemi yapabilir.
- Rol/izin paketi kurma. İleride eklenebilecek şekilde basit tut.
- Her kişinin kendi hesabı olur. Ortak hesap kullanılmaz.
- `aktif = false` olan kullanıcı giriş yapamaz.
- Giriş denemesi sınırı ve iki adımlı doğrulama açık olmalı.
- Stok hareketleri, fişler ve loglar her zaman işlemi yapan `user_id` ile kaydedilir.

## Veritabanı şeması

```
users              id, name, email, password, aktif (bool, varsayılan true), timestamps
                   + Filament iki adımlı doğrulama için gereken alanlar

kategoriler        id, ad, sira (int), timestamps

urunler            id, ad, kategori_id (FK),
                   renk (nullable),
                   birim (enum: metre/adet, varsayılan metre),
                   alis_fiyati (decimal 12,2, nullable),
                   satis_fiyati (decimal 12,2, nullable),
                   kritik_stok (decimal 12,2, varsayılan 0),
                   mevcut_stok (decimal 12,2, varsayılan 0),
                   gorsel (nullable), aciklama (text, nullable),
                   aktif (bool), timestamps, deleted_at

musteriler         id, firma_adi, yetkili (nullable), telefon (nullable),
                   adres (text, nullable), notlar (text, nullable),
                   acilis_bakiyesi (decimal 14,2, varsayılan 0),
                   bakiye (decimal 14,2, varsayılan 0),
                   timestamps, deleted_at

cari_hareketler    id, musteri_id (FK), tarih (datetime),
                   tip (enum: borc/alacak),
                   kaynak (enum: acilis/fis/fis_iptal/tahsilat/tahsilat_iptal/duzeltme),
                   tutar (decimal 12,2, her zaman pozitif),
                   bakiye_sonrasi (decimal 14,2),
                   fis_id (nullable FK), tahsilat_id (nullable FK),
                   aciklama (nullable), user_id (FK), created_at

tahsilatlar        id, makbuz_no (unique, örn. 2026-000001), musteri_id (FK),
                   fis_id (nullable FK; fişle birlikte kesilen peşin tahsilat),
                   tarih (datetime), tutar (decimal 12,2),
                   odeme_yontemi (enum: nakit/havale/cek/kredi_karti),
                   durum (enum: aktif/iptal), aciklama (nullable),
                   iptal_nedeni (nullable), iptal_eden_id (nullable FK users),
                   iptal_tarihi (nullable), user_id (FK), timestamps

makbuz_numaralari  yil (PK), son_no (int)

fisler             id, fis_no (unique, örn. 2026-000001), musteri_id (FK),
                   tarih (datetime), ara_toplam, iskonto, genel_toplam (decimal 12,2),
                   odeme_tipi (enum: acik_hesap/pesin, varsayılan acik_hesap),
                   durum (enum: aktif/iptal), aciklama (nullable),
                   iptal_nedeni (nullable), iptal_eden_id (nullable FK users),
                   iptal_tarihi (nullable), user_id (FK), timestamps

fis_kalemleri      id, fis_id (FK), urun_id (FK), miktar (decimal 12,2),
                   birim_fiyat (decimal 12,2), tutar (decimal 12,2), timestamps

fis_numaralari     yil (PK), son_no (int)

stok_hareketleri   id, urun_id (FK), yon (enum: giris/cikis),
                   sebep (enum: alis/satis/fire/numune/iade/sayim/fis_iptal),
                   miktar (decimal 12,2, her zaman pozitif),
                   fis_id (nullable FK), aciklama (nullable),
                   user_id (FK), created_at

activity_log       spatie/laravel-activitylog tablosu
```

Yabancı anahtarlarda `cascadeOnDelete` KULLANMA. Geçmiş kayıtlar hiçbir silme işleminden etkilenmemeli (`restrictOnDelete`).

## Stok kuralları (en kritik bölüm)

1. **`urunler.mevcut_stok` asla doğrudan güncellenmez.** Formlarda düzenlenemez, sadece okunur.
2. Stoğu değiştiren her işlem `App\Services\StokServisi` üzerinden yapılır:
   - `girisYap(Urun $urun, string $miktar, StokSebebi $sebep, ...)`
   - `cikisYap(Urun $urun, string $miktar, StokSebebi $sebep, ...)`
3. Servis her işlemde:
   - `DB::transaction()` içinde çalışır,
   - ürün satırını `lockForUpdate()` ile kilitler,
   - `stok_hareketleri` tablosuna kayıt ekler,
   - `mevcut_stok` alanını günceller.
4. Stok yetersizse `cikisYap` özel bir exception fırlatır (`YetersizStokException`) ve hiçbir şey kaydedilmez. Stok eksiye düşemez.
5. Miktar sıfır veya negatif olamaz.
6. Stok birimi: `birim = metre` ise miktar metredir, `adet` ise adettir. Giriş doğrudan miktar olarak yapılır; top adedi × top metresi girişi yoktur.
7. Stok hareketleri **asla silinmez veya düzenlenmez.** Hatalı bir hareket, ters yönlü yeni bir hareketle (sebep: `sayim`) düzeltilir.
8. `php artisan stok:dogrula` komutu tüm hareketlerden stoğu yeniden hesaplar ve `mevcut_stok` ile karşılaştırır. Fark varsa raporlar. `--duzelt` seçeneğiyle düzeltir.

## Para ve miktar hesapları

- Hesaplamalarda `float` KULLANMA. Modellerde `decimal:2` cast kullan, hesaplamaları `bcmath` fonksiyonlarıyla (`bcadd`, `bcmul`, `bcsub`, `bccomp`) yap.
- Tutar hesabı: `tutar = miktar × birim_fiyat`, 2 basamağa yuvarlanır.

## Fiş kuralları

1. Fiş kaydetme `App\Services\FisServisi` üzerinden yapılır. Fiş, kalemler, her kalem için `StokServisi::cikisYap(..., StokSebebi::Satis)` ve fişin `genel_toplam` tutarı kadar `CariServisi::borclandir(..., CariKaynagi::Fis)` **tek transaction** içinde çalışır. Herhangi bir kalemde stok yetersizse fiş hiç oluşmaz, cari bakiye değişmez ve kullanıcıya hangi üründe kaç birim eksik olduğu gösterilir. Ödeme tipi `pesin` ise aynı transaction içinde fişe bağlı bir tahsilat (`TahsilatServisi::kaydet`, `fis_id` dolu) kesilir ve borç anında kapanır; `acik_hesap` ise tutar müşterinin borcunda kalır.
2. Fiş numarası formatı: `YYYY-NNNNNN` (örn. `2026-000125`). Numara `fis_numaralari` tablosundan `lockForUpdate` ile alınır. Her yıl 1'den başlar. Numara atlamaz ve tekrar etmez.
3. Birim fiyat ürün kartındaki `satis_fiyati` ile dolu gelir ama fişte değiştirilebilir. Fiş kaydedildikten sonra ürün fiyatı değişse bile fişteki fiyat değişmez.
4. **Fişler silinmez ve kaydedildikten sonra düzenlenmez.** Sadece iptal edilir.
5. İptalde: iptal nedeni zorunlu, `durum = iptal`, iptal eden ve tarih kaydedilir, her kalem için `StokServisi::girisYap(..., StokSebebi::FisIptal)` ve fişin `genel_toplam` tutarı kadar `CariServisi::alacaklandir(..., CariKaynagi::FisIptal)` çalışır. Hepsi tek transaction içinde. İptal edilmiş fiş tekrar iptal edilemez. Fişe bağlı peşin tahsilat varsa o da aynı transaction içinde iptal edilir.
6. PDF çıktısı iki şablondur. **Varsayılan 80 mm termal rulodur** (`pdf/fis-termal.blade.php`; fiş yazıcısı Sunlux RP8020, baskı alanı 72 mm): "Fiş yazdır" butonu bunu açar. **PDF sayfası 80 mm değil 72 mm genişliğindedir**, yanlarda 2 mm pay vardır: yazıcı sayfayı kâğıdın kenarından değil baskı alanının başından basar ve kenar boşluğunu kendisi bırakır; sayfa 80 mm üretilirse içerik sağa kayar ve sağ kenarı kesilir (gerçek baskıda görüldü). Yazıcının Windows sürücüsü `POS-80C`'dir; yazdırırken kâğıt boyutu **80(72.1) x 3276 mm** (rulo) seçilmelidir. Sürücünün varsayılanı olan 80(72.1) x 297 mm seçili kalırsa 297 mm'den uzun fişlerin devamı basılmaz; bu bir kod hatası değil, yazıcı ayarıdır. Sayfa boyu içeriğe göre ayarlanır (`FisPdfController` fişi önce ölçer, sonra o boyda üretir), tek sayfa çıkar; termal yazıcı yalnızca siyah bastığı için renk ve gri ton kullanılmaz. Fişin en tepesinde logonun siyah-beyaz amblemi (`public/images/logo-termal.png`) bulunur; renkli logo koyu zeminli olduğu için termalde kullanılmaz. **A4 şablonu** (`pdf/fis.blade.php`) `?boyut=a4` ile, fiş görüntüleme sayfasındaki "A4 PDF" butonundan alınır. Her iki şablonda da aynı bilgiler bulunur. Fişte firma bilgileri, fiş no, tarih, müşteri, ödeme tipi, kalemler, toplamlar ve **cari hesap durumu** bulunur: önceki bakiye, bu fişin tutarı, varsa peşin tahsilat ve fiş sonrası bakiye (borç/alacak yönüyle). Bu özet `Fis::bakiyeOzeti()` ile `cari_hareketler.bakiye_sonrasi` alanından okunur; sonraki tahsilatlar fişin özetini değiştirmez. Altında şu ibare yer alır: "Bu belge mali değer taşımaz, bilgi amaçlıdır." İptal edilmiş fişin PDF'inde belirgin bir "İPTAL" damgası olur.
7. Fiş formunda **ödeme tipi** seçilir: `acik_hesap` (varsayılan; tutar borca yazılır, ay sonu veya vadesinde tahsilatla kapatılır) veya `pesin` (ödeme yöntemi zorunlu; fişle birlikte makbuz kesilir, `tahsilatlar.fis_id` fişi gösterir). Formda müşteri seçilince güncel bakiye ve fiş sonrası bakiye önizlemesi görünür. Peşin fiş iptal edilince bağlı tahsilat da aynı transaction içinde iptal edilir; bağlı tahsilat daha önce tek başına iptal edildiyse fiş açık hesaba dönmüş sayılır.

## Cari hesap kuralları

Cari tarafı stok tarafıyla aynı mantıkla çalışır: bir özet alan (`bakiye`) vardır ve bu alan hareketlerden türetilir, asla elle yazılmaz.

1. **`musteriler.bakiye` asla doğrudan güncellenmez.** Formlarda düzenlenemez, sadece okunur.
2. Bakiyeyi değiştiren her işlem `App\Services\CariServisi` üzerinden yapılır:
   - `borclandir(Musteri $musteri, string $tutar, CariKaynagi $kaynak, ...)`
   - `alacaklandir(Musteri $musteri, string $tutar, CariKaynagi $kaynak, ...)`
3. Servis her işlemde:
   - `DB::transaction()` içinde çalışır,
   - müşteri satırını `lockForUpdate()` ile kilitler,
   - `cari_hareketler` tablosuna kayıt ekler ve `bakiye_sonrasi` alanını doldurur,
   - `musteriler.bakiye` alanını günceller.
4. Bakiyenin işareti: **pozitif bakiye müşterinin bize borçlu olduğu anlamına gelir.** Borç kaydı bakiyeyi artırır, alacak kaydı azaltır.
5. Bakiye negatif olabilir; bu müşterinin fazla ödeme yaptığı anlamına gelir. Engellenmez, müşteri kartında farklı renkte gösterilir.
6. Tutar sıfır veya negatif olamaz. Yön her zaman `tip` alanıyla belirtilir.
7. Cari hareketler **asla silinmez veya düzenlenmez.** Hatalı bir kayıt, ters yönlü yeni bir hareketle (kaynak: `duzeltme`) düzeltilir. Düzeltme kaydında açıklama zorunludur.
8. Açılış bakiyesi: müşteri oluşturulurken girilen `acilis_bakiyesi` için kaynağı `acilis` olan tek bir cari hareket üretilir. Bu alan müşteri kaydedildikten sonra formdan veya doğrudan değiştirilemez. Yanlış girildiyse müşteri kartındaki **"Açılış bakiyesini düzelt"** işlemi (`CariServisi::acilisBakiyesiDuzelt`) kullanılır: doğru tutar ve zorunlu açıklama girilir, kayıtlı tutarla arasındaki fark kaynağı `duzeltme` olan tek bir cari hareket olarak yazılır, `acilis_bakiyesi` doğru değere çekilir ve ayrı bir log kaydı üretilir. Hepsi tek transaction içinde. Açılış hareketi silinmez ve değişmez; silinmiş müşteride bu işlem yapılamaz.
9. `php artisan cari:dogrula` komutu tüm hareketlerden bakiyeyi yeniden hesaplar ve `musteriler.bakiye` ile karşılaştırır. Fark varsa raporlar. `--duzelt` seçeneğiyle düzeltir.
10. Müşteri ekstresi tarih aralığına göre üretilir: devreden bakiye, aralıktaki hareketler ve kapanış bakiyesi gösterilir. PDF ve CSV çıktısı alınabilir.

## Tahsilat kuralları

1. Tahsilat kaydetme `App\Services\TahsilatServisi` üzerinden yapılır. Tahsilat kaydı ve `CariServisi::alacaklandir(..., CariKaynagi::Tahsilat)` **tek transaction** içinde çalışır. Fişle birlikte kesilen peşin tahsilatın `fis_id` alanı doludur ve Tahsilatlar listesinde fiş numarası görünür.
2. Makbuz numarası formatı: `YYYY-NNNNNN`. Numara `makbuz_numaralari` tablosundan `lockForUpdate` ile alınır. Her yıl 1'den başlar, atlamaz ve tekrar etmez. Yılın ilk kaydında satır henüz bulunmadığı için önce `insertOrIgnore` ile oluşturulur, ardından kilitlenip okunur. Aynı kural `fis_numaralari` için de geçerlidir.
3. **Tahsilatlar silinmez ve kaydedildikten sonra düzenlenmez.** Sadece iptal edilir.
4. İptalde: iptal nedeni zorunlu, `durum = iptal`, iptal eden ve tarih kaydedilir, `CariServisi::borclandir(..., CariKaynagi::TahsilatIptal)` çalışır. Hepsi tek transaction içinde. İptal edilmiş tahsilat tekrar iptal edilemez.
5. *İleride eklenecek:* Makbuz PDF çıktısı A4 şablonudur. Firma bilgileri, makbuz no, tarih, müşteri, tutar, ödeme yöntemi ve tahsilat sonrası bakiye bulunur. Altında şu ibare yer alır: "Bu belge mali değer taşımaz, bilgi amaçlıdır." İptal edilmiş makbuzun PDF'inde belirgin bir "İPTAL" damgası olur.
6. **Çek ve senet vade takibi bu projenin kapsamı dışındadır.** `odeme_yontemi = cek` yalnızca ödemenin nasıl alındığını belirtir; çekin vadesi, portföy durumu veya akıbeti izlenmez. İleride ayrı bir modül olarak eklenebilir.

## Silme kuralları

- Ürünler ve müşteriler soft delete ile pasife alınır, kalıcı silinmez. Ürün Kartları'nda ve Müşteriler'de bu işlem "Sil" olarak adlandırılır; silinen kayıtlar aynı sayfadaki "Son silinenler" sekmesinde listelenir ve "Geri al" ile geri alınabilir.
- Pasif ürünler seçim listelerinde görünmez ama geçmiş kayıtlarda adıyla görünmeye devam eder.
- Bakiyesi sıfırdan farklı olan müşteri pasife alınamaz. Kullanıcıya güncel bakiyesi gösterilir ve önce hesabın kapatılması istenir.
- Stok hareketleri, fişler, fiş kalemleri, cari hareketler, tahsilatlar ve loglar hiçbir şekilde silinemez.

## Loglama

- `spatie/laravel-activitylog` kullanılır.
- Loglanan modeller: `User`, `Kategori`, `Urun`, `Musteri`, `Fis`, `Tahsilat`. Sadece değişen alanlar loglanır.
- Giriş ve çıkış (login/logout) olayları da loglanır.
- Fiş iptali, tahsilat iptali ve cari düzeltme kaydı ayrı ve açıklamalı birer log kaydı üretir.
- Log açıklamaları Türkçe olur.

## Panel menü yapısı

| Grup | Sayfa | Tür |
|---|---|---|
| Ürünler | Ürün Kartları | Resource |
| Ürünler | Kategoriler | Resource |
| Stok | Stok Giriş | Özel sayfa |
| Stok | Canlı Stok | Özel sayfa (30 sn'de bir yenilenir; paylaşımlı hostingi yormamak için 10 sn yerine 30 sn) |
| Stok | Stok Çıkışı | Özel sayfa (sebep zorunlu, `satis` seçilemez) |
| Satış | Fiş Kayıt | Resource (oluşturma + görüntüleme + iptal) |
| Cari | Müşteriler | Resource (bakiye salt okunur; kartta "Açılış bakiyesini düzelt" butonu) |
| Cari | Tahsilatlar | Resource (oluşturma + görüntüleme + iptal) |
| Cari | Müşteri Ekstresi | Müşteri kartındaki "Ekstre" butonu (tarih aralıklı PDF; ayrı sayfa yok) |
| Geçmiş | Stok Geçmişi | Salt okunur liste |
| Geçmiş | Fiş Geçmişi | Salt okunur liste |
| Geçmiş | Cari Hareketler | Salt okunur liste |
| Sistem | Log Hareketleri | Salt okunur liste |
| Sistem | Kullanıcılar | Resource |

- Stok Giriş ve Stok Çıkışı sayfalarında Repeater ile tek seferde birden fazla ürün işlenebilir.
- Ürün seçimleri aranabilir olur ve ürünler `kategori → ad` sırasıyla listelenir.
- Canlı Stok'ta kritik stok altındaki ürünler kırmızı gösterilir.
- Panel SPA modunda çalışır (`->spa()`, Livewire `wire:navigate`); sayfa geçişleri tam yenileme yapmaz. PDF ve ekstre gibi dosya üreten rotalar `spaUrlExceptions` ile hariç tutulur, yeni dosya rotası eklenirse oraya da yazılır.
- Müşteri seçimleri aranabilir olur; seçim listesinde ve müşteri kartında güncel bakiye görünür. Borçlu bakiye kırmızı, alacaklı (negatif) bakiye yeşil gösterilir.
- Müşteri kartında cari hareketler ve tahsilatlar ilişkili liste olarak görünür; buradan doğrudan ekstre alınabilir.
- Geçmiş ve log sayfalarında düzenleme, silme veya oluşturma butonu olmaz. Tarih aralığı filtresi ve **CSV indirme** bulunur (Excel'de doğrudan açılır; Filament'in Excel dışa aktarımı kuyruk gerektirdiği için paylaşımlı hostingde kullanılmaz).

## Kod yapısı

- İş mantığı `app/Services` içinde olur. Filament sayfaları ve resource'lar servisleri çağırır, iş mantığı içermez.
- Enum'lar `app/Enums`, özel exception'lar `app/Exceptions` içinde olur.
- Form doğrulamaları hem Filament formunda hem serviste yapılır.
- Kullanıcıya gösterilen hata mesajları Türkçe ve anlaşılır olur (örn. "Stok yetersiz: Keten Beyaz için mevcut 12,50 m, istenen 20,00 m").

## Testler

- Pest kullanılır. Testler yerelde ayrı bir test veritabanında çalışır.
- Aşağıdakiler için test zorunludur:
  - Stok girişi stoğu artırır ve hareket kaydı oluşturur.
  - Stok çıkışı stoğu azaltır; yetersiz stokta exception fırlatır ve hiçbir şey kaydetmez.
  - Ondalıklı miktarlarda (örn. 0,1 + 0,2) hesap hatası oluşmaz.
  - Fiş kaydı: stok düşer, toplamlar doğrudur, numara sıralıdır.
  - Fiş çıktısı varsayılan olarak termal rulonun baskı alanı genişliğinde (72 mm) ve tek sayfadır; `?boyut=a4` ile A4 alınır.
  - Bir kalemde stok yetersizse fiş ve diğer kalemlerin stok düşümleri geri alınır.
  - Fiş iptali stoğu geri ekler; iptal edilmiş fiş tekrar iptal edilemez.
  - `stok:dogrula` komutu tutarsızlığı yakalar.
  - Fiş kesilince müşteri bakiyesi fişin genel toplamı kadar artar.
  - Peşin fiş fişle aynı transaction içinde tahsilat keser ve bakiye değişmez; iptalinde bağlı tahsilat da iptal olur.
  - Fiş bakiye özeti fiş anındaki önceki/sonraki bakiyeyi verir; alacaklı müşteride fiş tutarı alacaktan düşer.
  - Fiş iptal edilince bakiye kesim öncesi haline döner.
  - Tahsilat bakiyeyi azaltır; tahsilat iptali bakiyeyi geri ekler.
  - İptal edilmiş tahsilat tekrar iptal edilemez.
  - Açılış bakiyesi girilen müşteri için tek bir cari hareket oluşur.
  - Açılış bakiyesi düzeltmesi farkı düzeltme hareketi olarak yazar, açılış hareketine dokunmaz; aynı tutar ve boş açıklama reddedilir.
  - Makbuz numarası sıralıdır, atlamaz ve tekrar etmez.
  - Ondalıklı tutarlarda (örn. 0,1 + 0,2) bakiye kayması oluşmaz.
  - Bir fişte stok yetersizse cari bakiye hiç değişmez.
  - `cari:dogrula` komutu tutarsızlığı yakalar.
- Stok, fiş veya cari koduna dokunan her değişiklikten sonra `php artisan test` çalıştır.

## Güvenlik

- Ham SQL'e kullanıcı verisi koyma. Eloquent veya query builder kullan.
- Görsel yüklemede dosya türü ve boyut sınırı uygula (jpg, png, webp; en fazla 2 MB).
- Canlıda: `APP_DEBUG=false`, `APP_ENV=production`, HTTPS zorunlu.
- `.env` dosyası asla Git'e eklenmez. `.env.example` güncel tutulur.

## Yedekleme

- Yedekler harici bir servise gönderilmez; yönetici **Sistem → Yedekleme** sayfasından kendi bilgisayarına indirir. (Kullanıcı kararı: otomatik/gece yedeği ve e-posta istenmedi.)
- **Tam yedek (ZIP):** `App\Services\YedekServisi` tüm tabloların SQL dökümünü (mysqldump gerektirmeden, PHP ile) ve ürün görsellerini tek ZIP'e koyar; içinde Türkçe geri yükleme talimatı (`BENIOKU.txt`) bulunur. `.env` gibi gizli dosyalar ZIP'e girmez. Geçici tablolar (`sessions`, `cache`, `jobs` vb.) dökümde yer almaz.
- **Excel yedeği (.xlsx):** Ürünler, kategoriler, stok hareketleri, müşteriler, fişler, fiş kalemleri, cari hareketler, tahsilatlar ve kullanıcılar ayrı sayfalarda. Okunabilir kopya içindir; **geri yükleme için kullanılamaz**, geri yükleme yalnızca ZIP içindeki SQL ile yapılır.
- Her indirme log hareketlerine yazılır. Yedek dosyaları sunucuda tutulmaz; üretilir, indirilir, silinir.
- Yöneticiye hatırlatma: düzenli aralıklarla (en az haftada bir) ZIP yedeği indirip güvenli bir yerde saklamak kullanıcının sorumluluğundadır.

## Hostinger'e yayın notları

- Proje dosyaları `public_html` dışında durur. Sadece Laravel'in `public` klasörünün içeriği web'den erişilebilir olmalı.
- Yayın adımları (SSH ile): `git pull` → `composer install --no-dev --optimize-autoloader` → `php artisan migrate --force` → `php artisan optimize` → `php artisan filament:optimize`
- Frontend dosyaları (`npm run build`) yerelde derlenir; `public/build` Git'e dahildir (sunucuda Node yok).
- Adım adım kurulum, `.env` ayarları ve sorun giderme: `YAYIN.md`. Canlı ayar örneği: `.env.production.example`.
- Yükleme Git ve Composer olmadan yapılır: `php artisan yayin:paketle` sunucuya yüklenecek ZIP paketini (kod + vendor + public/build; .env, testler ve loglar hariç) `yayin-paketi/` altına üretir. Paket hPanel Dosya Yöneticisi'nden açılır, artisan komutları hPanel Terminal'inden çalıştırılır. Yüklemeyi kullanıcı kendisi yapar.
- `public/.htaccess` HTTPS yönlendirmesi, sıkıştırma, tarayıcı önbelleği ve güvenlik başlıklarını içerir. Uygulama `trustProxies('*')` ile çalışır ve canlıda bağlantıları https üretir.

## Sık kullanılan komutlar

```bash
php artisan serve                 # yerel sunucu
php artisan test                  # testler
php artisan migrate:fresh --seed  # veritabanını sıfırla (SADECE YERELDE)
php artisan stok:dogrula          # stok tutarlılık kontrolü
php artisan cari:dogrula          # cari bakiye tutarlılık kontrolü
php artisan yayin:paketle         # sunucuya yüklenecek ZIP paketi
npm run dev / npm run build       # frontend
```

## Geliştirme aşamaları

Her aşama bitince testler çalıştırılır ve commit atılır. Tamamlanan aşamayı işaretle.

- [x] 1. Kurulum: Laravel + Filament, Türkçe ayarlar, yönetici hesabı (Hostinger deneme yayını hosting planı alınınca Aşama 11'de yapılacak)
- [x] 2. Kullanıcılar: aktif/pasif, giriş sınırı, iki adımlı doğrulama (isteğe bağlı, profil sayfasından açılır), activitylog kurulumu, Kullanıcılar sayfası
- [x] 3. Kategoriler ve ürün kartları
- [x] 4. Stok altyapısı: `stok_hareketleri`, enum'lar, `StokServisi`, Pest kurulumu, testler, `stok:dogrula` komutu (`stok_hareketleri.fis_id` FK kısıtı fişler tablosuyla birlikte Aşama 7'de eklenecek)
- [x] 5. Stok Giriş ve Stok Çıkışı sayfaları
- [x] 6. Canlı Stok sayfası ve dashboard widget'ları (stok özeti + kritik stok listesi)
- [x] 7. Müşteriler, `FisServisi`, fiş kesme, fiş numarası, PDF, fiş iptali, testler (fiş–cari bağlantısı `CariServisi` ile birlikte Aşama 8'de eklenecek)
- [x] 8. Cari hesap: `CariServisi`, açılış bakiyesi, fiş–cari entegrasyonu, `TahsilatServisi`, müşteri ekstresi PDF, `cari:dogrula`, testler (makbuz PDF ve fişte peşin tahsilat alanı sonraya bırakıldı)
- [x] 9. Stok Geçmişi, Fiş Geçmişi, Cari Hareketler, Log Hareketleri sayfaları (tarih aralığı filtresi + CSV indirme)
- [x] 10. Statik tanıtım sitesi (Blade + Tailwind; metinler `config/site.php`, firma bilgileri `.env`)
- [x] 11. Yedekleme, yayına hazırlık (SEO, hız, hata sayfaları, .htaccess, `.env.production.example`, `YAYIN.md`) ve canlıya alma: 25 Eylül 2026'da Hostinger Premium üzerinde https://pateklifeperde.com adresinde yayında. Sonraki yayınlar için `php artisan yayin:paketle` + `YAYIN.md` bölüm 6.

## Çalışma şekli

- Bir aşamaya başlamadan önce kısa bir plan çıkar ve onay bekle.
- Bu dosyadaki şemada değişiklik gerekiyorsa önce öner, onaydan sonra hem kodu hem bu dosyayı güncelle.
- Emin olmadığın bir iş kuralında tahmin yürütme, sor.
