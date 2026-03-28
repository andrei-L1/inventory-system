import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';

import 'primeicons/primeicons.css';
import '../css/app.css';

import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        
        app.use(plugin)
           .use(ToastService)
           .use(ConfirmationService)
           .use(PrimeVue, {
               ripple: true,
               theme: {
                   preset: Aura,
                   options: {
                       darkModeSelector: '.app-dark',
                       cssLayer: {
                           name: 'primevue',
                           order: 'custom, primevue'
                       }
                   }
               }
           })
           .mount(el);
    },
    progress: {
        color: '#10b981', // Emerald glow
    },
});
