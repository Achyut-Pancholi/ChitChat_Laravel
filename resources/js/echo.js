import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Prefer PHP-injected runtime values (set in blade template) over build-time Vite env vars
const reverbKey    = window.__REVERB_KEY__    ?? import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost   = window.__REVERB_HOST__   ?? import.meta.env.VITE_REVERB_HOST;
const reverbPort   = window.__REVERB_PORT__   ?? import.meta.env.VITE_REVERB_PORT ?? 443;
const reverbForceTLS = window.__REVERB_TLS__  ?? ((import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbForceTLS,
    enabledTransports: ['ws', 'wss'],
});
