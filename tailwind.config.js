import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                cream: '#FDFBF7',
                sage: '#E5C4B3',
                leaf: '#D85A38',
                terracotta: '#D85A38',
                clay: '#A84830',
                bark: '#3C2A21',
                ink: '#3C2A21',
            },
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                display: ['Sora', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                soft: '0 20px 50px -20px rgba(60, 42, 33, 0.18)',
                glow: '0 0 40px rgba(216, 90, 56, 0.35)',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-12px)' },
                },
                'pulse-soft': {
                    '0%, 100%': { opacity: '0.45', transform: 'scale(1)' },
                    '50%': { opacity: '0.8', transform: 'scale(1.05)' },
                },
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '200% 0' },
                    '100%': { backgroundPosition: '-200% 0' },
                },
                'bounce-dot': {
                    '0%, 80%, 100%': { transform: 'translateY(0)', opacity: '0.4' },
                    '40%': { transform: 'translateY(-6px)', opacity: '1' },
                },
            },
            animation: {
                float: 'float 6s ease-in-out infinite',
                'float-delayed': 'float 7s ease-in-out 1s infinite',
                'pulse-soft': 'pulse-soft 4s ease-in-out infinite',
                'fade-up': 'fade-up 0.6s ease-out both',
                shimmer: 'shimmer 2.5s linear infinite',
                'bounce-dot': 'bounce-dot 1.2s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
