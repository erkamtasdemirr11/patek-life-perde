// Tanıtım sitesi: üst menü davranışı ve hero slider. Ek kütüphane yok.

const azHareket = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* Üst menü: kaydırınca dolu zemin, mobilde aç/kapa */
const header = document.querySelector('[data-header]');

if (header) {
    const hepDolu = header.hasAttribute('data-solid'); // hero'suz sayfalarda (katalog) baştan dolu
    const guncelle = () => header.classList.toggle('is-solid', hepDolu || window.scrollY > 40);
    guncelle();
    window.addEventListener('scroll', guncelle, { passive: true });

    const dugme = header.querySelector('[data-menu-toggle]');
    dugme?.addEventListener('click', () => {
        const acik = header.classList.toggle('is-open');
        dugme.setAttribute('aria-expanded', String(acik));
    });

    // Menüden bir bağlantıya tıklanınca kapat
    header.querySelectorAll('[data-menu] a').forEach((a) => a.addEventListener('click', () => {
        header.classList.remove('is-open');
        dugme?.setAttribute('aria-expanded', 'false');
    }));
}

/* Hero slider */
class Slider {
    constructor(kok) {
        this.kok = kok;
        this.slaytlar = [...kok.querySelectorAll('.hero-slide')];
        this.aktif = Math.max(0, this.slaytlar.findIndex((s) => s.classList.contains('is-active')));
        this.sure = Number(kok.dataset.autoplay || 6000);
        this.zamanlayici = null;

        if (this.slaytlar.length < 2) return;

        // Arka planlar: ilk slayt satır içi stilde gelir, diğerleri burada yüklenir.
        this.slaytlar.forEach((s) => {
            if (s.dataset.bg && !s.style.backgroundImage) s.style.backgroundImage = `url('${s.dataset.bg}')`;
        });

        this.noktalar = this.noktalariKur();
        kok.querySelector('[data-prev]')?.addEventListener('click', () => this.git(this.aktif - 1, true));
        kok.querySelector('[data-next]')?.addEventListener('click', () => this.git(this.aktif + 1, true));

        kok.addEventListener('mouseenter', () => this.durdur());
        kok.addEventListener('mouseleave', () => this.baslat());
        kok.addEventListener('focusin', () => this.durdur());
        kok.addEventListener('focusout', () => this.baslat());
        kok.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.git(this.aktif - 1, true);
            if (e.key === 'ArrowRight') this.git(this.aktif + 1, true);
        });

        // Dokunmatik kaydırma
        let baslangicX = null;
        kok.addEventListener('pointerdown', (e) => { baslangicX = e.clientX; }, { passive: true });
        kok.addEventListener('pointerup', (e) => {
            if (baslangicX === null) return;
            const fark = e.clientX - baslangicX;
            baslangicX = null;
            if (Math.abs(fark) > 40) this.git(this.aktif + (fark < 0 ? 1 : -1), true);
        });

        document.addEventListener('visibilitychange', () => (document.hidden ? this.durdur() : this.baslat()));
        this.baslat();
    }

    noktalariKur() {
        const kap = this.kok.querySelector('[data-dots]');
        if (!kap) return [];

        return this.slaytlar.map((_, i) => {
            const nokta = document.createElement('button');
            nokta.type = 'button';
            nokta.className = 'hero-dot' + (i === this.aktif ? ' is-active' : '');
            nokta.setAttribute('aria-label', `${i + 1}. slayt`);
            nokta.addEventListener('click', () => this.git(i, true));
            kap.appendChild(nokta);
            return nokta;
        });
    }

    git(hedef, elle = false) {
        const n = this.slaytlar.length;
        const yeni = ((hedef % n) + n) % n;
        if (yeni === this.aktif) return;

        this.slaytlar[this.aktif].classList.remove('is-active');
        this.noktalar[this.aktif]?.classList.remove('is-active');
        this.aktif = yeni;
        this.slaytlar[yeni].classList.add('is-active');
        this.noktalar[yeni]?.classList.add('is-active');

        if (elle) this.baslat(); // sayacı sıfırla
    }

    baslat() {
        this.durdur();
        if (azHareket || this.sure <= 0) return;
        this.zamanlayici = window.setInterval(() => this.git(this.aktif + 1), this.sure);
    }

    durdur() {
        if (this.zamanlayici) window.clearInterval(this.zamanlayici);
        this.zamanlayici = null;
    }
}

document.querySelectorAll('[data-slider]').forEach((el) => new Slider(el));
