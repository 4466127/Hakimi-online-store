/*
 | جاوااسکریپت صفحه‌های مشتری. Alpine.js (سبک)؛ Leaflet فقط برای نقشه‌ی تسویه و فقط وقتی باز شود (فایل جدا).
 | صفحه‌ها بی جاوااسکریپت هم کار می‌کنند؛ Alpine فقط کار را راحت‌تر می‌کند
 | (انتخاب نوع جنس، گالری، فیلتر در موبایل؛ سبد در مرحله‌ی ۴).
 */
import Alpine from 'alpinejs';
import locationPicker from './location-picker.js';

window.Alpine = Alpine;

// انتخاب موقعیت تحویل روی نقشه (Leaflet فقط هنگام باز کردن نقشه بار می‌شود)
Alpine.data('locationPicker', locationPicker);

/*
 | انتخاب سایز/رنگ در صفحه‌ی جنس. هیچ محاسبه‌ی پولی اینجا نیست: قیمت هر نوع از سرور آماده (فرمت‌شده) می‌آید.
 |
 | cfg.attrs     شناسه‌ی مشخصه‌ها به ترتیب (سایز، رنگ)
 | cfg.groups    [{id, name, options: [{id, value}]}] برای نشان دادن گزینه‌ی انتخاب‌شده
 | cfg.variants  [{id, label, options: {attrId: optionId}, price, compare, badge, available, low, leftText, sku}]
 | cfg.selected  گزینه‌های نوع پیش‌فرض
 */
Alpine.data('productPicker', (cfg) => ({
    attrs: cfg.attrs,
    variants: cfg.variants,
    selected: { ...cfg.selected },

    /** نوعی که با انتخاب‌های فعلی می‌خورد (جنس بی سایز و رنگ: تنها نوع). */
    get current() {
        return this.variants.find((v) => this.attrs.every((a) => v.options[a] === this.selected[a])) ?? null;
    },

    /** گزینه‌ای انتخاب شد؛ اگر این ترکیب نیست، نزدیک‌ترین نوعی که این گزینه را دارد (ترجیحاً موجود). */
    pick(attr, option) {
        const next = { ...this.selected, [attr]: option };
        let match = this.variants.find((v) => this.attrs.every((a) => v.options[a] === next[a]));
        if (!match) {
            const withOption = this.variants.filter((v) => v.options[attr] === option);
            match = withOption.find((v) => v.available) ?? withOption[0];
        }
        if (match) {
            this.selected = { ...match.options };
        }
    },

    /** ok: موجود | out: ناموجود | other: با انتخاب‌های دیگر ترکیب می‌شود */
    optionState(attr, option) {
        const probe = { ...this.selected, [attr]: option };
        const exact = this.variants.find((v) => this.attrs.every((a) => v.options[a] === probe[a]));
        if (exact) {
            return exact.available ? 'ok' : 'out';
        }
        return this.variants.some((v) => v.options[attr] === option && v.available) ? 'other' : 'out';
    },

    optionClass(attr, option) {
        if (this.selected[attr] === option) {
            return 'border-brand-700 bg-brand-50 font-bold text-brand-900 ring-2 ring-brand-100';
        }
        return this.optionState(attr, option) === 'out'
            ? 'border-gray-200 bg-gray-50 text-gray-400 line-through'
            : 'border-gray-300 bg-white text-gray-800 hover:border-brand-600';
    },

    selectedLabel(attr) {
        const group = cfg.groups.find((g) => g.id === attr);
        return group?.options.find((o) => o.id === this.selected[attr])?.value ?? '';
    },

    /** لینک واتساپ با نام جنس، نوع انتخاب‌شده، قیمت و آدرس صفحه. */
    whatsappHref() {
        if (!cfg.whatsapp) {
            return '#';
        }
        const v = this.current;
        const lines = [cfg.message];
        if (v) {
            lines.push((v.label ? v.label + ' — ' : '') + v.price);
        }
        lines.push(cfg.url);
        return cfg.whatsapp + '?text=' + encodeURIComponent(lines.join('\n'));
    },
}));

/** عدد لاتین ← دری (۰-۹). */
const faDigits = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** «حرکت کمتر» در تنظیم دستگاه. */
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 | کارت «کد تخفیف در سایت» (۵ قوس ۱۴۰۵): کاپی کد و شمارش معکوس.
 | cfg.remaining ثانیه‌های باقی‌مانده از سرور (یا null = بی تاریخ پایان). ساعت موبایل مشتری استفاده نمی‌شود؛
 | فقط فاصله‌ی زمانی از باز شدن صفحه. با «حرکت کمتر» هر دقیقه یک بار به‌روز می‌شود و ثانیه دیده نمی‌شود.
 */
