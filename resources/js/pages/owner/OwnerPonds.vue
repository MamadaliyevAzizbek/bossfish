<template>
  <div>
    <div class="page-head">
      <div>
        <h1>{{ $t('ponds') }}</h1>
        <p>{{ $t('ownerPondFarmHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openForm"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> {{ $t('addPond') }}</button>
    </div>

    <p v-if="farmsLoaded && !farms.length" class="panel muted" style="margin-bottom: 1rem">
      {{ $t('noFarmForPond') }}
    </p>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="loadError" class="panel empty">{{ loadError }}</div>

    <div v-else class="panel">
      <table>
        <thead>
          <tr>
            <th>{{ $t('name') }}</th>
            <th>{{ $t('region') }}</th>
            <th>{{ $t('district') }}</th>
            <th>{{ $t('area') }}</th>
            <th>{{ $t('farm') }}</th>
            <th>{{ $t('director') }}</th>
            <th>{{ $t('status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in ponds" :key="p.id">
            <td>{{ p.name }}</td>
            <td>{{ p.region }}</td>
            <td>{{ p.district || '—' }}</td>
            <td>{{ p.area_m2 }} {{ $t('hectare') }}</td>
            <td>{{ p.farm?.name || '—' }}</td>
            <td>{{ p.director?.name || '—' }}</td>
            <td><span class="badge" :class="p.status">{{ p.status }}</span></td>
            <td class="table-actions">
              <button
                v-if="p.farm_id"
                class="btn btn-primary btn-sm"
                type="button"
                @click="manageFarm(p)"
              >
                {{ $t('manageFarm') }}
              </button>
              <span v-else class="muted">—</span>
              <button class="btn btn-outline btn-sm" type="button" @click="remove(p)">{{ $t('delete') }}</button>
            </td>
          </tr>
          <tr v-if="!ponds.length">
            <td colspan="8" class="empty">{{ $t('noPonds') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal modal-wide">
        <h2 class="modal-title">{{ $t('newPond') }}</h2>
        <p v-if="!farms.length" class="muted">{{ $t('noFarmForPond') }}</p>
        <form v-else @submit.prevent="create" class="form-grid">
          <div class="field" style="grid-column: 1 / -1">
            <label>{{ $t('farmRequired') }} <span class="req">*</span></label>
            <select v-model="form.farm_id" required @change="onFarmChange">
              <option disabled value="">{{ $t('selectFarm') }}</option>
              <option v-for="farm in farms" :key="farm.id" :value="farm.id">
                {{ farm.name }} ({{ farm.region }}) — {{ farm.director?.name || '—' }}
              </option>
            </select>
          </div>
          <div class="field" style="grid-column: 1 / -1">
            <label>{{ $t('pondName') }} <span class="req">*</span></label>
            <input v-model="form.name" required :placeholder="$t('pondExample')" />
          </div>
          <div class="field" style="grid-column: 1 / -1">
            <RegionDistrictPicker
              v-model:region="form.region"
              v-model:district="form.district"
              :region-label="$t('region')"
              :district-label="$t('district')"
            />
          </div>
          <div class="field">
            <label>{{ $t('area') }} ({{ $t('hectare') }}) <span class="req">*</span></label>
            <FormattedNumberInput v-model="form.area_m2" :max-decimals="2" required />
            <small class="field-hint">{{ $t('areaHectareHint') }}</small>
          </div>
          <p v-if="error" class="error" style="grid-column: 1 / -1">{{ error }}</p>
          <div class="actions" style="grid-column: 1 / -1">
            <button class="btn btn-primary" type="submit" :disabled="!form.farm_id">{{ $t('save') }}</button>
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
import RegionDistrictPicker from '../../components/RegionDistrictPicker.vue';
import { setDirectorFarmId } from '../../utils/directorFarm';
import { t } from '../../i18n';

export default {
  components: { RegionDistrictPicker },
  setup() {
    const router = useRouter();

    function manageFarm(pond) {
      setDirectorFarmId(pond.farm_id);
      router.push({ name: 'director-farm-hub' });
    }
    const ponds = ref([]);
    const farms = ref([]);
    const loading = ref(true);
    const loadError = ref('');
    const farmsLoaded = ref(false);
    const showForm = ref(false);
    const error = ref('');
    const form = reactive({
      farm_id: '',
      name: '',
      region: '',
      district: '',
      area_m2: '',
    });

    async function loadFarms() {
      const { data } = await window.axios.get('/owner/farms');
      farms.value = data;
      farmsLoaded.value = true;
    }

    async function load() {
      loading.value = true;
      loadError.value = '';
      try {
        const [pondsResponse] = await Promise.all([
          window.axios.get('/owner/ponds'),
          loadFarms(),
        ]);
        ponds.value = pondsResponse.data;
      } catch (e) {
        loadError.value = e.response?.data?.message || t('loadFailed');
      } finally {
        loading.value = false;
      }
    }

    function onFarmChange() {
      const farm = farms.value.find((item) => String(item.id) === String(form.farm_id));
      if (farm?.region && !form.region) {
        form.region = farm.region;
      }
      form.district = '';
    }

    function openForm() {
      Object.assign(form, {
        farm_id: '',
        name: '',
        region: '',
        district: '',
        area_m2: '',
      });
      error.value = '';
      showForm.value = true;
    }

    async function create() {
      if (!form.farm_id) {
        error.value = t('farmSelectionRequired');
        return;
      }
      if (!form.region) {
        error.value = t('selectRegionFirst');
        return;
      }
      if (!form.district) {
        error.value = t('districtRequired');
        return;
      }
      error.value = '';
      try {
        await window.axios.post('/owner/ponds', {
          farm_id: form.farm_id,
          name: form.name,
          region: form.region,
          district: form.district,
          area_m2: form.area_m2,
        });
        showForm.value = false;
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    async function remove(p) {
      if (!confirm(`"${p.name}" o‘chirilsinmi?`)) return;
      await window.axios.delete(`/owner/ponds/${p.id}`);
      await load();
    }

    onMounted(load);

    return {
      ponds,
      farms,
      loading,
      loadError,
      farmsLoaded,
      showForm,
      form,
      error,
      onFarmChange,
      openForm,
      create,
      remove,
      manageFarm,
    };
  },
};
</script>
