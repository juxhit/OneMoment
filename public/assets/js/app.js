
function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;
  const meta = document.querySelector('meta[name="app-base"]');
  if (!meta) return;
  const base = meta.getAttribute('content');
  if (!base) return;
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(() => {});
  });
}
function albumApp(config) {
  return {
    token: config.token,
    apiBase: config.apiBase,
    pinRequired: config.pinRequired,
    hasAccess: config.hasAccess,
    isSecure: config.isSecure,
    maxFileMb: config.maxFileMb || 20,
    pin: '',
    pinError: '',
    photos: [],
    sinceId: 0,
    loadingGallery: false,
    uploading: false,
    uploadProgress: 0,
    statusMessage: '',
    statusType: 'info',
    guestLabel: '',
    get canUseCamera() { return this.isSecure; },
    init() { registerServiceWorker(); if (this.hasAccess) this.loadGallery(); },
    async verifyPin() {
      this.pinError = '';
      try {
        const res = await fetch(this.apiBase + 'pin-verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: this.token, pin: this.pin }),
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (!res.ok) {
          this.pinError = data.error === 'invalid_pin' ? 'Неверный PIN' : (data.error || 'Ошибка');
          return;
        }
        this.hasAccess = true;
        this.pin = '';
        await this.loadGallery();
      } catch (e) { this.pinError = 'Не удалось проверить PIN'; }
    },
    async loadGallery() {
      if (!this.hasAccess || this.loadingGallery) return;
      this.loadingGallery = true;
      try {
        const url = `${this.apiBase}media.php?token=${encodeURIComponent(this.token)}&since_id=0&limit=100`;
        const res = await fetch(url, { credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) { if (data.error === 'pin_required') this.hasAccess = false; return; }
        this.photos = (data.items || []).slice().reverse();
        if (this.photos.length > 0) this.sinceId = Math.max(...this.photos.map((p) => p.id));
      } catch (e) { this.setStatus('Не удалось загрузить галерею', 'error'); }
      finally { this.loadingGallery = false; }
    },
    openGalleryPicker() { this.$refs.galleryInput.click(); },
    openCameraPicker() { if (!this.canUseCamera) { this.openGalleryPicker(); return; } this.$refs.cameraInput.click(); },
    async onFilesSelected(event) {
      const files = Array.from(event.target.files || []);
      event.target.value = '';
      for (const file of files) await this.uploadOne(file);
    },
    async uploadOne(file) {
      if (!this.hasAccess) return;
      let blob = file;
      const maxBytes = this.maxFileMb * 1024 * 1024;
      try {
        if (file.size > maxBytes && window.imageCompression) {
          blob = await window.imageCompression(file, {
            maxSizeMB: this.maxFileMb,
            initialQuality: 0.95,
            alwaysKeepResolution: true,
            useWebWorker: true,
          });
        }
      } catch (e) { blob = file; }
      const form = new FormData();
      form.append('token', this.token);
      form.append('file', blob, file.name);
      const label = (this.guestLabel || '').trim();
      if (label) form.append('guest_label', label);
      this.uploading = true;
      this.uploadProgress = 0;
      try {
        const result = await this.xhrUpload(form);
        if (result.status === 'approved') {
          this.photos.unshift({
            id: result.id, thumb_url: result.thumb_url, full_url: result.full_url,
            guest_label: result.guest_label || null, status: result.status
          }); this.sinceId = Math.max(this.sinceId, result.id); }
        this.setStatus(result.message || 'Загружено', result.status === 'pending' ? 'info' : 'success');
      } catch (err) { this.setStatus(err.message || 'Ошибка загрузки', 'error'); }
      finally { this.uploading = false; this.uploadProgress = 0; }
    },
    xhrUpload(form) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', this.apiBase + 'upload.php');
        xhr.withCredentials = true;
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.uploadProgress = Math.round((e.loaded / e.total) * 100); };
        xhr.onload = () => {
          let data = {}; try { data = JSON.parse(xhr.responseText); } catch (e) {}
          if (xhr.status >= 200 && xhr.status < 300) resolve(data);
          else reject(new Error(data.error || 'Ошибка загрузки'));
        };
        xhr.onerror = () => reject(new Error('Сеть недоступна'));
        xhr.send(form);
      });
    },
    setStatus(message, type = 'info') {
      this.statusMessage = message; this.statusType = type;
      if (type === 'success') setTimeout(() => { if (this.statusMessage === message) this.statusMessage = ''; }, 3000);
    },
  };
}
function adminHealthWidget(apiUrl) {
  return {
    health: null, error: '',
    async load() {
      try {
        const res = await fetch(apiUrl, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('unauthorized');
        this.health = await res.json();
      } catch (e) { this.error = 'Не удалось загрузить статус диска'; }
    },
    formatBytes(bytes) {
      if (!bytes && bytes !== 0) return '—';
      const units = ['B', 'KB', 'MB', 'GB'];
      let v = bytes, i = 0;
      while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
      return v.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
    },
  };
}

function wallApp(config) {
  const polaroidPresets = [
    { left: 5, top: 12, width: 22, rotate: -8 },
    { left: 30, top: 8, width: 24, rotate: 5 },
    { left: 58, top: 14, width: 20, rotate: -4 },
    { left: 78, top: 10, width: 18, rotate: 7 },
    { left: 12, top: 42, width: 26, rotate: 3 },
    { left: 40, top: 38, width: 22, rotate: -6 },
    { left: 65, top: 44, width: 24, rotate: 4 },
    { left: 8, top: 68, width: 20, rotate: -3 },
    { left: 32, top: 72, width: 28, rotate: 6 },
    { left: 62, top: 70, width: 22, rotate: -5 },
  ];

  return {
    token: config.token,
    apiBase: config.apiBase,
    wallMode: config.wallMode || 'carousel',
    photos: Array.isArray(config.initialPhotos) ? config.initialPhotos.slice() : [],
    sinceId: config.sinceId || 0,
    title: config.title || 'OneMoment',
    currentIndex: 0,
    accentIndex: 0,
    carouselTimer: null,
    accentTimer: null,
    eventSource: null,
    pollTimer: null,
    sseWatchdog: null,
    reconnectTimer: null,
    reconnectAttempt: 0,
    usePoll: false,
    connectionLabel: '',

    get currentPhoto() {
      if (this.photos.length === 0) return null;
      const idx = Math.min(this.currentIndex, this.photos.length - 1);
      return this.photos[idx];
    },

    get accentPhoto() {
      if (this.photos.length === 0) return null;
      const fromEnd = this.photos.length - 1 - (this.accentIndex % this.photos.length);
      return this.photos[Math.max(0, fromEnd)];
    },

    get gridPhotos() {
      const accentId = this.accentPhoto?.id;
      return this.photos.filter((p) => p.id !== accentId).slice(-48).reverse();
    },

    init() {
      if (this.photos.length > 0) {
        this.currentIndex = this.photos.length - 1;
        this.sinceId = this.photos.reduce((m, p) => Math.max(m, p.id), this.sinceId);
      }
      this.connectStream();
      this.startModeEffects();
    },

    startModeEffects() {
      this.clearModeTimers();
      if (this.wallMode === 'carousel') {
        this.startCarousel();
      }
      if (this.wallMode === 'dynamic_mosaic') {
        this.accentTimer = setInterval(() => {
          if (this.photos.length > 1) this.accentIndex += 1;
        }, 5000);
      }
    },

    clearModeTimers() {
      if (this.carouselTimer) {
        clearInterval(this.carouselTimer);
        this.carouselTimer = null;
      }
      if (this.accentTimer) {
        clearInterval(this.accentTimer);
        this.accentTimer = null;
      }
    },

    polaroidStyle(photo) {
      const id = Number(photo?.id) || 0;
      const preset = polaroidPresets[id % polaroidPresets.length];
      const jitter = ((id * 13) % 9) - 4;
      const z = (id % 12) + 1;
      return [
        `left:${preset.left + jitter * 0.35}%`,
        `top:${preset.top + jitter * 0.25}%`,
        `width:${preset.width + (id % 4)}%`,
        `transform:rotate(${preset.rotate + jitter * 0.5}deg)`,
        `z-index:${z}`,
      ].join(';');
    },

    generationClass(photo) {
      const idx = this.photos.findIndex((p) => p.id === photo.id);
      if (idx < 0) return 'wall-gen-0';
      const fromEnd = this.photos.length - 1 - idx;
      const genSize = 10;
      const currentGen = Math.floor((this.photos.length - 1) / genSize);
      const photoGen = Math.floor(fromEnd / genSize);
      const age = Math.max(0, currentGen - photoGen);
      return 'wall-gen-' + Math.min(age, 4);
    },

    connectStream() {
      this.clearStream();
      if (this.usePoll) {
        this.startPoll();
        return;
      }

      const url = `${this.apiBase}stream.php?token=${encodeURIComponent(this.token)}&since_id=${this.sinceId}`;
      this.eventSource = new EventSource(url);
      this.connectionLabel = 'Live';

      this.eventSource.addEventListener('photo', (event) => {
        try {
          const photo = JSON.parse(event.data);
          this.addPhoto(photo);
        } catch (e) {}
        this.resetSseWatchdog();
      });

      this.eventSource.addEventListener('ping', () => {
        this.resetSseWatchdog();
      });

      this.eventSource.onopen = () => {
        this.reconnectAttempt = 0;
        this.connectionLabel = 'Live';
        this.resetSseWatchdog();
      };

      this.eventSource.onerror = () => {
        this.connectionLabel = 'Переподключение…';
        this.eventSource?.close();
        this.eventSource = null;
        this.clearSseWatchdog();
        this.scheduleReconnect();
      };

      this.resetSseWatchdog(true);
    },

    resetSseWatchdog(initial) {
      this.clearSseWatchdog();
      const timeout = initial ? 12000 : 15000;
      this.sseWatchdog = setTimeout(() => this.fallbackToPoll(), timeout);
    },

    clearSseWatchdog() {
      if (this.sseWatchdog) {
        clearTimeout(this.sseWatchdog);
        this.sseWatchdog = null;
      }
    },

    scheduleReconnect() {
      if (this.usePoll) return;
      if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
      const delay = Math.min(30000, 1000 * Math.pow(2, this.reconnectAttempt));
      this.reconnectAttempt += 1;
      this.reconnectTimer = setTimeout(() => this.connectStream(), delay);
    },

    fallbackToPoll() {
      if (this.usePoll) return;
      this.usePoll = true;
      this.connectionLabel = 'Polling';
      this.clearStream();
      this.startPoll();
    },

    startPoll() {
      this.pollOnce();
      if (this.pollTimer) clearInterval(this.pollTimer);
      this.pollTimer = setInterval(() => this.pollOnce(), 2000);
    },

    async pollOnce() {
      try {
        const url = `${this.apiBase}media.php?wall=1&token=${encodeURIComponent(this.token)}&since_id=${this.sinceId}&limit=50`;
        const res = await fetch(url);
        const data = await res.json();
        if (!res.ok) return;
        (data.items || []).forEach((photo) => this.addPhoto(photo));
      } catch (e) {}
    },

    addPhoto(photo) {
      if (!photo || !photo.id) return;
      if (this.photos.some((p) => p.id === photo.id)) return;
      this.photos.push(photo);
      this.sinceId = Math.max(this.sinceId, photo.id);
      if (this.wallMode === 'carousel') {
        this.currentIndex = this.photos.length - 1;
      }
      if (this.wallMode === 'global_mosaic' && this.photos.length > 200) {
        this.photos = this.photos.slice(-200);
      } else if (this.photos.length > 120) {
        this.photos = this.photos.slice(-120);
      }
    },

    startCarousel() {
      if (this.carouselTimer) clearInterval(this.carouselTimer);
      this.carouselTimer = setInterval(() => {
        if (this.photos.length < 2) return;
        this.currentIndex = (this.currentIndex + 1) % this.photos.length;
      }, 6000);
    },

    onImageLoad() {},

    clearStream() {
      this.clearSseWatchdog();
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
      if (this.reconnectTimer) {
        clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
      }
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },
  };
}
