<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong v-if="pond?.name" class="page-head__context">{{ pond.name }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery', params: { pondId } }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="report-page-layout report-page-layout--daily">
        <section class="panel report-form-panel">
          <h2>{{ editingId ? $t('editReport') : $t('newReport') }}</h2>

          <form @submit.prevent="save" class="form-section-blocks fishery-form">
        <section class="form-section-block">
          <h3>{{ $t('date') }}</h3>
          <div class="form-grid">
            <div class="field form-wide">
              <label>{{ $t('qReportDate') }}</label>
              <input v-model="form.report_date" type="date" required />
            </div>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('weighFishOptional') }}</h3>
          <p class="field-hint">{{ $t('weighFishHint') }}</p>

          <label class="toggle-label">
            <input v-model="form.weigh_fish" type="checkbox" />
            <span>{{ form.weigh_fish ? $t('weighFishOn') : $t('weighFishOff') }}</span>
          </label>

          <div v-if="form.weigh_fish && !stockTypes.length" class="empty">
            <p>{{ $t('noFishYet') }}</p>
            <router-link class="btn btn-primary btn-sm" :to="{ name: 'director-add-fish', params: { pondId } }">
              {{ $t('addFish') }}
            </router-link>
          </div>

          <div v-else-if="form.weigh_fish" class="fish-weight-grid">
            <div v-for="type in stockTypes" :key="type" class="fish-weight-card">
              <div class="fish-weight-head">
                <strong>{{ fishLabel(type) }}</strong>
                <span v-if="suggestedWeights[type]" class="record-tag">
                  {{ $t('previousWeight') }}: {{ suggestedWeights[type].average_weight_g }} g
                </span>
              </div>
              <div class="field">
                <label>{{ $t('qAverageWeight') }}</label>
                <FormattedNumberInput
                  v-model="form.weights[type]"
                  :max-decimals="1"
                  :placeholder="suggestedWeights[type]?.average_weight_g ? String(suggestedWeights[type].average_weight_g) : ''"
                />
              </div>
              <small v-if="suggestedWeights[type]" class="field-hint">
                {{ weightSourceText(suggestedWeights[type]) }}
              </small>
            </div>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('labTests') }}</h3>
          <p class="field-hint">{{ $t('waterQualityKitHint') }}</p>

          <div class="form-grid">
            <div class="field">
              <label>{{ $t('qTestTime') }} <span class="req">*</span></label>
              <input v-model="form.test_time" type="time" />
              <small class="field-hint">{{ $t('waterTestTimeHint') }}</small>
            </div>
            <div class="field">
              <label>{{ $t('qDoLevel') }}</label>
              <FormattedNumberInput v-model="form.do_mg_l" :max-decimals="3" />
            </div>
            <div class="field">
              <label>{{ $t('qPhLevel') }}</label>
              <FormattedNumberInput v-model="form.ph_level" :max-decimals="2" />
              <small class="field-hint">{{ $t('waterKitRangePh') }}</small>
            </div>
            <div class="field">
              <label>{{ $t('qAmmonia') }}</label>
              <FormattedNumberInput v-model="form.ammonia_mg_l" :max-decimals="3" />
              <small class="field-hint">{{ $t('waterKitRangeAmmonia') }}</small>
            </div>
            <div class="field">
              <label>{{ $t('qNitrite') }}</label>
              <FormattedNumberInput v-model="form.nitrite_mg_l" :max-decimals="3" />
              <small class="field-hint">{{ $t('waterKitRangeNitrite') }}</small>
            </div>
            <div class="field">
              <label>{{ $t('qWaterTransparency') }}</label>
              <FormattedNumberInput v-model="form.water_transparency_cm" :max-decimals="2" />
            </div>
          </div>

          <WaterQualityPanel
            :readings="{
              test_time: form.test_time,
              do_mg_l: form.do_mg_l,
              ph_level: form.ph_level,
              ammonia_mg_l: form.ammonia_mg_l,
              nitrite_mg_l: form.nitrite_mg_l,
              water_transparency_cm: form.water_transparency_cm,
            }"
            :standards="waterStandards"
          />
        </section>

        <div class="submit-bar">
          <p v-if="error" class="error">{{ error }}</p>
          <button class="btn btn-primary btn-block-mobile" type="submit" :disabled="saving">
            {{ saving ? $t('loading') : $t('save') }}
          </button>
          <button v-if="editingId" class="btn btn-outline btn-block-mobile" type="button" @click="resetForm">
            {{ $t('cancel') }}
          </button>
        </div>
          </form>
        </section>

        <div class="report-history-toggle-wrap report-history-toggle-wrap--main">
          <button
            class="btn btn-outline btn-block-mobile report-history-toggle report-history-toggle--main"
            type="button"
            :aria-expanded="showHistory"
            @click="showHistory = !showHistory"
          >
            {{ showHistory ? $t('dailyReportHideHistory') : $t('dailyReportShowHistory') }}
            <span v-if="reports.length" class="report-history-count report-history-count--main">{{ reports.length }}</span>
          </button>
        </div>

        <section v-if="showHistory" class="panel report-history-panel report-history-panel--main">
          <h2>
            <span class="report-history-accent report-history-accent--main" aria-hidden="true" />
            {{ $t('previousReports') }}
          </h2>
          <p class="field-hint report-history-hint">{{ $t('previousReportsHint') }}</p>
          <div v-if="!reports.length" class="empty">{{ $t('noReports') }}</div>
          <ul v-else class="main-history-list">
            <li v-for="report in reports" :key="report.id" class="main-history-card">
              <div class="main-history-card__head">
                <div class="main-history-card__when">
                  <strong>{{ dateOnly(report.report_date) }}</strong>
                  <span v-if="report.test_time" class="main-history-time">
                    {{ String(report.test_time).slice(0, 5) }}
                  </span>
                </div>
                <div class="main-history-card__actions">
                  <button class="btn btn-outline btn-sm" type="button" @click="startEdit(report)">{{ $t('edit') }}</button>
                  <button class="btn btn-outline btn-sm btn-danger-outline" type="button" @click="remove(report)">{{ $t('delete') }}</button>
                </div>
              </div>

              <div class="main-history-card__body">
                <section class="main-history-block main-history-block--weights">
                  <h3>{{ $t('weighFishOptional') }}</h3>
                  <div v-if="report.fish_weights?.length" class="main-history-chips">
                    <span
                      v-for="w in report.fish_weights"
                      :key="w.id || w.fish_type"
                      class="main-history-chip"
                      :class="`main-history-chip--${w.fish_type}`"
                    >
                      <span class="main-history-chip__name">{{ fishLabel(w.fish_type) }}</span>
                      <strong>{{ kgToGrams(w.average_weight_kg) }} g</strong>
                    </span>
                  </div>
                  <p v-else class="main-history-empty">{{ $t('weighFishOff') }}</p>
                </section>

                <section class="main-history-block main-history-block--lab">
                  <h3>{{ $t('labTests') }}</h3>
                  <div class="main-history-metrics">
                    <div class="main-history-metric" :class="{ 'is-filled': hasValue(report.do_mg_l) }">
                      <span>{{ $t('doLevel') }}</span>
                      <strong>{{ valueOrDash(report.do_mg_l) }}</strong>
                    </div>
                    <div class="main-history-metric" :class="{ 'is-filled': hasValue(report.ph_level) }">
                      <span>{{ $t('phLevel') }}</span>
                      <strong>{{ valueOrDash(report.ph_level) }}</strong>
                    </div>
                    <div class="main-history-metric" :class="{ 'is-filled': hasValue(report.ammonia_mg_l) }">
                      <span>{{ $t('ammonia') }}</span>
                      <strong>{{ valueOrDash(report.ammonia_mg_l) }}</strong>
                    </div>
                    <div class="main-history-metric" :class="{ 'is-filled': hasValue(report.nitrite_mg_l) }">
                      <span>{{ $t('nitrite') }}</span>
                      <strong>{{ valueOrDash(report.nitrite_mg_l) }}</strong>
                    </div>
                    <div class="main-history-metric" :class="{ 'is-filled': hasValue(report.water_transparency_cm) }">
                      <span>{{ $t('waterTransparency') }}</span>
                      <strong>{{ report.water_transparency_cm ? `${report.water_transparency_cm} cm` : '—' }}</strong>
                    </div>
                  </div>
                </section>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import WaterQualityPanel from '../../components/WaterQualityPanel.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { summarizeWaterIssues } from '../../utils/waterQuality';

