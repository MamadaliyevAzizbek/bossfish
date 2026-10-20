<template>
  <div class="ow venger-readable">
    <div class="page-head">
      <div>
        <h1>{{ $t('workers') }}</h1>
        <p>{{ $t('ownerWorkersHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openCreate"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addWorker') }}</button>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="ow-toolbar panel">
        <input
          v-model="searchQ"
          type="search"
          class="ow-search"
          :placeholder="$t('ownerSearchNamePhonePh')"
          :aria-label="$t('ownerSearchNamePhone')"
        />
        <span class="ow-count">{{ number(filteredWorkers.length) }} / {{ number(workers.length) }}</span>
      </div>

      <div class="ow-list">
        <article
          v-for="worker in filteredWorkers"
          :key="worker.id"
          class="ow-card panel"
          :class="{
            'is-off': !worker.is_active,
            'is-blocked': worker.director?.access_expired || worker.director?.is_active === false,
          }"
        >
          <div class="ow-card__top">
            <div>
              <h3>{{ worker.name }}</h3>
              <p class="mono">{{ worker.phone }}</p>
            </div>
            <div class="ow-card__badges">
              <span class="badge" :class="worker.is_active ? 'active' : 'inactive'">
                {{ worker.is_active ? $t('active') : $t('inactive') }}
              </span>
              <span
                v-if="worker.director?.access_expired"
                class="badge inactive"
              >{{ $t('accessExpired') }}</span>
              <span
                v-else-if="worker.director?.access_days_left != null"
                class="badge"
                :class="worker.director.access_days_left <= 10 ? 'pending' : 'active'"
              >
                {{ $t('daysLeftShort', { days: worker.director.access_days_left }) }}
              </span>
            </div>
          </div>

          <div class="ow-card__meta">
            <div>
              <span>{{ $t('assignedDirector') }}</span>
              <strong>{{ worker.director?.name || '—' }}</strong>
              <small v-if="worker.director?.phone" class="mono">{{ worker.director.phone }}</small>
            </div>
            <div>
              <span>{{ $t('password') }}</span>
              <strong class="mono">
                {{ visiblePass[worker.id] ? (worker.password || '—') : '••••••' }}
              </strong>
              <button class="linkish" type="button" @click="togglePass(worker.id)">
                {{ visiblePass[worker.id] ? $t('hide') : $t('show') }}
              </button>
            </div>
          </div>

          <p v-if="worker.director?.access_expired || worker.director?.is_active === false" class="ow-card__warn">
            {{ $t('workerBlockedByDirector') }}
          </p>

          <div class="ow-card__actions">
            <button class="btn btn-outline btn-sm" type="button" @click="openEdit(worker)">
              {{ $t('edit') }}
            </button>
            <button class="btn btn-outline btn-sm" type="button" @click="toggleActive(worker)">
              {{ worker.is_active ? $t('deactivate') : $t('activate') }}
            </button>
          </div>
        </article>

        <div v-if="!filteredWorkers.length" class="panel empty">
          {{ searchQ.trim() ? $t('ownerSearchEmpty') : $t('noWorkers') }}
        </div>
      </div>
    </template>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal">
        <h2 class="modal-title">{{ editingId ? $t('editWorker') : $t('newWorker') }}</h2>
        <form class="form-grid one-col" @submit.prevent="save">
          <div class="field">
            <label>{{ $t('name') }}</label>
            <input v-model="form.name" required maxlength="255" />
          </div>
          <div class="field">
            <label>{{ $t('phoneLogin') }}</label>
            <PhoneInput v-model="form.phone" required />
          </div>
          <div class="field">
            <label>{{ editingId ? $t('newPassword') : $t('shortPassword') }}</label>
            <input
              v-model="form.password"
              type="text"
              :required="!editingId"
              minlength="4"
              :placeholder="editingId ? $t('leaveBlankUnchanged') : ''"
            />
          </div>
          <div class="field">
            <label>{{ $t('assignedDirector') }}</label>
            <select v-model="form.director_id" required>
              <option value="">{{ $t('selectDirector') }}</option>
              <option v-for="director in directors" :key="director.id" :value="director.id">
                {{ director.name }} · {{ director.phone }}
              </option>
            </select>
            <small class="field-hint">{{ $t('workerDirectorRequiredHint') }}</small>
          </div>
          <div v-if="editingId" class="field">
            <label>{{ $t('status') }}</label>
            <select v-model="form.is_active">
              <option :value="true">{{ $t('active') }}</option>
              <option :value="false">{{ $t('inactive') }}</option>
            </select>
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit" :disabled="!canSave">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="showForm = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import PhoneInput from '../../components/PhoneInput.vue';
import { isCompletePhone, toFullPhone } from '../../utils/phone';
import { t } from '../../i18n';

function matchesNameOrPhone(item, q) {
  const raw = String(q || '').trim().toLowerCase();
  if (!raw) return true;
  const digits = raw.replace(/\D+/g, '');
  const name = String(item.name || '').toLowerCase();
  const phone = String(item.phone || '').toLowerCase();
  const directorName = String(item.director?.name || '').toLowerCase();
  if (name.includes(raw) || phone.includes(raw) || directorName.includes(raw)) return true;
  if (digits && phone.replace(/\D+/g, '').includes(digits)) return true;
  return false;
}

