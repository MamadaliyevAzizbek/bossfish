<template>
  <div class="sec venger-readable">
    <div class="page-head">
      <div>
        <h1>{{ $t('security') }}</h1>
        <p>{{ $t('securityDescription') }}</p>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="sec-kpi sec-kpi--creds">
        <div class="sec-kpi__card">
          <span>{{ $t('directors') }}</span>
          <strong>{{ number(directors.length) }}</strong>
        </div>
        <div class="sec-kpi__card">
          <span>{{ $t('workers') }}</span>
          <strong>{{ number(workers.length) }}</strong>
        </div>
        <div class="sec-kpi__card">
          <span>{{ $t('securityInactive') }}</span>
          <strong>{{ number(inactiveDirectors) }}</strong>
        </div>
      </div>

      <div class="sec-tabs" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="sec-tabs__btn"
          :class="{ 'is-active': activeTab === tab.id }"
          role="tab"
          :aria-selected="activeTab === tab.id"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
          <em v-if="tab.count != null">{{ tab.count }}</em>
        </button>
      </div>

      <!-- BOSS -->
      <section v-if="activeTab === 'boss'" class="panel sec-panel">
        <div class="sec-boss">
          <div class="sec-boss__main">
            <div class="sec-avatar" aria-hidden="true">B</div>
            <div>
              <h2>{{ owner.name || $t('myCabinet') }}</h2>
              <p>{{ $t('myCabinet') }}</p>
            </div>
          </div>
          <button class="btn btn-primary" type="button" @click="openOwnerEdit">{{ $t('edit') }}</button>
        </div>
        <div class="sec-cred-grid">
          <div class="sec-cred">
            <span>{{ $t('loginAddress') }}</span>
            <strong class="mono">{{ owner.login_url }}</strong>
          </div>
          <div class="sec-cred">
            <span>{{ $t('ownerLoginLabel') }}</span>
            <strong class="mono">{{ owner.email }}</strong>
          </div>
          <div class="sec-cred">
            <span>{{ $t('password') }}</span>
            <div class="sec-cred__pass">
              <strong class="mono">{{ showOwnerPass ? (owner.password || '—') : '••••••••' }}</strong>
              <button class="btn btn-outline btn-sm" type="button" @click="showOwnerPass = !showOwnerPass">
                {{ showOwnerPass ? $t('hide') : $t('show') }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Directors -->
      <section v-else-if="activeTab === 'directors'" class="panel sec-panel">
        <div class="sec-toolbar">
          <div class="sec-search">
            <input
              v-model="searchQ"
              type="search"
              :placeholder="$t('ownerSearchNamePhonePh')"
              :aria-label="$t('ownerSearchNamePhone')"
            />
          </div>
          <div class="sec-filters" role="group">
            <button
              v-for="f in directorFilters"
              :key="f.id"
              type="button"
              class="sec-filters__btn"
              :class="{ 'is-active': directorFilter === f.id }"
              @click="directorFilter = f.id"
            >
              {{ f.label }}
            </button>
          </div>
          <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-clients' }">
            {{ $t('ownerClientsNav') }} <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </router-link>
        </div>

        <p v-if="searchQ.trim()" class="field-hint">
          {{ $t('ownerSearchResultCount', { n: filteredDirectors.length, total: directors.length }) }}
        </p>

        <div class="sec-list">
          <article
            v-for="d in filteredDirectors"
            :key="d.id"
            class="sec-card"
            :class="{ 'is-off': d.is_active === false }"
          >
            <div class="sec-card__top">
              <div>
                <h3>{{ d.name }}</h3>
                <p class="mono">{{ d.phone }}</p>
              </div>
              <div class="sec-card__badges">
                <span class="badge" :class="d.is_active ? 'active' : 'inactive'">
                  {{ d.is_active ? $t('active') : $t('inactive') }}
                </span>
              </div>
            </div>

            <div class="sec-card__meta">
              <div>
                <span>{{ $t('password') }}</span>
                <strong class="mono">
                  {{ visiblePass[d.id] ? (d.password || '—') : '••••••' }}
                </strong>
                <button class="linkish" type="button" @click="togglePass(d.id)">
                  {{ visiblePass[d.id] ? $t('hide') : $t('show') }}
                </button>
              </div>
              <div>
                <span>{{ $t('farms') }}</span>
                <strong>{{ formatDirectorAssignments(d) }}</strong>
              </div>
            </div>

            <div class="sec-card__actions">
              <button class="btn btn-outline btn-sm" type="button" @click="openDirectorEdit(d)">
                {{ $t('edit') }}
              </button>
              <button class="btn btn-outline btn-sm" type="button" @click="toggleDirectorActive(d)">
                {{ d.is_active ? $t('deactivate') : $t('activate') }}
              </button>
            </div>
          </article>

          <div v-if="!filteredDirectors.length" class="empty">
            {{ searchQ.trim() || directorFilter !== 'all' ? $t('ownerSearchEmpty') : $t('noDirectors') }}
          </div>
        </div>
      </section>

      <!-- Workers -->
      <section v-else-if="activeTab === 'workers'" class="panel sec-panel">
        <div class="sec-toolbar">
          <div class="sec-search">
            <input
              v-model="workerSearchQ"
              type="search"
              :placeholder="$t('ownerSearchNamePhonePh')"
              :aria-label="$t('ownerSearchNamePhone')"
            />
          </div>
          <router-link class="btn btn-primary btn-sm" :to="{ name: 'owner-workers' }">
            {{ $t('workers') }} <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </router-link>
        </div>

        <div class="sec-list">
          <article
            v-for="w in filteredWorkers"
            :key="w.id"
            class="sec-card"
            :class="{ 'is-off': w.is_active === false }"
          >
            <div class="sec-card__top">
              <div>
                <h3>{{ w.name }}</h3>
                <p class="mono">{{ w.phone }}</p>
              </div>
              <span class="badge" :class="w.is_active ? 'active' : 'inactive'">
                {{ w.is_active ? $t('active') : $t('inactive') }}
              </span>
            </div>
            <div class="sec-card__meta">
              <div>
                <span>{{ $t('password') }}</span>
                <strong class="mono">
                  {{ visiblePass[`w-${w.id}`] ? (w.password || '—') : '••••••' }}
                </strong>
                <button class="linkish" type="button" @click="togglePass(`w-${w.id}`)">
                  {{ visiblePass[`w-${w.id}`] ? $t('hide') : $t('show') }}
                </button>
              </div>
              <div>
                <span>{{ $t('farms') }}</span>
                <strong>{{ formatWorkerFarms(w) }}</strong>
              </div>
            </div>
          </article>

          <div v-if="!filteredWorkers.length" class="empty">
            {{ workerSearchQ.trim() ? $t('ownerSearchEmpty') : $t('noWorkers') }}
          </div>
        </div>
      </section>

      <!-- Venger shortcut -->
      <section v-else class="panel sec-panel sec-venger">
        <div>
          <h2>{{ $t('ownerVengerSection') }}</h2>
          <p>{{ $t('securityVengerHint') }}</p>
        </div>
        <router-link class="btn btn-primary" :to="{ name: 'owner-venger-staff' }">
          {{ $t('vengerStaff') }} <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
        </router-link>
      </section>
    </template>

    <div v-if="ownerModal" class="modal-backdrop" @click.self="ownerModal = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('editOwner') }}</h2>
        <form class="form-grid one-col" @submit.prevent="saveOwner">
          <div class="field"><label>{{ $t('name') }}</label><input v-model="ownerForm.name" required /></div>
          <div class="field"><label>{{ $t('ownerLoginLabel') }}</label><input v-model="ownerForm.email" type="email" required /></div>
          <div class="field">
            <label>{{ $t('newPassword') }}</label>
            <input v-model="ownerForm.password" type="text" minlength="4" :placeholder="$t('leaveBlankUnchanged')" />
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="ownerModal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="directorModal" class="modal-backdrop" @click.self="directorModal = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('editDirector') }}</h2>
        <form class="form-grid one-col" @submit.prevent="saveDirector">
          <div class="field"><label>{{ $t('name') }}</label><input v-model="directorForm.name" required /></div>
          <div class="field">
            <label>{{ $t('phoneLogin') }}</label>
            <PhoneInput v-model="directorForm.phone" required />
          </div>
          <div class="field">
            <label>{{ $t('newPassword') }}</label>
            <input v-model="directorForm.password" type="text" minlength="4" :placeholder="$t('leaveBlankUnchanged')" />
          </div>

          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="directorModal = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import PhoneInput from '../../components/PhoneInput.vue';
