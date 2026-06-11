/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],
    theme: {
        extend: {
            fontFamily: {
                display: ['Unbounded', 'sans-serif'],
                body: ['Sora', 'sans-serif'],
                mono: ['"IBM Plex Mono"', 'monospace'],
            },
            // All values resolve from the :root CSS variables in app.css —
            // the palette lives there; these are just named handles. The
            // rgb(var() / <alpha-value>) form keeps opacity modifiers
            // (bg-p1/20 etc.) working.
            colors: {
                void: 'rgb(var(--c-void) / <alpha-value>)',
                surface: 'rgb(var(--c-surface) / <alpha-value>)',
                raised: 'rgb(var(--c-raised) / <alpha-value>)',
                cell: 'rgb(var(--c-cell) / <alpha-value>)',
                'cell-hover': 'rgb(var(--c-cell-hover) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                p1: 'rgb(var(--c-p1) / <alpha-value>)',
                'p1-bright': 'rgb(var(--c-p1-bright) / <alpha-value>)',
                p2: 'rgb(var(--c-p2) / <alpha-value>)',
                'p2-bright': 'rgb(var(--c-p2-bright) / <alpha-value>)',
                gold: 'rgb(var(--c-accent) / <alpha-value>)',
                mint: 'rgb(var(--c-success) / <alpha-value>)',
                ink: 'rgb(var(--c-ink) / <alpha-value>)',
                dim: 'rgb(var(--c-dim) / <alpha-value>)',
            },
            boxShadow: {
                'glow-p1': '0 0 18px rgb(var(--c-p1) / 0.5), 0 0 50px rgb(var(--c-p1) / 0.22)',
                'glow-p2': '0 0 18px rgb(var(--c-p2) / 0.5), 0 0 50px rgb(var(--c-p2) / 0.22)',
                'glow-gold': '0 0 24px rgb(var(--c-accent) / 0.45), 0 0 70px rgb(var(--c-accent) / 0.18)',
            },
        },
    },
    plugins: [],
};
