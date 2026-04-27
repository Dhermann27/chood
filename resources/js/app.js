import './bootstrap';
import '../css/app.css';

import {createApp, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {FontAwesomeIcon, FontAwesomeLayers, FontAwesomeLayersText} from '@fortawesome/vue-fontawesome';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faAdd, faAlarmClock, faArrowsUpDownLeftRight, faBadgeCheck, faBroom,
    faClipboard, faCowbellCirclePlus, faDroplet, faEdit, faHandDots,
    faHouseChimneyBlank, faHouseCircleCheck, faMars, faPrescriptionBottlePill,
    faRotateLeft, faRotateRight, faSheep, faSpinnerThird, faStethoscope, faTrash, faTurkey,
    faVenus, faWeightHanging, faXmark,
} from '@awesome.me/kit-ed8e499057/icons/classic/solid';
import {ZiggyVue} from '../../vendor/tightenco/ziggy/dist/index.esm.js';

library.add(
    faAdd, faAlarmClock, faArrowsUpDownLeftRight, faBadgeCheck, faBroom,
    faClipboard, faCowbellCirclePlus, faDroplet, faEdit, faHandDots,
    faHouseChimneyBlank, faHouseCircleCheck, faMars, faPrescriptionBottlePill,
    faRotateLeft, faRotateRight, faSheep, faSpinnerThird, faStethoscope, faTrash, faTurkey,
    faVenus, faWeightHanging, faXmark,
);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
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

        return vueApp.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
}).catch((error) => {
    console.error(error);
});
