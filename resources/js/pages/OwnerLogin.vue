<template>
  <div class="auth-page auth-login owner-gate">
    <div class="auth-card auth-card--login">
      <div class="auth-card-top">
        <div class="auth-brand">
          <BrandLogo variant="light" />
        </div>
        <LanguageSwitcher />
      </div>

      <div class="auth-card-body">
        <h1>{{ $t('loginGreeting') }}</h1>
        <p class="subtitle">{{ $t('ownerProtectedLogin') }}</p>

        <form @submit.prevent="submit" class="form-grid one-col">
          <div class="field">
            <label>{{ $t('ownerLoginLabel') }}</label>
            <input
              v-model.trim="form.login"
              type="text"
              required
              autocomplete="username"
              :placeholder="$t('ownerLoginLabel')"
            />
          </div>
          <div class="field">
            <label>{{ $t('password') }}</label>
            <input
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              :placeholder="$t('password')"
            />
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <button class="btn btn-primary auth-submit" type="submit" :disabled="loading">
            {{ loading ? $t('checking') : $t('enterCabinet') }}
          </button>
        </form>
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
import BrandLogo from '../components/BrandLogo.vue';
import LanguageSwitcher from '../components/LanguageSwitcher.vue';
import { t } from '../i18n';

export default {
  components: { BrandLogo, LanguageSwitcher },
  setup() {
    const router = useRouter();
    const loading = ref(false);
    const error = ref('');
    const form = reactive({
      login: '',
      password: '',
    });
    const supportPhone = '+998 77 444 2525';
    const supportTel = '+998774442525';

    onMounted(async () => {
      if (!auth.hasValidSession()) return;
      if (!auth.state.user) {
        await auth.fetchUser();
      }
      if (auth.isOwner()) {
        await router.replace({ name: 'owner-cabinet' });
      }
    });

    async function submit() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.post('/auth/owner-login', form);
        auth.setAuth(data.user, data.token, data.expires_at);
        router.push({ name: 'owner-cabinet' });
      } catch (e) {
        error.value = e.response?.data?.errors?.login?.[0]
          || e.response?.data?.message
          || t('loginDenied');
      } finally {
        loading.value = false;
      }
    }

    return { form, loading, error, submit, supportPhone, supportTel };
  },
};
</script>
