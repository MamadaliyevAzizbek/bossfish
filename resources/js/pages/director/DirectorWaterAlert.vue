<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong class="page-head__context">{{ $t('waterAlertShortTitle') }}</strong>
      <button type="button" class="btn btn-outline btn-sm" @click="goBack">{{ $t('back') }}</button>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel error">{{ error }}</div>

    <template v-else>
      <section class="panel wq-detail-hero" :class="`wq-detail-hero--${assessment.level}`">
        <h1>{{ $t('waterAlertShortTitle') }}</h1>
        <p>{{ $t('waterAlertDetailLead', { pond: pond?.name || '—' }) }}</p>
        <dl class="wq-detail-meta">
          <div>
            <dt>{{ $t('date') }}</dt>
            <dd>{{ report.report_date || '—' }}</dd>
          </div>
          <div>
            <dt>{{ $t('testTime') }}</dt>
            <dd>{{ report.test_time || '—' }}</dd>
          </div>
          <div>
            <dt>{{ $t('waterTimeBandLabel', { band: bandLabel }) }}</dt>
            <dd>{{ stockLabel }}</dd>
          </div>
        </dl>
      </section>

      <section class="panel">
        <h2>{{ $t('waterAlertIssuesTitle') }}</h2>
        <p class="field-hint">{{ $t('waterAlertIssuesHint') }}</p>

        <article
          v-for="row in metricRows"
          :key="row.key"
          class="wq-detail-card"
          :class="`wq-detail-card--${row.level}`"
        >
          <header>
            <strong>{{ row.label }}</strong>
            <span class="wq-detail-badge">{{ levelLabel(row.level) }}</span>
          </header>
          <dl>
            <div>
              <dt>{{ $t('waterAlertMeasured') }}</dt>
              <dd>{{ row.valueText }}</dd>
            </div>
            <div>
              <dt>{{ $t('waterAlertNorm') }}</dt>
              <dd>{{ row.norm }}</dd>
            </div>
          </dl>
          <p v-if="row.diagnosis" class="wq-detail-diagnosis">{{ row.diagnosis }}</p>
          <p>{{ row.about }}</p>
          <p v-if="row.fish" class="wq-detail-fish">{{ row.fish }}</p>
          <p v-if="row.action" class="wq-detail-action">
            <strong>{{ $t('waterActionLabel') }}:</strong> {{ row.action }}
          </p>
        </article>
      </section>

      <section v-if="primaryAction && assessment.level !== 'ok'" class="panel wq-detail-action-panel">
        <h2>{{ $t('waterActionPlanTitle') }}</h2>
        <p>{{ primaryAction }}</p>
      </section>

      <section class="panel wq-detail-specialist">
        <h2>{{ $t('waterAlertSpecialistTitle') }}</h2>
        <p>{{ $t('waterAlertSpecialistHint') }}</p>
        <a class="btn btn-primary btn-block-mobile" :href="`tel:${specialistPhone}`">
          {{ $t('waterAlertCallSpecialist', { phone: specialistPhone }) }}
        </a>
        <router-link
          class="btn btn-outline btn-block-mobile"
          :to="{ name: 'director-main-report', params: { pondId } }"
        >
          {{ $t('mainReport') }}
        </router-link>
      </section>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { t } from '../../i18n';
import { formatWaterAction, formatWaterIssue, timeBandKey } from '../../utils/waterQuality';