export default {
  components: { PhoneInput },
  setup() {
    const workers = ref([]);
    const directors = ref([]);
    const loading = ref(true);
    const showForm = ref(false);
    const editingId = ref(null);
    const error = ref('');
    const searchQ = ref('');
    const visiblePass = reactive({});
    const form = reactive({
      name: '',
      phone: toFullPhone(''),
      password: '',
      director_id: '',
      is_active: true,
    });

    const filteredWorkers = computed(() => workers.value.filter((w) => matchesNameOrPhone(w, searchQ.value)));

    const canSave = computed(() => (
      form.name
      && isCompletePhone(form.phone)
      && form.director_id
      && (editingId.value || form.password.length >= 4)
    ));

    function number(value) {
      const n = Number(value || 0);
      return Number.isFinite(n) ? n.toLocaleString() : '0';
    }

    function togglePass(id) {
      visiblePass[id] = !visiblePass[id];
    }

    async function load() {
      loading.value = true;
      try {
        const [workersResponse, directorsResponse] = await Promise.all([
          window.axios.get('/owner/workers'),
          window.axios.get('/owner/directors'),
        ]);
        workers.value = workersResponse.data;
        directors.value = directorsResponse.data;
      } finally {
        loading.value = false;
      }
    }

    function openCreate() {
      editingId.value = null;
      Object.assign(form, {
        name: '',
        phone: toFullPhone(''),
        password: '',
        director_id: '',
        is_active: true,
      });
      error.value = '';
      showForm.value = true;
    }

    function openEdit(worker) {
      editingId.value = worker.id;
      Object.assign(form, {
        name: worker.name,
        phone: worker.phone,
        password: '',
        director_id: worker.director_id || worker.director?.id || '',
        is_active: worker.is_active,
      });
      error.value = '';
      showForm.value = true;
    }

    async function save() {
      error.value = '';
      if (!isCompletePhone(form.phone)) {
        error.value = t('phoneIncomplete');
        return;
      }
      if (!form.director_id) {
        error.value = t('selectDirectorRequired');
        return;
      }

      try {
        const payload = {
          name: form.name,
          phone: form.phone,
          director_id: Number(form.director_id),
        };
        if (form.password) payload.password = form.password;
        if (editingId.value) payload.is_active = form.is_active;

        if (editingId.value) {
          await window.axios.put(`/owner/workers/${editingId.value}`, payload);
        } else {
          await window.axios.post('/owner/workers', payload);
        }

        showForm.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    async function toggleActive(worker) {
      try {
        await window.axios.put(`/owner/workers/${worker.id}`, { is_active: !worker.is_active });
        await load();
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      }
    }

    onMounted(load);

    return {
      workers,
      directors,
      filteredWorkers,
      canSave,
      loading,
      showForm,
      editingId,
      form,
      error,
      searchQ,
      visiblePass,
      number,
      togglePass,
      openCreate,
      openEdit,
      save,
      toggleActive,
    };
  },
};
</script>

<style scoped>
.ow-toolbar {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin-bottom: 0.85rem;
  padding: 0.75rem 1rem;
}
.ow-search {
  flex: 1;
  min-height: 42px;
  border: 1px solid rgba(20, 80, 50, 0.2);
  border-radius: 10px;
  padding: 0.5rem 0.75rem;
  font-size: 16px;
  background: #fff;
  color: #143226;
}
.ow-count {
  color: #3a5548;
  font-size: 0.88rem;
  white-space: nowrap;
}
.ow-list {
  display: grid;
  gap: 0.75rem;
}
.ow-card {
  padding: 1rem 1.05rem;
}
.ow-card.is-off {
  background: #f8fafc;
  border-style: dashed;
}
.ow-card.is-blocked {
  border-color: #f59e0b;
  background: #fffbeb;
}
.ow-card__top {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.7rem;
}
.ow-card__top h3 {
  margin: 0;
  font-size: 1.05rem;
  color: #0f2a1c;
}
.ow-card__top p {
  margin: 0.2rem 0 0;
  color: #3a5548;
}
.ow-card__badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  justify-content: flex-end;
}
.ow-card__meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.7rem;
  margin-bottom: 0.7rem;
}
.ow-card__meta span {
  display: block;
  color: #3a5548;
  font-size: 0.75rem;
  margin-bottom: 0.15rem;
}
.ow-card__meta strong {
  color: #143226;
  margin-right: 0.35rem;
}
.ow-card__meta small {
  display: block;
  color: #5b7266;
  margin-top: 0.15rem;
}
.ow-card__warn {
  margin: 0 0 0.7rem;
  color: #9a3412;
  font-size: 0.88rem;
}
.ow-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}
@media (max-width: 700px) {
  .ow-card__meta { grid-template-columns: 1fr; }
}
</style>
