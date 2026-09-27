# Hostinger'e Yayın Rehberi

Bu rehber projeyi Hostinger paylaşımlı hostingde canlıya almak için izlenecek adımları anlatır.
Dosyalar hPanel'den ZIP olarak yüklenir; Git veya Composer gerekmez. Komutlar hPanel'in tarayıcı
içindeki **Terminal** ekranından çalıştırılır. `alanadiniz.com` yerine kendi alan adınızı yazın.

## 1. Gerekenler (hPanel'de bir kez)

- Hostinger Premium (veya üstü) web hosting ve alan adı.
- hPanel > Gelişmiş > PHP Yapılandırması: **PHP 8.3** seçili; `bcmath`, `gd`, `zip`, `mbstring`, `intl`, `pdo_mysql` uzantıları açık (çoğu varsayılan olarak açıktır).
- hPanel > Veritabanları > MySQL Veritabanları: yeni veritabanı ve kullanıcı oluşturun; **veritabanı adı, kullanıcı adı ve şifreyi** not alın.
- hPanel > Güvenlik > SSL: ücretsiz SSL kurulu ve **"HTTPS'yi zorla"** açık.
- hPanel > Gelişmiş > SSH Erişimi: açık (Terminal bunu kullanır).

## 2. Yerelde paketi hazırlama

```bash
npm run build
php artisan test
php artisan yayin:paketle
```

Komut `yayin-paketi/patek-yayin-TARIH.zip` dosyasını üretir. İçinde uygulama kodu, `vendor` ve derlenmiş
`public/build` vardır; `.env`, testler, loglar ve yüklenmiş görseller yoktur.

Slider görselleri (`public/images/slider`) şu an stok fotoğraf; paketlemeden önce kendi fotoğraflarınızı
aynı adlarla (WebP, 1920x1080) koyun. `config/site.php` içindeki metinler de yer tutucudur.

## 3. Sunucu klasör yapısı

Proje `public_html` dışında durur; yalnızca Laravel'in `public` klasörü web'den erişilir.

```
/home/uXXXXXXXX/domains/alanadiniz.com/
|-- patek/            <- proje (ZIP buraya açılır)
`-- public_html  ->  patek/public   (sembolik bağlantı)
```

## 4. İlk yayın

### 4a. ZIP'i yükleyin (hPanel > Dosyalar > Dosya Yöneticisi)

1. `domains/alanadiniz.com` klasörüne girin, `patek` adında yeni klasör oluşturun.
2. `patek` klasörüne girip ZIP dosyasını yükleyin (Upload). ZIP 256 MB'ı aşarsa yerelde
   `php artisan yayin:paketle --vendorsuz` ile paketleyin; o zaman 4b'de ilk komut olarak
   `composer install --no-dev --optimize-autoloader` çalıştırırsınız.
3. ZIP'e sağ tıklayıp **Extract** deyin; dosyalar `patek` içine açılır. ZIP'i silebilirsiniz.
4. `.env.production.example` dosyasını kopyalayıp adını `.env` yapın. Dosya Yöneticisi'nin
   düzenleyicisiyle açıp `APP_URL`, `DB_*`, `FIRMA_*` ve `SITE_*` alanlarını doldurun.

### 4b. Komutları çalıştırın (hPanel > Gelişmiş > Terminal)

```bash
cd ~/domains/alanadiniz.com/patek

php artisan key:generate --force
php artisan migrate --force
# Hostinger PHP'de symlink() kapalı; storage:link hata verir, bağlantı kabukla kurulur
ln -sfn ~/domains/alanadiniz.com/patek/storage/app/public public/storage
chmod -R 775 storage bootstrap/cache

# İlk yönetici hesabı: KULLANICI_ADI, E-POSTA ve SIFRE yerine kendi bilgilerinizi yazın (güçlü bir şifre seçin)
php artisan make:filament-user --name=KULLANICI_ADI --email=yonetici@alanadiniz.com --password=SIFRE

# Önbellekler
php artisan optimize
php artisan filament:optimize

