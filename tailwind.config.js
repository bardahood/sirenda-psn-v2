import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Enums/*.php',
    ],
    // Kelas status dibentuk dinamis ('badge-' + kode) sehingga harus di-safelist.
    safelist: [{ pattern: /^(badge|titik)-(ON_TRACK|BERISIKO|TERLAMBAT|TANPA_DATA|BELUM|DRAFT|DIAJUKAN|DIVERIFIKASI|DIKEMBALIKAN)$/ }],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans Variable"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Token desain Dashboard PSN.
                primer: { DEFAULT: '#0E2747', 50: '#eef2f8', 100: '#d5deeb', 700: '#163a66', 800: '#112f54', 900: '#0E2747' },
                aksen: { DEFAULT: '#1F6FD1', 50: '#ebf3fc', 100: '#d3e4f8', 600: '#1F6FD1', 700: '#1a5db0' },
                kanvas: '#f5f7fa',
            },
            fontSize: {
                kpi: ['32px', { lineHeight: '40px', fontWeight: '700' }],
                panel: ['16px', { lineHeight: '24px', fontWeight: '600' }],
                isi: ['14px', { lineHeight: '20px' }],
                label: ['12px', { lineHeight: '16px', fontWeight: '500' }],
            },
            borderRadius: { kartu: '12px' },
            spacing: { 18: '72px', 60: '240px' },
        },
    },
    plugins: [forms],
};
