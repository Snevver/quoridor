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
            colors: {
                void: '#07090f',
                surface: '#11141f',
                raised: '#1a1e2e',
                cell: '#161a29',
                'cell-hover': '#1f2438',
                line: '#262c44',
                p1: '#6d7cff',
                'p1-bright': '#93a0ff',
                p2: '#fb4d6d',
                'p2-bright': '#ff7d94',
                gold: '#fbbf24',
                mint: '#34d399',
                ink: '#eef1ff',
                dim: '#6b7394',
            },
            boxShadow: {
                'glow-p1': '0 0 18px rgba(109, 124, 255, 0.55), 0 0 50px rgba(109, 124, 255, 0.25)',
                'glow-p2': '0 0 18px rgba(251, 77, 109, 0.55), 0 0 50px rgba(251, 77, 109, 0.25)',
                'glow-gold': '0 0 24px rgba(251, 191, 36, 0.5), 0 0 70px rgba(251, 191, 36, 0.2)',
            },
        },
    },
    plugins: [],
};