const FISH_KEYS = ['sazan', 'tolstolob', 'oq_amur', 'targil_tolstolob'];

export default {
  components: { DirectorShell, WaterQualityPanel },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const pondId = computed(() => route.params.pondId);
    const loading = ref(true);
    const saving = ref(false);
    const editingId = ref(null);
    const showHistory = ref(false);
    const pond = ref(null);
    const reports = ref([]);
    const suggestedWeights = ref({});
    const fishTypes = ref({});
    const stockTypes = ref([]);
    const waterStandards = ref(null);
    const error = ref('');
    const today = new Date().toISOString().slice(0, 10);

    const emptyWeights = () => Object.fromEntries(FISH_KEYS.map((key) => [key, '']));
    const kgToGrams = (value) => Math.round(Number(value) * 1000 * 10) / 10;

    const form = reactive({
      report_date: today,
      weigh_fish: false,
      weights: emptyWeights(),
      test_time: '',
      do_mg_l: '',
      ph_level: '',
      ammonia_mg_l: '',
      nitrite_mg_l: '',
      water_transparency_cm: '',
    });

    const dateOnly = (value) => (typeof value === 'string' ? value.slice(0, 10) : value);
    const hasValue = (value) => value !== null && value !== undefined && value !== '';
    const valueOrDash = (value) => (hasValue(value) ? value : '—');
    const fishLabel = (type) => fishTypes.value[type] || type;

    function weightSourceText(item) {
      if (!item) return '';
      if (item.source === 'main_report') {
        return `${t('fromPreviousMainReport')} (${item.source_date})`;
      }
      return `${t('fromStockAdd')} (${item.source_date})`;
    }

    function prefillSuggestedWeights() {
      stockTypes.value.forEach((type) => {
        if (!form.weights[type] && suggestedWeights.value[type]) {
          form.weights[type] = String(suggestedWeights.value[type].average_weight_g);
        }
      });
    }

    function resetForm() {
      editingId.value = null;
      Object.assign(form, {
        report_date: today,
        weigh_fish: false,
        weights: emptyWeights(),
        test_time: '',
        do_mg_l: '',
        ph_level: '',
        ammonia_mg_l: '',
        nitrite_mg_l: '',
        water_transparency_cm: '',
      });
      error.value = '';
    }

    function startEdit(report) {
      const weights = emptyWeights();
      (report.fish_weights || []).forEach((row) => {
        weights[row.fish_type] = String(kgToGrams(row.average_weight_kg));
      });

      editingId.value = report.id;
      Object.assign(form, {
        report_date: dateOnly(report.report_date),
        weigh_fish: !!(report.fish_weights?.length),
        weights,
        test_time: report.test_time ? String(report.test_time).slice(0, 5) : '',
        do_mg_l: report.do_mg_l ?? '',
        ph_level: report.ph_level ?? '',
        ammonia_mg_l: report.ammonia_mg_l ?? '',
        nitrite_mg_l: report.nitrite_mg_l ?? '',
        water_transparency_cm: report.water_transparency_cm ?? '',
      });
      error.value = '';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    watch(() => form.weigh_fish, (enabled) => {
      if (enabled && !editingId.value) prefillSuggestedWeights();
    });

    async function load() {
      loading.value = true;
      try {
        const [mainRes, stockRes] = await Promise.all([
          window.axios.get(`/director/ponds/${pondId.value}/main-reports`),
          window.axios.get(`/director/ponds/${pondId.value}/fish-stock`),
        ]);

        pond.value = mainRes.data.pond;
        suggestedWeights.value = mainRes.data.suggested_weights || {};
        fishTypes.value = mainRes.data.fish_types || {};
        waterStandards.value = mainRes.data.water_quality_standards || null;
        reports.value = mainRes.data.reports?.data || mainRes.data.reports || [];
        stockTypes.value = (stockRes.data.stock || []).map((item) => item.fish_type);
      } finally {
        loading.value = false;
      }
    }

    async function save() {
      error.value = '';
      saving.value = true;
      try {
        const types = form.weigh_fish ? stockTypes.value : [];
        const fishWeights = form.weigh_fish
          ? types
            .filter((type) => form.weights[type] !== '' && form.weights[type] !== null)
            .map((type) => ({
              fish_type: type,
              average_weight_g: form.weights[type],
            }))
          : [];

        const payload = {
          report_date: form.report_date,
          weigh_fish: form.weigh_fish,
          fish_weights: fishWeights,
          test_time: form.test_time || null,
          do_mg_l: form.do_mg_l || null,
          ph_level: form.ph_level || null,
          ammonia_mg_l: form.ammonia_mg_l || null,
          nitrite_mg_l: form.nitrite_mg_l || null,
          water_transparency_cm: form.water_transparency_cm || null,
        };

        const { data } = editingId.value
          ? await window.axios.put(`/director/ponds/${pondId.value}/main-reports/${editingId.value}`, payload)
          : await window.axios.post(`/director/ponds/${pondId.value}/main-reports`, payload);

        const wq = data?.water_quality;
        if (wq && wq.level && wq.level !== 'ok') {
          const lines = summarizeWaterIssues(wq.issues, t);
          const head = wq.level === 'danger' ? t('waterAlertTitleDanger') : t('waterAlertTitleCaution');
          showSaved([head, ...lines, t('waterAlertCallSpecialist', { phone: t('homeContactPhone') })].filter(Boolean).join(' · '));
        } else {
          showSaved(t('saved'));
        }
        await router.push({ name: 'director-fishery', params: { pondId: pondId.value } });
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    async function remove(report) {
      if (!confirm(t('confirmDeleteReport'))) return;
      await window.axios.delete(`/director/ponds/${pondId.value}/main-reports/${report.id}`);
      if (editingId.value === report.id) resetForm();
      await load();
    }

    onMounted(load);

    return {
      pondId,
      loading,
      saving,
      editingId,
      showHistory,
      pond,
      reports,
      suggestedWeights,
      stockTypes,
      waterStandards,
      form,
      error,
      save,
      startEdit,
      resetForm,
      remove,
      dateOnly,
      hasValue,
      valueOrDash,
      kgToGrams,
      fishLabel,
      weightSourceText,
    };
  },
};
</script>
