const TZ = 'Asia/Jakarta';

export const pct = (v) => (v === null || v === undefined ? '-' : (v * 100).toFixed(1) + '%');

export function fmtTime(iso) {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('id-ID', { timeZone: TZ, dateStyle: 'short', timeStyle: 'short' });
}

export function fmtDur(sec) {
    sec = Math.max(0, Math.round(sec || 0));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    return h > 0 ? `${h}j ${m}m` : `${m}m`;
}

export const STATUS_LABEL = { running: 'Berjalan', idle: 'Siaga', downtime: 'Downtime', offline: 'Offline' };
export const WO_LABEL = { planned: 'Direncanakan', running: 'Berjalan', paused: 'Dijeda', completed: 'Selesai', cancelled: 'Dibatalkan' };

export function todayLocal() {
    return new Date().toLocaleDateString('en-CA', { timeZone: TZ }); // YYYY-MM-DD
}
