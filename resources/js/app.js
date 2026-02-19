import './bootstrap';
import '../css/app.css';

import {createApp, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {FontAwesomeIcon, FontAwesomeLayers, FontAwesomeLayersText} from '@fortawesome/vue-fontawesome';
import {byPrefixAndName} from '@awesome.me/kit-ed8e499057/icons';
import {ZiggyVue} from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

await createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({el, App, props, plugin}) {
        const vueApp = createApp({render: () => h(App, props)});

        vueApp
            .use(plugin)
            .use(ZiggyVue)
            .component('FontAwesomeIcon', FontAwesomeIcon)
            .component('font-awesome-layers', FontAwesomeLayers)
            .component('font-awesome-layers-text', FontAwesomeLayersText);

        // Make Font Awesome kit icons available everywhere
        vueApp.config.globalProperties.$fa = byPrefixAndName;

        return vueApp.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
});