const METRICS = [
  'do_mg_l',
  'ph_level',
  'ammonia_mg_l',
  'nitrite_mg_l',
  'water_transparency_cm',
];

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const pondId = computed(() => route.params.pondId);
    const reportId = computed(() => route.params.reportId);
    const loading = ref(true);
    const error = ref('');
    const pond = ref(null);
    const report = ref({});
    const assessment = ref({ level: 'caution', band: { key: 'unknown' }, issues: [], metrics: {} });
    const standards = ref(null);
    const specialistPhone = ref('+998774442525');

    const bandKey = computed(() => assessment.value?.band?.key || timeBandKey(report.value?.test_time));
    const bandLabel = computed(() => {
      const key = bandKey.value;
      if (!key || key === 'unknown') return t('waterBand_day');
      return t(`waterBand_${key}`);
    });

    const stockLabel = computed(() => {
      const n = Number(pond.value?.current_stock);
      if (!Number.isFinite(n)) return '';
      return t('waterAlertFishStock', { count: n });
    });

    const issuesByMetric = computed(() => {
      const map = {};
      for (const issue of assessment.value?.issues || []) {
        if (!issue?.metric) continue;
        if (!map[issue.metric] || rank(issue.level) > rank(map[issue.metric].level)) {
          map[issue.metric] = issue;
        }
      }
      return map;
    });

    const primaryAction = computed(() => {
      const key = assessment.value?.primary_action_key;
      if (!key || key === 'waterActionOk') return '';
      return formatWaterAction(key, t);
    });

    const metricRows = computed(() => {
      const metricsCfg = standards.value?.metrics || {};
      const levels = assessment.value?.metrics || {};
      const band = bandKey.value;
      const rows = [];

      for (const key of METRICS) {
        const raw = report.value?.[key];
        if (raw === null || raw === undefined || raw === '') continue;
        const value = Number(raw);
        const level = levels[key]?.level || 'ok';
        const cfg = metricsCfg[key] || {};
        const b = cfg.bands?.[band] || cfg.bands?.unknown || {};
        const issue = issuesByMetric.value[key];
        rows.push({
          key,
          label: t(`waterMetric_${key}`),
          level,
          valueText: `${value}${cfg.unit ? ` ${cfg.unit}` : ''}`,
          norm: normText(key, b),
          diagnosis: issue ? formatWaterIssue(issue, t) : '',
          about: t(`waterAbout_${key}`),
          fish: level === 'ok' ? '' : t(`waterFish_${key}`),
          action: issue ? formatWaterAction(issue, t) : '',
        });
      }

      rows.sort((a, b) => rank(b.level) - rank(a.level));
      return rows;
    });

    function rank(level) {
      if (level === 'danger') return 2;
      if (level === 'caution') return 1;
      return 0;
    }

    function levelLabel(level) {
      if (level === 'danger') return t('waterLevelDanger');
      if (level === 'caution') return t('waterLevelCaution');
      return t('waterLevelOk');
    }

    function normText(key, b) {
      if (!b) return '—';
      if (key === 'do_mg_l') return t('waterNormDo', { min: b.ok_min });
      if (key === 'ph_level') return t('waterNormPh', { from: b.ok?.[0], to: b.ok?.[1] });
      if (key === 'ammonia_mg_l') return t('waterNormAmmonia', { max: b.ok_max });
      if (key === 'nitrite_mg_l') return t('waterNormNitrite', { max: b.ok_max });
      if (key === 'water_transparency_cm') return t('waterNormTransparency', { from: b.ok?.[0], to: b.ok?.[1] });
      return '—';
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get(
          `/director/ponds/${pondId.value}/main-reports/${reportId.value}/water-quality`,
        );
        pond.value = data.pond;
        report.value = data.report || {};
        assessment.value = data.assessment || { level: 'caution', issues: [], metrics: {}, band: {} };
        standards.value = data.standards;
        if (data.specialist_phone) specialistPhone.value = data.specialist_phone;
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function goBack() {
      if (window.history.length > 1) router.back();
      else router.push({ name: 'director-fishery', params: { pondId: pondId.value } });
    }

    onMounted(load);

    return {
      pondId,
      loading,
      error,
      pond,
      report,
      assessment,
      bandLabel,
      stockLabel,
      metricRows,
      primaryAction,
      specialistPhone,
      levelLabel,
      goBack,
    };
  },
};
</script>
