import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                header: ['"ChunkFive"', 'serif'],
                subheader: ['FuturaBold', 'sans-serif'],
                body: ['FuturaBook', 'sans-serif'],
            },
            textColor: {
                DEFAULT: '#373a36', // Set the default text color
            },
            colors: {
                DEFAULT: '#373a36',
                crimson: '#9E1B32',
                sunshine: '#FFDE17',
                caregiver: '#87B3D1',
                greyhound: '#58595B',
                meadow: '#88C999', // complementary green
                alerted: '#FF4F4F',      // high-intensity alert red
            },
        },
    },

    plugins: [forms],
};
