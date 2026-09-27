<?php

/*
 * Tanıtım sitesinin metinleri. Firma adı, adres, telefon ve e-posta
 * config/firma.php'den (yani .env'den) gelir; buradakiler sadece site içeriğidir.
 * Metinler yer tutucudur — kendi ifadelerinizle değiştirin.
 */
return [
    'ust_baslik' => "1989'dan beri toptan perde tedarikçisi",
    'slogan' => 'Toptan perde, tül ve fon kumaşında güvenilir tedarikçiniz',
    'aciklama' => 'Perdeciler, dekorasyon firmaları ve mağazalar için geniş ürün yelpazesi, uygun toptan fiyat ve hızlı teslimat.',

    // 90 ile başlayan, boşluksuz numara: 905321234567. Boşsa WhatsApp butonu görünmez.
    'whatsapp' => env('SITE_WHATSAPP', ''),
    'instagram' => env('SITE_INSTAGRAM', ''),
    'calisma_saatleri' => 'Pazartesi – Cumartesi 09:00 – 19:00',
    // Google Haritalar > Paylaş > Harita yerleştir > src adresi. Boşsa harita görünmez.
    'harita_embed' => env('SITE_HARITA_EMBED', ''),
    // Google Search Console doğrulama kodu (HTML etiketi yöntemi). Boşsa etiket basılmaz.
    'google_dogrulama' => env('GOOGLE_SITE_VERIFICATION', ''),


    /*
     * Ana sayfa slider'ı. Görseller public/images/slider/ altında (JPG/WebP, 1920x1080 önerilir).
     * 'baglanti' # ile başlarsa sayfa içi bölüme gider. Slayt eklemek için listeye yeni bir öğe ekleyin.
     */
    'slaytlar' => [
        [
            'gorsel' => 'images/slider/slayt-1.webp',
            'ust_baslik' => "1989'dan beri",
            'baslik' => 'Toptan perde, tül ve fon kumaşında güvenilir tedarikçiniz',
            'aciklama' => 'Perdeciler, dekorasyon firmaları ve mağazalar için geniş ürün yelpazesi, uygun toptan fiyat ve hızlı teslimat.',
            'buton' => 'Ürün gruplarımız',
            'baglanti' => '#urunler',
        ],
        [
            'gorsel' => 'images/slider/slayt-2.webp',
            'ust_baslik' => 'Tül · Fon · Stor · Aksesuar',
            'baslik' => 'Yüzlerce desen ve renk, tek çatı altında',
            'aciklama' => 'Ana ürün gruplarında sürekli stok; siparişiniz yarım kalmaz.',
            'buton' => 'Neden biz',
            'baglanti' => '#neden-biz',
        ],
        [
            'gorsel' => 'images/slider/slayt-3.webp',
            'ust_baslik' => 'Numune',
            'baslik' => 'Işığı ve dokuyu görün, sonra karar verin',
            'aciklama' => 'Numune ve toptan fiyat listesi için bizimle iletişime geçin.',
            'buton' => 'Bize ulaşın',
            'baglanti' => '#iletisim',
        ],
    ],

    'urun_gruplari' => [
        ['ad' => 'Tül', 'aciklama' => 'Düz, desenli ve keten görünümlü tüller; 280–300 cm en seçenekleri.'],
        ['ad' => 'Fon Perde', 'aciklama' => 'Kadife, keten, jakar ve karartma (blackout) fon kumaşları.'],
        ['ad' => 'Stor ve Zebra', 'aciklama' => 'Ev ve ofis için stor, zebra ve karartma mekanizmalı sistemler.'],
        ['ad' => 'Jaluzi', 'aciklama' => 'Ahşap, alüminyum ve PVC jaluzi seçenekleri.'],
        ['ad' => 'Aksesuar', 'aciklama' => 'Korniş, ray, rustik, braçol, şerit ve dikiş malzemeleri.'],
        ['ad' => 'Hazır Perde', 'aciklama' => 'Standart ölçülerde dikilmiş, hemen teslim hazır perdeler.'],
    ],

    'neden_biz' => [
        ['baslik' => 'Toptan fiyat', 'aciklama' => 'Top ve koli bazlı alımlarda kademeli iskonto.'],
        ['baslik' => 'Sürekli stok', 'aciklama' => 'Ana ürün gruplarında düzenli stok; sipariş yarım kalmaz.'],
        ['baslik' => 'Hızlı sevkiyat', 'aciklama' => 'Siparişler aynı gün hazırlanır, kargo veya ambarla yola çıkar.'],
        ['baslik' => 'Numune', 'aciklama' => 'Numune için bizimle iletişime geçin.'],
    ],

    'hakkimizda' => [
        'Patek Life Perde, perde ve tül kumaşlarında toptan tedarik yapan bir aile işletmesidir. Perdecilere, dekorasyon firmalarına ve mağazalara hizmet veriyoruz.',
        'Amacımız basit: doğru ürünü, doğru fiyata, zamanında teslim etmek. Ürün gamımızı sürekli yeniliyor, müşterilerimizin taleplerine göre stok planlıyoruz.',
    ],
];
