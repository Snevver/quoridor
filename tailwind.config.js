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
            // "Oxide" — warm black, verdigris vs. copper, bone & brass.
            colors: {
                void: '#0d0b09',
                surface: '#16120e',
                raised: '#1e1913',
                cell: '#18140f',
                'cell-hover': '#221c15',
                line: '#2f2820',
                p1: '#2fc39b',
                'p1-bright': '#62e2bd',
                p2: '#e0763c',
                'p2-bright': '#f29a68',
                gold: '#d4a843',
                mint: '#b8e34d',
                ink: '#f3ece0',
                dim: '#968b78',
            },
            boxShadow: {
                'glow-p1': '0 0 18px rgba(47, 195, 155, 0.5), 0 0 50px rgba(47, 195, 155, 0.22)',
                'glow-p2': '0 0 18px rgba(224, 118, 60, 0.5), 0 0 50px rgba(224, 118, 60, 0.22)',
                'glow-gold': '0 0 24px rgba(212, 168, 67, 0.45), 0 0 70px rgba(212, 168, 67, 0.18)',
            },
        },
    },
    plugins: [],
};
