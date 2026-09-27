<?php

it('tanıtım sitesi açılır, slider vardır ve navbarda Giriş butonu yoktur', function () {
    $yanit = $this->get('/')
        ->assertOk()
        ->assertSee(config('firma.ad'))
        ->assertSee('data-slider', escape: false)
        ->assertSee('Ürün gruplarımız');

    $slaytlar = config('site.slaytlar');
    $html = $yanit->getContent();

    preg_match('/<header.*?<\/header>/s', $html, $header);

    expect($header[0] ?? '')->not->toContain('Giriş')
        ->and(substr_count($html, 'class="hero-slide"') + substr_count($html, 'class="hero-slide is-active"'))->toBe(count($slaytlar))
        ->and($html)->toContain($slaytlar[0]['baslik'])
        ->and($html)->toContain('hero-arrow-next')
        ->and($html)->toContain('data-header');
});

it('tanıtım sitesi tüm ürün gruplarını ve iletişim bölümünü gösterir', function () {
    $yanit = $this->get('/')->assertOk();

    foreach (config('site.urun_gruplari') as $grup) {
        $yanit->assertSee($grup['ad']);
    }

    $yanit->assertSee('İletişim')->assertSee(config('site.calisma_saatleri'));
});
