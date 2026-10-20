<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ $t('farms') }}</h1>
        <p>{{ $t('ownerFarmsHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openForm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addFarm') }}</button>
    </div>

    <p v-if="directorsLoaded && !directors.length" class="panel muted" style="margin-bottom: 1rem">
      {{ $t('noDirectorsForFarm') }}
    </p>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="loadError" class="panel empty">{{ loadError }}</div>

    <div v-else class="panel">
      <table>
        <thead>
          <tr>
            <th>{{ $t('name') }}</th>
            <th>{{ $t('region') }}</th>
            <th>{{ $t('director') }}</th>
            <th>{{ $t('ponds') }}</th>
            <th>{{ $t('status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="farm in farms" :key="farm.id">
            <td>{{ farm.name }}</td>
            <td>{{ farm.region }}</td>
            <td>{{ farm.director?.name || '—' }}</td>
            <td>{{ farm.ponds_count || 0 }}</td>
            <td><span class="badge" :class="farm.status">{{ farm.status }}</span></td>
            <td class="table-actions">
              <button class="btn btn-primary btn-sm" type="button" @click="manageFarm(farm)">
                {{ $t('manageFarm') }}
              </button>
            </td>
          </tr>
          <tr v-if="!farms.length">
            <td colspan="6" class="empty">{{ $t('noFarmYet') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal modal-wide">
        <h2 class="modal-title">{{ $t('newFarm') }}</h2>
        <p v-if="!directors.length" class="muted">{{ $t('noDirectorsForFarm') }}</p>
        <form v-else @submit.prevent="create" class="form-grid">
          <div class="field" style="grid-column: 1 / -1">
            <label>{{ $t('name') }}</label>
            <input v-model="form.name" required />
          </div>
          <div class="field">
            <label>{{ $t('region') }}</label>
            <select v-model="form.region" required>
              <option disabled value="">{{ $t('select') }}</option>
              <option v-for="r in regions" :key="r.name" :value="r.name">{{ r.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('directorRequired') }}</label>
            <select v-model="form.director_id" required>
              <option disabled value="">{{ $t('select') }}</option>
              <option v-for="d in directors" :key="d.id" :value="d.id">{{ d.name }} ({{ d.phone }})</option>
            </select>
          </div>
          <p v-if="error" class="error" style="grid-column: 1 / -1">{{ error }}</p>
          <div class="actions" style="grid-column: 1 / -1">
            <button class="btn btn-primary" type="submit" :disabled="!form.director_id">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="showForm = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { setDirectorFarmId } from '../../utils/directorFarm';
import { normalizeRegions } from '../../utils/regionCoordinates';
import { t } from '../../i18n';

export default {
  setup() {
    const router = useRouter();
    const farms = ref([]);
    const directors = ref([]);
    const regions = ref([]);
    const loading = ref(true);
    const loadError = ref('');
    const directorsLoaded = ref(false);
    const showForm = ref(false);
    const error = ref('');
    const form = reactive({
      name: '',
      region: '',
      director_id: '',
    });

    function manageFarm(farm) {
      setDirectorFarmId(farm.id);
      router.push({ name: 'director-farm-hub' });
    }

    async function loadDirectors() {
      const { data } = await window.axios.get('/owner/directors');
      directors.value = data;
      directorsLoaded.value = true;
    }

    async function load() {
      loading.value = true;
      loadError.value = '';
      try {
        const [farmsResponse] = await Promise.all([
          window.axios.get('/owner/farms'),
          loadDirectors(),
        ]);
        farms.value = farmsResponse.data;
      } catch (e) {
        loadError.value = e.response?.data?.message || t('loadFailed');
      } finally {
        loading.value = false;
      }
    }

    async function loadFormData() {
      const { data } = await window.axios.get('/owner/regions');
      regions.value = normalizeRegions(data);
    }

    async function openForm() {
      Object.assign(form, { name: '', region: '', director_id: '' });
      error.value = '';
      await loadFormData();
      showForm.value = true;
    }

    async function create() {
      if (!form.director_id) {
        error.value = t('directorSelectionRequired');
        return;
      }
      error.value = '';
      try {
        await window.axios.post('/owner/farms', form);
        showForm.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    onMounted(load);

    return {
      farms,
      directors,
      regions,
      loading,
      loadError,
      directorsLoaded,
      showForm,
      form,
      error,
      openForm,
      create,
      manageFarm,
    };
  },
};
</script>
