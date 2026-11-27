/*
 | انتخاب موقعیت تحویل روی نقشه (صفحه‌ی تسویه).
 |
 | - Leaflet فقط وقتی بار می‌شود که مشتری «انتخاب موقعیت روی نقشه» را بزند (فایل جدا؛ صفحه‌ها سبک می‌مانند).
 | - مشتری نشانه را می‌کشد یا روی نقشه می‌زند؛ «استفاده از موقعیت فعلی من» از GPS مرورگر (با اجازه‌ی خودش).
 | - نتیجه فقط در دو خانه‌ی پنهان فورم (delivery_lat / delivery_lng) می‌نشیند؛ سرور دوباره بررسی می‌کند.
 | - اگر نقشه یا GPS کار نکرد، پیام می‌دهد و آدرس نوشتاری مثل همیشه کافی است.
 |
 | cfg: { tileUrl, attribution, maxZoom, center: [lat, lng], zoom, lat, lng, source, text: {…} }
 */
export default (cfg) => ({
    open: false,
    loading: false,
    failed: false,
    locating: false,
    message: '',
    lat: cfg.lat || '',
    lng: cfg.lng || '',
    source: cfg.source || '',
    // نقطه‌ای که روی نقشه است ولی هنوز «تأیید» نشده
    draft: null,
    map: null,
    marker: null,

    get chosen() {
        return this.lat !== '' && this.lng !== '';
    },

    async show() {
        this.open = true;
        this.message = '';
        if (this.map) {
            this.$nextTick(() => this.map.invalidateSize());
            return;
        }
        this.loading = true;
        try {
            const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
            await this.$nextTick();
            const start = this.chosen ? [Number(this.lat), Number(this.lng)] : cfg.center;
            this.map = L.map(this.$refs.map, { zoomControl: true, attributionControl: true }).setView(start, this.chosen ? 17 : cfg.zoom);
            const tiles = L.tileLayer(cfg.tileUrl, { maxZoom: cfg.maxZoom, attribution: cfg.attribution });
            tiles.on('tileerror', () => { if (! this.map._bzTileOk) { this.failed = true; this.message = cfg.text.mapFailed; } });
            tiles.on('tileload', () => { this.map._bzTileOk = true; this.failed = false; });
            tiles.addTo(this.map);

            // نشانه بی عکس (بی فایل بیرونی و بی مشکل مسیر عکس)
            const icon = L.divIcon({
                className: '',
                html: '<span class="bz-pin" aria-hidden="true"></span>',
                iconSize: [32, 42],
                iconAnchor: [16, 42],
            });
            this.marker = L.marker(start, { draggable: true, icon, keyboard: true, title: cfg.text.drag }).addTo(this.map);
            this.draft = { lat: start[0], lng: start[1], source: this.source || 'map' };
            this.marker.on('dragend', () => this.setDraft(this.marker.getLatLng(), 'map'));
            this.map.on('click', (e) => { this.marker.setLatLng(e.latlng); this.setDraft(e.latlng, 'map'); });
        } catch (e) {
            this.failed = true;
            this.message = cfg.text.mapFailed;
        } finally {
            this.loading = false;
        }
    },

    setDraft(latlng, source) {
        this.draft = { lat: latlng.lat, lng: latlng.lng, source };
        this.message = '';
    },

    useMyLocation() {
        if (! ('geolocation' in navigator)) {
            this.message = cfg.text.gpsUnavailable;
            return;
        }
        this.locating = true;
        this.message = '';
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                this.locating = false;
                const point = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                // نقشه را (دوباره) باز کن تا مشتری نقطه را ببیند و «تأیید» بزند
                await this.show();
                if (this.map && this.marker) {
                    this.marker.setLatLng(point);
                    this.map.setView(point, 17);
                }
                this.setDraft(point, 'gps');
                this.message = cfg.text.gpsDone;
            },
            (err) => {
                this.locating = false;
                this.message = err.code === 1 ? cfg.text.gpsDenied : cfg.text.gpsUnavailable;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
        );
    },

    confirm() {
        if (! this.draft) {
            return;
        }
        this.lat = this.draft.lat.toFixed(6);
        this.lng = this.draft.lng.toFixed(6);
        this.source = this.draft.source;
        this.open = false;
    },

    // آدرس ذخیره‌شده انتخاب شد (دفترچه‌ی آدرس): موقعیت همان آدرس، یا خالی
    setPoint(detail) {
        const lat = detail && detail.lat ? String(detail.lat) : '';
        const lng = detail && detail.lng ? String(detail.lng) : '';
        this.lat = lat && lng ? lat : '';
        this.lng = lat && lng ? lng : '';
        this.source = lat && lng ? (detail.source || 'map') : '';
        this.draft = null;
        this.message = '';
        if (this.chosen && this.map && this.marker) {
            const point = [Number(this.lat), Number(this.lng)];
            this.marker.setLatLng(point);
            this.map.setView(point, 17);
        }
    },

    clear() {
        this.lat = '';
        this.lng = '';
        this.source = '';
        this.draft = null;
    },
});
