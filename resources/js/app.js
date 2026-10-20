import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import auth from './auth';
import FormattedNumberInput from './components/FormattedNumberInput.vue';
import { setLocale, i18nState, t } from './i18n';

setLocale(i18nState.locale);

async function bootstrap() {
    await auth.initAuth();

    const app = createApp(App);
    app.component('FormattedNumberInput', FormattedNumberInput);
    app.config.globalProperties.$t = t;
    app.use(router);
    await router.isReady();
    app.mount('#app');
}

bootstrap();