Alpine.data('sitePromo', (cfg) => ({
    code: cfg.code,
    left: cfg.remaining,
    copied: false,
    reduced: prefersReducedMotion(),
    timer: null,

    init() {
        if (this.left === null) {
            return;
        }
        const startedAt = performance.now();
        const total = this.left;
        const tick = () => { this.left = Math.max(0, Math.round(total - (performance.now() - startedAt) / 1000)); };
        this.timer = setInterval(() => {
            if (!document.hidden) { tick(); }
            if (this.left === 0) { clearInterval(this.timer); }
        }, this.reduced ? 60000 : 1000);
        document.addEventListener('visibilitychange', tick);
    },

    destroy() { clearInterval(this.timer); },

    parts() {
        const t = Math.max(0, this.left ?? 0);
        return { d: Math.floor(t / 86400), h: Math.floor((t % 86400) / 3600), m: Math.floor((t % 3600) / 60), s: t % 60 };
    },
    pad(n) { return String(n).padStart(2, '0'); },
    fa(n) { return faDigits(n); },
    label() {
        const p = this.parts();
        return faDigits(`${p.d}:${this.pad(p.h)}:${this.pad(p.m)}`);
    },

    /** کاپی؛ روی http (نه https) navigator.clipboard نیست، پس راه قدیمی هم امتحان می‌شود. */
    async copy() {
        let ok = false;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(this.code);
                ok = true;
            }
        } catch (e) { ok = false; }
        if (!ok) {
            const input = document.createElement('input');
            input.value = this.code;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            input.remove();
        }
        if (!ok) {
            // آخرین راه: متن کد انتخاب می‌شود تا مشتری خودش کاپی کند
            const range = document.createRange();
            range.selectNodeContents(this.$refs.code);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
        this.copied = ok;
        if (ok) { setTimeout(() => { this.copied = false; }, 2500); }
    },
}));

/*
 | ویدیوی صفحه‌ی اصلی (۵ قوس ۱۴۰۵): بی صدا، فقط وقتی دست‌کم نیمش دیده می‌شود پخش می‌شود و بیرون از دید
 | می‌ایستد. با «صرفه‌جویی داده»، انترنت 2G یا «حرکت کمتر» خودکار پخش نمی‌شود: فقط عکس با دکمه‌ی پخش.
 | فایل ویدیو تا وقت پخش دانلود نمی‌شود (preload="none").
 */
Alpine.data('homeVideo', () => ({
    playing: false,
    muted: true,
    manual: false,
    observer: null,

    init() {
        const video = this.$refs.video;
        video.muted = true;
        const conn = navigator.connection || {};
        const slow = conn.saveData || ['slow-2g', '2g'].includes(conn.effectiveType);
        this.manual = slow || prefersReducedMotion();
        video.addEventListener('play', () => { this.playing = true; });
        video.addEventListener('pause', () => { this.playing = false; });
        if (this.manual || !('IntersectionObserver' in window)) {
            return;
        }
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.5 && !document.hidden) {
                    video.play().catch(() => {});
                } else {
                    video.pause();
                }
            });
        }, { threshold: [0, 0.5] });
        this.observer.observe(video);
    },

    destroy() { this.observer?.disconnect(); },

    toggle() {
        const video = this.$refs.video;
        if (video.paused) {
            this.manual = true;   // مشتری خودش زد؛ دیگر خودکار نمی‌ایستد/پخش نمی‌شود
            this.observer?.disconnect();
            video.play().catch(() => {});
        } else {
            this.manual = true;
            this.observer?.disconnect();
            video.pause();
        }
    },

    toggleMute() {
        this.muted = !this.muted;
        this.$refs.video.muted = this.muted;
    },
}));

/*
 | پیام کوچک بالای نوار پایین (کد تخفیف سایت): با «×» بسته می‌شود و برای همان کد دیگر نشان داده نمی‌شود.
 | localStorage ممکن است بسته باشد (حالت خصوصی)؛ آن وقت فقط تا بستن صفحه.
 */
Alpine.data('dismissible', (key) => ({
    open: false,
    init() {
        let closed = false;
        try { closed = window.localStorage.getItem(key) === '1'; } catch (e) { closed = false; }
        this.open = !closed;
    },
    close() {
        this.open = false;
        try { window.localStorage.setItem(key, '1'); } catch (e) { /* بی اثر */ }
    },
}));

Alpine.start();
