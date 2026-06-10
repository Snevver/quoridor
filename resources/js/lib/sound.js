/**
 * Tiny WebAudio synth — no assets, just oscillators.
 * Every cue is short and unobtrusive; global mute persists in localStorage.
 */

let ctx = null;
let muted = localStorage.getItem('quoridor-muted') === '1';

function ac() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
}

export function isMuted() {
    return muted;
}

export function toggleMute() {
    muted = !muted;
    localStorage.setItem('quoridor-muted', muted ? '1' : '0');
    return muted;
}

function tone({ freq, time = 0, length = 0.12, type = 'sine', gain = 0.08, slideTo = null }) {
    if (muted) return;
    try {
        const audio = ac();
        const t = audio.currentTime + time;
        const osc = audio.createOscillator();
        const amp = audio.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, t);
        if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t + length);
        amp.gain.setValueAtTime(0, t);
        amp.gain.linearRampToValueAtTime(gain, t + 0.012);
        amp.gain.exponentialRampToValueAtTime(0.0001, t + length);
        osc.connect(amp).connect(audio.destination);
        osc.start(t);
        osc.stop(t + length + 0.05);
    } catch {
        /* audio is a garnish — never let it break the game */
    }
}

export const sfx = {
    move() {
        tone({ freq: 520, type: 'triangle', length: 0.09, gain: 0.07 });
        tone({ freq: 780, time: 0.05, type: 'triangle', length: 0.1, gain: 0.05 });
    },
    wall() {
        tone({ freq: 180, type: 'square', length: 0.1, gain: 0.05 });
        tone({ freq: 95, time: 0.02, type: 'sine', length: 0.16, gain: 0.1 });
    },
    error() {
        tone({ freq: 220, type: 'sawtooth', length: 0.12, gain: 0.05 });
        tone({ freq: 160, time: 0.08, type: 'sawtooth', length: 0.16, gain: 0.05 });
    },
    match() {
        [392, 523, 659, 784].forEach((f, i) => tone({ freq: f, time: i * 0.09, type: 'triangle', length: 0.18, gain: 0.07 }));
    },
    win() {
        [523, 659, 784, 1047, 1319].forEach((f, i) => tone({ freq: f, time: i * 0.11, type: 'triangle', length: 0.3, gain: 0.08 }));
    },
    lose() {
        [330, 277, 220].forEach((f, i) => tone({ freq: f, time: i * 0.16, type: 'sine', length: 0.32, gain: 0.07 }));
    },
    tick() {
        tone({ freq: 880, type: 'sine', length: 0.05, gain: 0.03 });
    },
};
