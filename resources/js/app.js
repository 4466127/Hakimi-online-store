/*
 | جاوااسکریپت صفحه‌های مشتری. فقط Alpine.js (سبک)؛ هیچ کتابخانه‌ی بیرونی.
 | صفحه‌ها بی جاوااسکریپت هم کار می‌کنند؛ Alpine فقط کار را راحت‌تر می‌کند
 | (انتخاب نوع جنس، گالری، فیلتر در موبایل؛ سبد در مرحله‌ی ۴).
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.start();
