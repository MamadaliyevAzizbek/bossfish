<template>
  <div class="owner-venger-staff venger-readable">
    <OwnerVengerNav />

    <div class="page-head">
      <div>
        <h1>{{ $t('vengerStaff') }}</h1>
        <p>{{ $t('ownerVengerStaffPageHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addVengerStaff') }}</button>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <div v-else class="panel">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>{{ $t('name') }}</th>
              <th>{{ $t('ownerLoginLabel') }}</th>
              <th>{{ $t('password') }}</th>
              <th>{{ $t('vengerClientsTitle') }}</th>
              <th>{{ $t('vengerPondsTabOpen') }} / {{ $t('vengerPondsTabDone') }}</th>
              <th>{{ $t('status') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in staff" :key="item.id">
              <td><strong>{{ item.name }}</strong></td>
              <td class="mono">{{ item.email }}</td>
              <td class="mono">
                <span v-if="visiblePass[item.id]">{{ item.password || '—' }}</span>
                <span v-else>••••••</span>
                <button class="linkish" type="button" @click="togglePass(item.id)">
                  {{ visiblePass[item.id] ? $t('hide') : $t('show') }}
                </button>
              </td>
              <td>{{ number(item.clients_count) }}</td>
              <td>{{ number(item.open_count) }} / {{ number(item.final_count) }}</td>
              <td>
                <span class="badge" :class="item.is_active ? 'active' : 'inactive'">
                  {{ item.is_active ? $t('active') : $t('inactive') }}
                </span>
              </td>
              <td class="staff-actions">
                <button class="btn btn-outline btn-sm" type="button" @click="openEdit(item)">{{ $t('edit') }}</button>
                <button class="btn btn-outline btn-sm" type="button" @click="toggleActive(item)">
                  {{ item.is_active ? $t('deactivate') : $t('activate') }}
                </button>
                <button class="btn btn-danger-outline btn-sm" type="button" @click="remove(item)">
                  {{ $t('delete') }}
                </button>
              </td>
            </tr>
            <tr v-if="!staff.length">
              <td colspan="7" class="empty">{{ $t('noVengerStaff') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="modal" class="modal-backdrop" @click.self="modal = false">
      <div class="modal">
        <h2 class="modal-title">{{ form.id ? $t('editVengerStaff') : $t('addVengerStaff') }}</h2>
        <form @submit.prevent="save" class="form-grid one-col">
          <div class="field"><label>{{ $t('name') }}</label><input v-model="form.name" required /></div>
          <div class="field"><label>{{ $t('ownerLoginLabel') }}</label><input v-model="form.email" type="email" required /></div>
          <div class="field">
            <label>{{ form.id ? $t('newPassword') : $t('password') }}</label>
            <input
              v-model="form.password"
              type="text"
              minlength="4"
              :required="!form.id"
              :placeholder="form.id ? $t('leaveBlankUnchanged') : ''"
            />
          </div>
          <p v-if="formError" class="error">{{ formError }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="modal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import { t } from '../../i18n';

export default {
  components: { OwnerVengerNav },
  setup() {
    const loading = ref(true);
    const error = ref('');
    const formError = ref('');
    const staff = ref([]);
    const visiblePass = reactive({});
    const modal = ref(false);
    const form = reactive({ id: null, name: '', email: '', password: '' });

    function number(v) {
      const n = Number(v || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function togglePass(id) {
      visiblePass[id] = !visiblePass[id];
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get('/owner/venger');
        staff.value = data.staff || [];
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function openCreate() {
      Object.assign(form, { id: null, name: '', email: '', password: '' });
      formError.value = '';
      modal.value = true;
    }

    function openEdit(item) {
      Object.assign(form, { id: item.id, name: item.name, email: item.email || '', password: '' });
      formError.value = '';
      modal.value = true;
    }

    async function save() {
      formError.value = '';
      try {
        const payload = { name: form.name, email: form.email };
        if (form.password) payload.password = form.password;
        if (form.id) await window.axios.put(`/owner/venger/staff/${form.id}`, payload);
        else await window.axios.post('/owner/venger/staff', payload);
        modal.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        formError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    async function toggleActive(item) {
      try {
        await window.axios.put(`/owner/venger/staff/${item.id}`, { is_active: !item.is_active });
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      }
    }

    async function remove(item) {
      if (!window.confirm(t('ownerVengerDeleteStaffConfirm', { name: item.name }))) return;
      try {
        await window.axios.delete(`/owner/venger/staff/${item.id}`);
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      }
    }

    onMounted(load);

    return {
      loading, error, formError, staff, visiblePass, modal, form,
      number, togglePass, openCreate, openEdit, save, toggleActive, remove,
    };
  },
};
</script>

<style scoped>
.staff-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
</style>