# public_html'i projenin public klasörüne bağla
cd ~/domains/alanadiniz.com
rm -rf public_html
ln -s patek/public public_html
```

Terminal'deki `php` komutu 8.3 değilse (`php -v` ile bakın) komutları `php83` ile çalıştırın
veya hPanel'den CLI sürümünü değiştirin.

**Seeder çalıştırmayın.** `php artisan db:seed` yalnızca yerel geliştirme içindir; canlıda test kullanıcısı oluşturur.

## 5. Yayın sonrası kontrol

- `https://alanadiniz.com` açılıyor, `http://` adresi `https://` adresine yönleniyor.
- `https://alanadiniz.com/urunler`, `/robots.txt`, `/sitemap.xml` açılıyor.
- `https://alanadiniz.com/panel` giriş ekranı geliyor; giriş yapıp Ürün Kartları'na bir görsel yükleyin, katalogda göründüğünü doğrulayın.
- Olmayan bir adres (`/deneme`) Türkçe 404 sayfası gösteriyor.
- Google Search Console'a alan adını ekleyip `sitemap.xml` adresini gönderin.
- Panelde Sistem > Yedekleme'den bir ZIP indirip açıldığını doğrulayın. Haftada bir yedek almak dükkanın sorumluluğundadır.
- İki adımlı doğrulamayı profil sayfasından açın.

## 6. Sonraki yayınlar (güncelleme)

1. Yerelde: `npm run build`, `php artisan test`, `php artisan yayin:paketle`.
2. Terminal: `cd ~/domains/alanadiniz.com/patek && php artisan down`
3. Dosya Yöneticisi: yeni ZIP'i `patek` klasörüne yükleyip **Extract** deyin. Mevcut dosyaların üzerine yazar; `.env`, yüklenen görseller ve loglar korunur.
4. Terminal:

```bash
cd ~/domains/alanadiniz.com/patek
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan up
```

## 7. Git ile yayın (isteğe bağlı)

Kodu bir Git deposuna koyarsanız ZIP yerine şu yol kullanılır: sunucuda `git clone ... patek`,
`composer install --no-dev --optimize-autoloader`, ardından 4a-4 ve 4b'deki adımlar.
Güncellemede `git pull` + `composer install --no-dev` + bölüm 6'daki komutlar.
`public/build` Git'e dahildir (sunucuda Node yok), her yayından önce `npm run build` çalıştırıp commit edin.

## 8. Sık karşılaşılan sorunlar

| Belirti | Sebep ve çözüm |
|---|---|
| Sayfa 500 veriyor | `storage/logs/laravel-*.log` dosyasına bakın. Çoğu kez `.env` eksik ya da `APP_KEY` üretilmemiştir. `storage` ve `bootstrap/cache` yazılabilir olmalı: `chmod -R 775 storage bootstrap/cache` |
| Site stilsiz açılıyor | `public/build` pakete girmemiş. Yerelde `npm run build` çalıştırıp yeniden paketleyin. |
| Ürün görselleri görünmüyor | `php artisan storage:link` çalıştırılmamış. |
| Panelde 419 "Oturum süresi doldu" | `APP_URL` https ile başlamalı, `SESSION_SECURE_COOKIE=true` olmalı; sonra `php artisan optimize`. |
| Ayar değişikliği etki etmiyor | `.env` değiştikten sonra `php artisan optimize` (önbelleği yeniler). |
| http adresi https'e yönlenmiyor | hPanel > SSL > "HTTPS'yi zorla" açık mı? `public/.htaccess` de yönlendirir. |
| Dosya Yöneticisi ZIP'i açmıyor | ZIP çok büyükse `--vendorsuz` paketleyip Terminal'de `composer install --no-dev --optimize-autoloader` çalıştırın. |

Zamanlanmış görev şu an yok; ileride eklenirse hPanel > Gelişmiş > Cron İşleri'ne her dakika
`php /home/uXXXXXXXX/domains/alanadiniz.com/patek/artisan schedule:run >> /dev/null 2>&1` eklenir.
