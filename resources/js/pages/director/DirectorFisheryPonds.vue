<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <button class="btn btn-primary" type="button" @click="openForm()">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        {{ $t('addPond') }}
      </button>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="pond-season-tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="pond-season-tab"
          :class="{ 'pond-season-tab--active': tab === 'active' }"
          :aria-selected="tab === 'active'"
          @click="tab = 'active'"
        >
          {{ $t('pondsActiveTab') }}
          <em>{{ activePonds.length }}</em>
        </button>
        <button
          type="button"
          role="tab"
          class="pond-season-tab"
          :class="{ 'pond-season-tab--active': tab === 'closed' }"
          :aria-selected="tab === 'closed'"
          @click="tab = 'closed'"
        >
          {{ $t('pondsClosedTab') }}
          <em>{{ closedPonds.length }}</em>
        </button>
      </div>

      <p class="pond-season-hint">
        {{ tab === 'active' ? $t('pondsActiveHint') : $t('pondsClosedHint') }}
      </p>

      <div v-if="tab === 'active'" class="pond-pick-grid">
        <button
          v-for="p in activePonds"
          :key="p.id"
          type="button"
          class="pond-pick"
          @click="selectPond(p)"
        >
          <div class="boss-hub-card__body">
            <h3>{{ p.name }}</h3>
            <p>{{ p.district || p.region }} · {{ p.area_m2 }} {{ $t('hectare') }}</p>
            <span>{{ $t('stock') }}: {{ p.current_stock || 0 }} {{ $t('pieces') }}</span>
          </div>
          <span class="boss-nav-card__arrow" aria-hidden="true">
            <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg>
          </span>
        </button>

        <div v-if="!activePonds.length" class="panel empty">
          {{ $t('noActivePondsYet') }}
        </div>
      </div>

      <div v-else class="pond-pick-grid">
        <div
          v-for="p in closedPonds"
          :key="p.id"
          class="pond-pick pond-pick--closed"
        >
          <button type="button" class="pond-pick__main" @click="openArchive(p)">
            <div class="boss-hub-card__body">
              <h3>{{ p.name }}</h3>
              <p>{{ p.district || p.region }} · {{ p.area_m2 }} {{ $t('hectare') }}</p>
              <span class="badge badge-muted">{{ $t('pondClosedBadge') }}</span>
              <small v-if="p.closed_at">{{ formatClosedOn(p.closed_at) }}</small>
            </div>
          </button>
          <div class="pond-pick__season-actions">
            <button type="button" class="btn btn-outline btn-sm" @click="openArchive(p)">
              {{ $t('viewPondArchive') }}
            </button>
            <button type="button" class="btn btn-primary btn-sm" @click="openSeasonForm(p)">
              {{ $t('openNextSeason') }}
            </button>
          </div>
        </div>

        <div v-if="!closedPonds.length" class="panel empty">
          {{ $t('noClosedPondsYet') }}
        </div>
      </div>

      <div v-if="pendingRequests.length" class="panel" style="margin-top: 1rem">
        <h2 style="margin: 0 0 0.75rem; font-size: 1.05rem">{{ $t('pendingPondRequests') }}</h2>
        <ul class="pending-pond-requests">
          <li v-for="item in pendingRequests" :key="item.id">
            <strong>{{ item.name }}</strong>
            <span>{{ item.district || item.region }} · {{ item.area_m2 }} {{ $t('hectare') }}</span>
            <span class="badge badge-warn">{{ $t('statusPending') }}</span>
          </li>
        </ul>
        <p class="field-hint">{{ $t('createPondRequestPendingHint') }}</p>
      </div>
    </template>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal modal-wide">
        <h2 class="modal-title">
          {{ seasonSource
            ? $t('newSeasonPondTitle')
            : (isOwnerActing ? $t('newPond') : $t('newPondRequest')) }}
        </h2>
        <p v-if="seasonSource" class="field-hint" style="margin-top: -0.25rem">
          {{ $t('newSeasonPondHint', { name: seasonSource.name, year: currentYear }) }}
        </p>
        <p v-else-if="!isOwnerActing" class="field-hint" style="margin-top: -0.25rem">
          {{ $t('createPondRequestIntro') }}
        </p>
        <form @submit.prevent="create" class="form-grid one-col">
          <div class="field">
            <label>{{ $t('pondName') }} <span class="req">*</span></label>
            <input v-model="form.name" required :placeholder="$t('pondExample')" />
            <small v-if="seasonSource" class="field-hint">{{ $t('newSeasonNameHint') }}</small>
          </div>
          <RegionDistrictPicker
            v-model:region="form.region"
            v-model:district="form.district"
            :region-label="$t('region')"
            :district-label="$t('district')"
          />
          <div class="field">
            <label>{{ $t('area') }} ({{ $t('hectare') }}) <span class="req">*</span></label>
            <FormattedNumberInput v-model="form.area_m2" :max-decimals="2" required />
            <small class="field-hint">{{ $t('areaHectareHint') }}</small>
          </div>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">
              {{ seasonSource
                ? (isOwnerActing ? $t('openNextSeason') : $t('submitPondRequest'))
                : (isOwnerActing ? $t('save') : $t('submitPondRequest')) }}
            </button>
            <button class="btn btn-outline" type="button" @click="showForm = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import RegionDistrictPicker from '../../components/RegionDistrictPicker.vue';
