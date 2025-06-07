import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '/vendor/tightenco/ziggy';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon,FontAwesomeLayers, FontAwesomeLayersText } from '@fortawesome/vue-fontawesome';
import { fas, far } from '@awesome.me/kit-ed8e499057/icons';
library.add(fas.faEdit, fas.faTrash, fas.faPlus, fas.faWeightHanging, fas.faSpinnerThird, fas.faBroom,
    fas.faCheckSquare, far.faSquare, fas.faBadgeCheck, fas.faRotateLeft, fas.faCowbellCirclePlus, fas.faClipboard,
    fas.faHouseCircleCheck, fas.faHouseChimneyBlank, fas.faBowlFood, fas.faPrescriptionBottlePill, fas.faHandDots,
    fas.faNoteMedical, fas.faMars, fas.faVenus, fas.faDroplet, fas.faSheep, fas.faSoap, fas.faPumpSoap, fas.faWind,
    fas.faBrush, fas.faTooth, fas.faEarListen, fas.faEye, fas.faAlarmClock);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const app = createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('font-awesome-icon', FontAwesomeIcon)
            .component('font-awesome-layers', FontAwesomeLayers)
            .component('font-awesome-layers-text', FontAwesomeLayersText)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