import { isCompletePhone } from '../../utils/phone';
import auth from '../../auth';
import { t } from '../../i18n';

function matchesNameOrPhone(item, q) {
  const raw = String(q || '').trim().toLowerCase();
  if (!raw) return true;
  const digits = raw.replace(/\D+/g, '');
  const name = String(item.name || '').toLowerCase();
  const phone = String(item.phone || '').toLowerCase();
  if (name.includes(raw) || phone.includes(raw)) return true;
  if (digits && phone.replace(/\D+/g, '').includes(digits)) return true;
  return false;
}

export default {
  components: { PhoneInput },
  setup() {
    const loading = ref(true);
    const owner = ref({});
    const directors = ref([]);
    const workers = ref([]);
    const searchQ = ref('');
    const workerSearchQ = ref('');
    const directorFilter = ref('all');
    const activeTab = ref('directors');
    const visiblePass = reactive({});
    const showOwnerPass = ref(false);
    const ownerModal = ref(false);
    const directorModal = ref(false);
    const error = ref('');
    const ownerForm = reactive({ name: '', email: '', password: '' });
    const directorForm = reactive({
      id: null,
      name: '',
      phone: '',
      password: '',
    });

    const inactiveDirectors = computed(() => directors.value.filter((d) => d.is_active === false).length);

    const tabs = computed(() => [
      { id: 'boss', label: t('myCabinet'), count: null },
      { id: 'directors', label: t('directors'), count: directors.value.length },
      { id: 'workers', label: t('workers'), count: workers.value.length },
      { id: 'venger', label: t('ownerVengerSection'), count: null },
    ]);

    const directorFilters = computed(() => [
      { id: 'all', label: t('all') },
      { id: 'inactive', label: t('inactive') },
    ]);

    const filteredDirectors = computed(() => directors.value.filter((d) => {
      if (!matchesNameOrPhone(d, searchQ.value)) return false;
      if (directorFilter.value === 'inactive') return d.is_active === false;
      return true;
    }));

    const filteredWorkers = computed(() => workers.value.filter((w) => matchesNameOrPhone(w, workerSearchQ.value)));

    function number(value) {
      const n = Number(value || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function formatDirectorAssignments(d) {
      const farms = (d.directed_farms || []).map((f) => f.name);
      const ponds = (d.directed_ponds || []).map((p) => p.name);
      const parts = [...farms, ...ponds.filter((name) => !farms.includes(name))];
      return parts.length ? parts.join(', ') : '—';
    }

    function formatWorkerFarms(w) {
      const farms = (w.assigned_farms || []).map((f) => f.name);
      return farms.length ? farms.join(', ') : '—';
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/security');
        owner.value = data.owner;
        directors.value = data.directors || [];
        workers.value = data.workers || [];
      } finally {
        loading.value = false;
      }
    }

    function togglePass(id) {
      visiblePass[id] = !visiblePass[id];
    }

    function openOwnerEdit() {
      Object.assign(ownerForm, {
        name: owner.value.name || '',
        email: owner.value.email || '',
        password: '',
      });
      error.value = '';
      ownerModal.value = true;
    }

    function openDirectorEdit(d) {
      Object.assign(directorForm, {
        id: d.id,
        name: d.name,
        phone: d.phone || '+998',
        password: '',
      });
      error.value = '';
      directorModal.value = true;
    }

    async function saveOwner() {
      error.value = '';
      try {
        const payload = {
          name: ownerForm.name,
          email: ownerForm.email,
        };
        if (ownerForm.password) payload.password = ownerForm.password;

        const { data } = await window.axios.put('/owner/security/owner', payload);
        owner.value = data;
        if (auth.state.user) {
          auth.state.user.name = data.name;
          auth.state.user.email = data.email;
        }
        ownerModal.value = false;
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    async function saveDirector() {
      error.value = '';
      if (!isCompletePhone(directorForm.phone)) {
        error.value = t('phoneIncompleteFull');
        return;
      }
      try {
        const payload = {
          name: directorForm.name,
          phone: directorForm.phone,
        };
        if (directorForm.password) payload.password = directorForm.password;

        await window.axios.put(`/owner/directors/${directorForm.id}`, payload);
        directorModal.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    async function toggleDirectorActive(d) {
      try {
        await window.axios.put(`/owner/directors/${d.id}`, { is_active: !d.is_active });
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      }
    }

    onMounted(load);

    return {
      loading,
      owner,
      directors,
      workers,
      filteredDirectors,
      filteredWorkers,
      searchQ,
      workerSearchQ,
      directorFilter,
      directorFilters,
      activeTab,
      tabs,
      inactiveDirectors,
      visiblePass,
      showOwnerPass,
      ownerModal,
      directorModal,
      ownerForm,
      directorForm,
      error,
      number,
      togglePass,
      openOwnerEdit,
      openDirectorEdit,
      saveOwner,
      saveDirector,
      toggleDirectorActive,
      formatDirectorAssignments,
      formatWorkerFarms,
    };
  },
};
</script>

<style scoped>
.sec-kpi {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.7rem;
  margin-bottom: 0.85rem;
}
.sec-kpi--creds {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.sec-kpi__card {
  background: #ffffff;
  border: 1px solid rgba(20, 80, 50, 0.14);
  border-radius: 14px;
  padding: 0.85rem 1rem;
  box-shadow: 0 8px 20px rgba(8, 40, 24, 0.12);
}
.sec-kpi__card span {
  display: block;
  color: #3a5548;
  font-size: 0.82rem;
  margin-bottom: 0.2rem;
}
.sec-kpi__card strong {
  font-size: 1.45rem;
  color: #0f2a1c;
}
.sec-kpi__card--warn strong { color: #b45309; }

.sec-alert {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  margin-bottom: 0.85rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: #fff7ed;
  border: 1px solid #fdba74;
  color: #9a3412;
  font-size: 0.9rem;
}

.sec-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
  padding: 0.25rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(20, 80, 50, 0.1);
  width: fit-content;
  max-width: 100%;
}
.sec-tabs__btn {
  border: 0;
  background: transparent;
  color: #3d5a4a;
  font: inherit;
  font-size: 0.9rem;
  font-weight: 650;
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.sec-tabs__btn em {
  font-style: normal;
  font-size: 0.75rem;
  background: rgba(10, 107, 64, 0.12);
  color: #0a6b40;
  border-radius: 999px;
  padding: 0.05rem 0.4rem;
}
.sec-tabs__btn.is-active {
  background: #0a6b40;
  color: #fff;
}
.sec-tabs__btn.is-active em {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

.sec-panel { margin-top: 0; }
.sec-boss {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
  margin-bottom: 1rem;
}
.sec-boss__main {
  display: flex;
  gap: 0.85rem;
  align-items: center;
}
.sec-boss h2 { margin: 0; font-size: 1.15rem; }
.sec-boss p { margin: 0.15rem 0 0; color: #5b7266; font-size: 0.88rem; }
.sec-avatar {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #053825, #0a6b40);
  color: #fff;
  font-weight: 800;
}

.sec-cred-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
}
.sec-cred {
  background: #f4faf6;
  border-radius: 12px;
  padding: 0.75rem 0.85rem;
}
.sec-cred span {
  display: block;
  color: #5b7266;
  font-size: 0.78rem;
  margin-bottom: 0.25rem;
}
.sec-cred strong { word-break: break-all; }
.sec-cred__pass {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.sec-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
  margin-bottom: 0.85rem;
}
.sec-search {
  flex: 1 1 220px;
  max-width: 360px;
}
.sec-search input {
  width: 100%;
  min-height: 42px;
  border: 1px solid rgba(20, 80, 50, 0.2);
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
  font-size: 16px;
  background: #fff;
}
.sec-filters {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  padding: 0.2rem;
  border-radius: 999px;
  background: #eef5f0;
}
.sec-filters__btn {
  border: 0;
  background: transparent;
  color: #3d5a4a;
  font: inherit;
  font-size: 0.8rem;
  font-weight: 650;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  cursor: pointer;
}
.sec-filters__btn.is-active {
  background: #0a6b40;
  color: #fff;
}

.sec-list {
  display: grid;
  gap: 0.7rem;
}
.sec-card {
  border: 1px solid rgba(20, 80, 50, 0.14);
  border-radius: 14px;
  padding: 0.9rem 1rem;
  background: #fff;
  color: #143226;
}
.sec-card.is-warn { border-color: #f59e0b; background: #fffbeb; }
.sec-card.is-danger { border-color: #ef4444; background: #fef2f2; }
.sec-card.is-off {
  background: #f8fafc;
  border-style: dashed;
}
.sec-card.is-off .sec-card__top h3,
.sec-card.is-off .sec-card__meta strong {
  color: #334155;
}
.sec-card__top {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
  margin-bottom: 0.65rem;
}
.sec-card__top h3 { margin: 0; font-size: 1.02rem; color: #0f2a1c; }
.sec-card__top p { margin: 0.2rem 0 0; color: #3a5548; font-size: 0.88rem; }
.sec-card__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  justify-content: flex-end;
}
.sec-card__meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
  margin-bottom: 0.75rem;
}
.sec-card__meta span {
  display: block;
  color: #5b7266;
  font-size: 0.75rem;
  margin-bottom: 0.15rem;
}
.sec-card__meta strong { font-size: 0.92rem; margin-right: 0.35rem; }
.sec-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.sec-venger {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}
.sec-venger h2 { margin: 0 0 0.3rem; }
.sec-venger p { margin: 0; color: #5b7266; }

.sec-access-mode {
  display: flex;
  gap: 0.3rem;
  margin-bottom: 0.55rem;
  padding: 0.2rem;
  border-radius: 10px;
  background: #eef5f0;
}
.sec-access-mode__btn {
  flex: 1;
  border: 0;
  background: transparent;
  color: #3d5a4a;
  font: inherit;
  font-size: 0.82rem;
  font-weight: 650;
  padding: 0.45rem 0.5rem;
  border-radius: 8px;
  cursor: pointer;
}
.sec-access-mode__btn.is-active {
  background: #0a6b40;
  color: #fff;
}

@media (max-width: 900px) {
  .sec-kpi { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sec-cred-grid { grid-template-columns: 1fr; }
  .sec-card__meta { grid-template-columns: 1fr; }
  .sec-venger { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 560px) {
  .sec-kpi { grid-template-columns: 1fr 1fr; }
  .sec-boss { flex-direction: column; align-items: flex-start; }
}
</style>