import auth from '../../auth';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatUzLongDate } from '../../utils/date';

function isPondClosed(pond) {
  return !!pond?.closed_at || pond?.status === 'inactive' || pond?.status === 'closed';
}

/** "Havza 1 · 2025" → "Havza 1 · 2026" */
function nextSeasonPondName(name, year = new Date().getFullYear()) {
  const raw = String(name || '').trim();
  const base = raw.replace(/\s*[·\-]?\s*\(?20\d{2}\)?\s*$/u, '').trim() || raw;
  return `${base} · ${year}`;
}

export default {
  components: { DirectorShell, RegionDistrictPicker },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const farmId = useDirectorFarmId();
    const farm = ref(null);
    const ponds = ref([]);
    const pendingRequests = ref([]);
    const loading = ref(true);
    const showForm = ref(false);
    const error = ref('');
    const tab = ref('active');
    const seasonSource = ref(null);
    const isOwnerActing = computed(() => auth.isOwner());
    const currentYear = new Date().getFullYear();
    const form = reactive({
      name: '',
      region: '',
      district: '',
      area_m2: '',
    });

    const activePonds = computed(() => ponds.value.filter((p) => !isPondClosed(p)));
    const closedPonds = computed(() => ponds.value.filter((p) => isPondClosed(p)));

    async function load() {
      loading.value = true;
      try {
        const [f, p, r] = await Promise.all([
          window.axios.get(`/director/farms/${farmId.value}`),
          window.axios.get(`/director/farms/${farmId.value}/ponds`),
          window.axios.get(`/director/farms/${farmId.value}/pond-create-requests`),
        ]);
        farm.value = f.data;
        ponds.value = p.data || [];
        pendingRequests.value = (r.data.requests || []).filter((item) => item.status === 'pending');
        if (!activePonds.value.length && closedPonds.value.length) {
          tab.value = 'closed';
        }
      } finally {
        loading.value = false;
      }
    }

    function openForm() {
      seasonSource.value = null;
      Object.assign(form, {
        name: '',
        region: farm.value?.region || '',
        district: '',
        area_m2: '',
      });
      error.value = '';
      showForm.value = true;
    }

    function openSeasonForm(pond) {
      seasonSource.value = pond;
      Object.assign(form, {
        name: nextSeasonPondName(pond.name, currentYear),
        region: pond.region || farm.value?.region || '',
        district: pond.district || '',
        area_m2: pond.area_m2 ?? '',
      });
      error.value = '';
      showForm.value = true;
    }

    async function create() {
      error.value = '';
      if (!form.region) {
        error.value = t('selectRegionFirst');
        return;
      }
      if (!form.district) {
        error.value = t('districtRequired');
        return;
      }
      try {
        const { data } = await window.axios.post(`/director/farms/${farmId.value}/ponds`, {
          name: form.name,
          region: form.region,
          district: form.district,
          area_m2: form.area_m2,
          notes: seasonSource.value
            ? t('newSeasonFromNote', { name: seasonSource.value.name })
            : null,
        });
        showForm.value = false;
        seasonSource.value = null;
        if (isOwnerActing.value && data?.id) {
          await load();
          tab.value = 'active';
          selectPond(data);
          return;
        }
        showSaved(t('createPondRequestSent'));
        await load();
        tab.value = 'active';
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    function selectPond(p) {
      localStorage.setItem('director_pond_id', String(p.id));
      router.push({ name: 'director-fishery', params: { pondId: p.id } });
    }

    function openArchive(p) {
      localStorage.setItem('director_pond_id', String(p.id));
      router.push({ name: 'director-activities', params: { pondId: p.id } });
    }

    function formatClosedOn(value) {
      return formatUzLongDate(value) || String(value).slice(0, 10);
    }

    watch(
      () => [route.query.seasonFrom, ponds.value],
      ([fromId, list]) => {
        if (!fromId || !list?.length) return;
        const source = list.find((p) => String(p.id) === String(fromId) && isPondClosed(p));
        if (source) {
          tab.value = 'closed';
          openSeasonForm(source);
          router.replace({ name: 'director-fishery-ponds', query: {} });
        }
      },
      { immediate: true },
    );

    onMounted(load);

    return {
      farmId,
      farm,
      ponds,
      activePonds,
      closedPonds,
      pendingRequests,
      loading,
      showForm,
      form,
      error,
      tab,
      seasonSource,
      currentYear,
      isOwnerActing,
      openForm,
      openSeasonForm,
      create,
      selectPond,
      openArchive,
      formatClosedOn,
    };
  },
};
</script>
