<template>
  <DirectorShell>
    <div class="page-head">
      <div>
        <h1>{{ director?.name || $t('director') }}</h1>
        <p>
          <span class="mono">{{ director?.phone }}</span>
          · {{ formatArea(director?.ponds_total_area_m2) }} {{ $t('hectare') }}
        </p>
      </div>
      <div class="page-head-actions">
        <button
          v-if="!farms.length"
          class="btn btn-primary btn-sm"
          type="button"
          @click="openFarmForm"
        >
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          {{ $t('addFarm') }}
        </button>
        <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-clients' }" @click="clearActing">
          {{ $t('backToDirectors') }}
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div v-if="!farms.length" class="panel empty">
        {{ $t('directorNoFarmYet') }}
      </div>

      <div v-else-if="farms.length > 1" class="pond-pick-grid">
        <button
          v-for="farm in farms"
          :key="farm.id"
          type="button"
          class="pond-pick pond-pick--farm"
          @click="openFarm(farm)"
        >
          <div class="boss-hub-card__body">
            <BrandLogo variant="light" compact class="boss-hub-card__brand" />
            <h3>{{ farm.name }}</h3>
            <p>{{ farm.region }} · {{ farm.ponds_count || 0 }} {{ $t('pondsCount') }}</p>
          </div>
          <span class="boss-nav-card__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></span>
        </button>
      </div>
    </template>

    <div v-if="showFarmForm" class="modal-backdrop" @click.self="showFarmForm = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('newFarm') }}</h2>
        <form class="form-grid" @submit.prevent="createFarm">
          <div class="field">
            <label>{{ $t('name') }}</label>
            <input v-model="farmForm.name" required maxlength="255" />
          </div>
          <div class="field">
            <label>{{ $t('region') }}</label>
            <select v-model="farmForm.region" required>
              <option value="">{{ $t('selectRegion') }}</option>
              <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <p v-if="error" class="error" style="grid-column: 1 / -1">{{ error }}</p>
          <div class="actions" style="grid-column: 1 / -1">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="showFarmForm = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import BrandLogo from '../../components/BrandLogo.vue';
import { setDirectorFarmId } from '../../utils/directorFarm';
import { clearActingDirector, setActingDirector } from '../../utils/ownerContext';
import { normalizeRegions } from '../../utils/regionCoordinates';
import { i18nState, t } from '../../i18n';

export default {
  components: { DirectorShell, BrandLogo },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const directorId = route.params.directorId;
    const director = ref(null);
    const farms = ref([]);
    const regions = ref([]);
    const loading = ref(true);
    const showFarmForm = ref(false);
    const error = ref('');
    const farmForm = reactive({ name: '', region: '' });

    const number = (value) => {
      if (value === null || value === undefined || value === '') return '0';
      return new Intl.NumberFormat(i18nState.locale === 'ru' ? 'ru-RU' : 'uz-UZ').format(Number(value));
    };

    const formatArea = (areaM2) => number(Number(areaM2 || 0));

    function clearActing() {
      clearActingDirector();
    }

    function openFarm(farm) {
      setDirectorFarmId(farm.id);
      return router.replace({ name: 'director-farm-hub' });
    }

    async function loadDirector() {
      const { data } = await window.axios.get('/owner/directors');
      director.value = data.find((item) => String(item.id) === String(directorId)) || null;
      if (director.value) {
        setActingDirector(director.value);
      }
    }

    async function loadFarms() {
      const { data } = await window.axios.get('/director/farms', {
        params: { director_id: directorId },
      });
      farms.value = data;
    }

    async function loadRegions() {
      const { data } = await window.axios.get('/owner/regions');
      regions.value = normalizeRegions(data).map((r) => r.name);
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        await Promise.all([loadDirector(), loadFarms(), loadRegions()]);
        if (farms.value.length === 1) {
          await openFarm(farms.value[0]);
        }
      } catch (e) {
        farms.value = [];
        error.value = e.response?.data?.message || t('noData');
      } finally {
        loading.value = false;
      }
    }

    function openFarmForm() {
      Object.assign(farmForm, { name: '', region: '' });
      error.value = '';
      showFarmForm.value = true;
    }

    async function createFarm() {
      error.value = '';
      try {
        const { data } = await window.axios.post('/owner/farms', {
          name: farmForm.name,
          region: farmForm.region,
          director_id: Number(directorId),
        });
        showFarmForm.value = false;
        await openFarm(data);
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    onMounted(load);

    return {
      director,
      farms,
      regions,
      loading,
      showFarmForm,
      farmForm,
      error,
      number,
      formatArea,
      clearActing,
      openFarmForm,
      createFarm,
      openFarm,
    };
  },
};
</script>
