import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let echo = null;

function getEcho() {
    if (echo) return echo;
    try {
        const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
        const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
        echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (e) {
        console.error('Echo gagal dibuat', e);
    }
    return echo;
}

/** Dengarkan update mesin real-time. Mengembalikan fungsi untuk berhenti. */
export function listenMachines(onMachine, onState = () => {}) {
    const e = getEcho();
    if (!e) return () => {};

    const pusher = e.connector?.pusher;
    const stateHandler = (s) => onState(s.current);
    pusher?.connection.bind('state_change', stateHandler);
    onState(pusher?.connection.state ?? 'unavailable');

    e.channel('mepro').listen('.machine.updated', (payload) => onMachine(payload.machine));

    return () => {
        pusher?.connection.unbind('state_change', stateHandler);
        e.leave('mepro');
    };
}
