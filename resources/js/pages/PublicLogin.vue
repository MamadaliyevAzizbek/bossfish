<template>
  <div class="auth-page auth-login">
    <div class="auth-card auth-card--login">
      <div class="auth-card-top">
        <div class="auth-brand">
          <BrandLogo variant="light" />
        </div>
        <LanguageSwitcher />
      </div>

      <div class="auth-card-body">
        <h1>{{ $t('loginGreeting') }}</h1>
        <p class="subtitle">{{ $t('phonePasswordLogin') }}</p>

        <form @submit.prevent="submit" class="form-grid one-col">
          <div class="field">
            <label>{{ $t('phoneNumber') }}</label>
            <PhoneInput v-model="form.phone" required />
          </div>
          <div class="field">
            <label>{{ $t('password') }}</label>
            <input
              v-model="form.password"
              type="password"
              required
              minlength="4"
              :placeholder="$t('password')"
            />
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <button class="btn btn-primary auth-submit" type="submit" :disabled="loading">
            {{ loading ? $t('loggingIn') : $t('login') }}
          </button>
        </form>

        <router-link class="auth-back" :to="{ name: 'home' }">{{ $t('back') }}</router-link>
      </div>

      <div class="auth-card-foot">
        <p class="auth-contact">{{ $t('loginContactHint') }}</p>
        <a class="auth-contact-link" :href="`tel:${supportTel}`">{{ supportPhone }}</a>
        <p class="auth-rights">{{ $t('loginRightsReserved') }}</p>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import auth from '../auth';
import PhoneInput from '../components/PhoneInput.vue';
import BrandLogo from '../components/BrandLogo.vue';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import { isCompletePhone, toFullPhone } from '../utils/phone';
import { t } from '../i18n';

export default {
  components: { PhoneInput, BrandLogo, LanguageSwitcher },
  props: {
    role: { type: String, required: true },
  },
  setup(props) {
    const router = useRouter();
    const loading = ref(false);
    const error = ref('');
    const form = reactive({ phone: toFullPhone(''), password: '' });
    const supportPhone = '+998 77 444 2525';
    const supportTel = '+998774442525';

    onMounted(async () => {
      if (!auth.hasValidSession()) return;
      if (!auth.state.user) {
        await auth.fetchUser();
      }
      if (auth.isAuthenticated() && auth.state.user?.role === props.role) {
        await router.replace(auth.homeForRole());
      }
    });

    async function submit() {
      loading.value = true;
      error.value = '';
      if (!isCompletePhone(form.phone)) {
        error.value = t('phoneIncomplete');
        loading.value = false;
        return;
      }
      try {
        auth.clearAuth();
        const { data } = await window.axios.post('/auth/login', {
          phone: toFullPhone(form.phone),
          password: form.password,
          role: props.role,
        });
        auth.setAuth(data.user, data.token, data.expires_at);
        await router.push(auth.homeForRole());
      } catch (e) {
        const errors = e.response?.data?.errors || {};
        error.value = errors.phone?.[0]
          || errors.password?.[0]
          || e.response?.data?.message
          || t('errorOccurred');
      } finally {
        loading.value = false;
      }
    }

    return { form, loading, error, submit, supportPhone, supportTel };
  },
};
</script>
