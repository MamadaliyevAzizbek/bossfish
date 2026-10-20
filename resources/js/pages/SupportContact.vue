<template>
  <div class="support-page-wrap" :class="{ 'support-page-wrap--crm': inCrmShell }">
    <SceneBackground v-if="!inCrmShell" scene-key="home" />
    <div class="support-page">
    <div v-if="!inCrmShell" class="page-head">
      <div>
        <h1>
          <span class="support-title-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          {{ $t('supportTitle') }}
        </h1>
        <p>{{ $t('supportHint') }}</p>
      </div>
    </div>

    <div class="support-grid">
      <section class="panel support-phone-card">
        <h2>{{ $t('supportPhoneTitle') }}</h2>
        <p v-if="!inCrmShell">{{ $t('supportPhoneHint') }}</p>
        <a class="support-phone-link" :href="`tel:${phoneTel}`">
          <span class="support-phone-link__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
          </span>
          {{ phone }}
        </a>
        <span class="support-hours">{{ hours }}</span>
      </section>

      <section class="panel">
        <h2>{{ $t('supportWriteTitle') }}</h2>
        <p v-if="!inCrmShell">{{ $t('supportWriteHint') }}</p>
        <form class="form-grid one-col" @submit.prevent="submit">
          <template v-if="!isAuthenticated">
            <div class="field">
              <label>{{ $t('name') }} <span class="req">*</span></label>
              <input v-model="guestName" required maxlength="120" />
            </div>
            <div class="field">
              <label>{{ $t('phone') }} <span class="req">*</span></label>
              <input v-model="guestPhone" required maxlength="32" type="tel" placeholder="+998..." />
            </div>
          </template>
          <div class="field">
            <label>{{ $t('supportMessage') }} <span class="req">*</span></label>
            <textarea v-model="message" rows="5" required maxlength="4000" :placeholder="$t('supportMessagePlaceholder')" />
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <p v-if="sent" class="success-text">{{ $t('supportSent') }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit" :disabled="saving">
              {{ saving ? $t('loading') : $t('supportSend') }}
            </button>
          </div>
        </form>
      </section>
    </div>

    <section v-if="isOwner" class="panel" style="margin-top: 1rem">
      <div class="page-head" style="margin-top: 0">
        <div>
          <h2 style="margin: 0; font-size: 1.1rem">{{ $t('supportInboxTitle') }}</h2>
          <p style="margin: 0.35rem 0 0">{{ $t('supportInboxHint') }}</p>
        </div>
        <span v-if="unreadCount" class="badge active">{{ unreadCount }}</span>
      </div>

      <div class="owner-filter-bar" style="margin-bottom: 0.75rem">
        <div class="field">
          <label>{{ $t('filterStatus') }}</label>
          <select v-model="inboxFilter" @change="loadInbox">
            <option value="unread">{{ $t('supportUnread') }}</option>
            <option value="all">{{ $t('all') }}</option>
            <option value="read">{{ $t('supportRead') }}</option>
          </select>
        </div>
      </div>

      <div v-if="inboxLoading" class="empty">{{ $t('loading') }}</div>
      <div v-else-if="!inbox.length" class="empty">{{ $t('supportInboxEmpty') }}</div>
      <div v-else class="support-inbox-list">
        <article v-for="item in inbox" :key="item.id" class="support-inbox-item">
          <div class="support-inbox-head">
            <strong>{{ item.user_name }}</strong>
            <span class="badge badge-muted">{{ roleLabel(item.user_role) }}</span>
            <span v-if="!item.read_at" class="badge badge-warn">{{ $t('supportUnread') }}</span>
          </div>
          <p>{{ item.message }}</p>
          <div class="support-inbox-meta">
            <a v-if="item.user_phone" class="phone-link" :href="`tel:${normalizePhone(item.user_phone)}`">
              {{ formatPhone(item.user_phone) }}
            </a>
            <span>{{ formatDateTime(item.created_at) }}</span>
          </div>
          <button
            v-if="!item.read_at"
            class="btn btn-outline btn-sm"
            type="button"
            @click="markRead(item)"
          >
            {{ $t('supportMarkRead') }}
          </button>
        </article>
      </div>
    </section>
  </div>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import auth from '../auth';
import SceneBackground from '../components/SceneBackground.vue';
import { t } from '../i18n';
import { formatPhoneDisplay, toFullPhone } from '../utils/phone';

export default {
  components: { SceneBackground },
  setup() {
    const isAuthenticated = computed(() => auth.isAuthenticated());
    const isOwner = computed(() => auth.isOwner());
    const inCrmShell = computed(() => (
      auth.isOwner() || auth.isDirector() || auth.isWorker() || auth.isVenger()
    ));
    const phone = ref('+998 77 444 2525');
    const phoneTel = ref('+998774442525');
    const hours = ref('24/7');
    const guestName = ref('');
    const guestPhone = ref('');
    const message = ref('');
    const error = ref('');
    const sent = ref(false);
    const saving = ref(false);
    const inbox = ref([]);
    const inboxLoading = ref(false);
    const inboxFilter = ref('unread');
    const unreadCount = ref(0);

    function formatPhone(value) {
      return formatPhoneDisplay(value || '');
    }

    function normalizePhone(value) {
      return toFullPhone(value || '');
    }

    function formatDateTime(value) {
      if (!value) return '—';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '—';
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      const hoursPart = String(date.getHours()).padStart(2, '0');
      const mins = String(date.getMinutes()).padStart(2, '0');
      return `${day}.${month}.${year} ${hoursPart}:${mins}`;
    }

    function roleLabel(role) {
      return ({
        owner: t('owner'),
        director: t('director'),
        worker: t('worker'),
        guest: t('supportGuest'),
      }[role] || role);
    }

    async function loadInfo() {
      try {
        const { data } = await window.axios.get('/support');
        phone.value = data.phone || phone.value;
        phoneTel.value = data.phone_tel || phoneTel.value;
        hours.value = data.hours || hours.value;
      } catch (e) {
        /* keep defaults */
      }
    }

    async function loadInbox() {
      if (!isOwner.value) return;
      inboxLoading.value = true;
      try {
        const { data } = await window.axios.get('/owner/support-messages', {
          params: { status: inboxFilter.value },
        });
        inbox.value = data.messages || [];
        unreadCount.value = data.unread_count || 0;
      } finally {
        inboxLoading.value = false;
      }
    }

    async function submit() {
      error.value = '';
      sent.value = false;
      saving.value = true;
      try {
        const payload = isAuthenticated.value
          ? { message: message.value }
          : { name: guestName.value, phone: guestPhone.value, message: message.value };
        await window.axios.post('/support/messages', payload);
        message.value = '';
        if (!isAuthenticated.value) {
          guestName.value = '';
          guestPhone.value = '';
        }
        sent.value = true;
        if (isOwner.value) await loadInbox();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      } finally {
        saving.value = false;
      }
    }

    async function markRead(item) {
      await window.axios.post(`/owner/support-messages/${item.id}/read`);
      await loadInbox();
    }

    onMounted(async () => {
      await loadInfo();
      await loadInbox();
    });

    return {
      isAuthenticated,
      isOwner,
      inCrmShell,
      phone,
      phoneTel,
      hours,
      guestName,
      guestPhone,
      message,
      error,
      sent,
      saving,
      inbox,
      inboxLoading,
      inboxFilter,
      unreadCount,
      formatPhone,
      normalizePhone,
      formatDateTime,
      roleLabel,
      submit,
      markRead,
      loadInbox,
    };
  },
};
</script>
