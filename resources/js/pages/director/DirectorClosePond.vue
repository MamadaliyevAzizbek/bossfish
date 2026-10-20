<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <strong v-if="pond?.name" class="page-head__context">{{ pond.name }}</strong>
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-fishery', params: { pondId } }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else-if="closed">
      <div class="panel pond-closed-banner">
        <h2>{{ $t('pondClosedTitle') }}</h2>
        <p>{{ $t('pondClosedHint') }}</p>
        <p><strong>{{ $t('qReportDate') }}:</strong> {{ closure?.closed_on }}</p>
        <p v-if="closure?.notes"><strong>{{ $t('notes') }}:</strong> {{ closure.notes }}</p>
        <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-activities', params: { pondId } }">
          {{ $t('finalReportArchive') }}
        </router-link>
      </div>
    </template>

    <template v-else-if="pendingRequest">
      <div class="panel closure-pending-banner">
        <h2>{{ $t('closureRequestPendingTitle') }}</h2>
        <p>{{ $t('closureRequestPendingHint') }}</p>
        <dl class="record-facts">
          <div>
            <dt>{{ $t('closeDate') }}</dt>
            <dd>{{ pendingRequest.closed_on }}</dd>
          </div>
          <div>
            <dt>{{ $t('sentAt') }}</dt>
            <dd>{{ formatDateTime(pendingRequest.created_at) }}</dd>
          </div>
        </dl>
        <p v-if="pendingRequest.notes"><strong>{{ $t('notes') }}:</strong> {{ pendingRequest.notes }}</p>
      </div>
    </template>

    <template v-else>
      <div class="panel estimate-info">
        <h2>{{ $t('closureRequestIntroTitle') }}</h2>
        <p>{{ $t('closureRequestIntroBody') }}</p>
      </div>

      <div class="panel">
        <h2>{{ $t('closureAnalysisTitle') }}</h2>
        <p class="field-hint">{{ $t('closureAnalysisHint') }}</p>
        <div v-if="!analysisLines.length" class="empty">{{ $t('noFishInEstimate') }}</div>
        <div v-else class="table-scroll">
          <table class="report-table">
            <thead>
              <tr>
                <th>{{ $t('fishType') }}</th>
                <th>{{ $t('added') }}</th>
                <th>{{ $t('sold') }}</th>
                <th>{{ $t('loss') }}</th>
                <th>{{ $t('inStock') }}</th>
                <th>{{ $t('saleRatio') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in analysisLines" :key="row.fish_type">
                <td>{{ row.label || fishLabel(row.fish_type) }}</td>
                <td class="num">{{ formatInt(row.added) }}</td>
                <td class="num">{{ formatInt(row.sold) }}</td>
                <td class="num">{{ formatInt(row.loss) }}</td>
                <td class="num">{{ formatInt(row.remaining) }}</td>
                <td class="num">
                  <span :class="{ 'text-success': row.loss_free }">
                    {{ row.sale_ratio_percent != null ? `${row.sale_ratio_percent}%` : '—' }}
                  </span>
                  <small v-if="row.loss_free" class="badge active">{{ $t('lossFreeBadge') }}</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="analysis?.overall_loss_free" class="field-hint success-hint">{{ $t('overallLossFreeHint') }}</p>
        <p v-else-if="analysis?.total_remaining > 0" class="field-hint warn-hint">{{ $t('remainingAtClosureHint') }}</p>
      </div>

      <form @submit.prevent="submit" class="panel form-section-blocks fishery-form">
        <section class="form-section-block">
          <h3>{{ $t('closureConfirmTitle') }}</h3>
          <p class="field-hint">{{ $t('closureConfirmHint') }}</p>

          <label class="checkbox-row">
            <input v-model="confirmations.all_finished" type="checkbox" required />
            <span>{{ $t('confirmAllFinished') }}</span>
          </label>
          <label class="checkbox-row">
            <input v-model="confirmations.no_fish_left" type="checkbox" required />
            <span>{{ $t('confirmNoFishLeft') }}</span>
          </label>
          <label class="checkbox-row">
            <input v-model="confirmations.sales_recorded" type="checkbox" required />
            <span>{{ $t('confirmSalesRecorded') }}</span>
          </label>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('closeDate') }}</h3>
          <div class="form-grid">
            <div class="field">
              <label>{{ $t('closeDate') }}</label>
              <input v-model="form.closed_on" type="date" required />
            </div>
            <div class="field form-wide">
              <label>{{ $t('notes') }}</label>
              <textarea v-model="form.notes" rows="2" :placeholder="$t('closureNotesPlaceholder')" />
            </div>
          </div>
        </section>

        <p v-if="error" class="error">{{ error }}</p>

        <div class="actions">
          <button class="btn btn-primary btn-block-mobile" type="submit" :disabled="saving || !canSubmit">
            {{ saving ? $t('loading') : $t('submitClosureRequest') }}
          </button>
        </div>
      </form>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatInt } from '../../utils/number';
import { fishLabel as labelFish } from '../../utils/fish';

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const pondId = computed(() => route.params.pondId);
    const loading = ref(true);
    const saving = ref(false);
    const error = ref('');
    const closed = ref(false);
    const pond = ref(null);
    const closure = ref(null);
    const pendingRequest = ref(null);
    const analysis = ref(null);
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({ closed_on: today, notes: '' });
    const confirmations = reactive({
      all_finished: false,
      no_fish_left: false,
      sales_recorded: false,
    });

    const fishLabel = (type) => labelFish(type, t);
    const analysisLines = computed(() => analysis.value?.fish_types || []);
    const canSubmit = computed(() => (
      confirmations.all_finished
      && confirmations.no_fish_left
      && confirmations.sales_recorded
    ));

    function formatDateTime(value) {
      if (!value) return '—';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '—';
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      const hours = String(date.getHours()).padStart(2, '0');
      const mins = String(date.getMinutes()).padStart(2, '0');
      return `${day}.${month}.${year} ${hours}:${mins}`;
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get(`/director/ponds/${pondId.value}/closure`);
        pond.value = data.pond;
        closed.value = !!data.closed;
        closure.value = data.closure || null;
        pendingRequest.value = data.pending_request || null;
        analysis.value = data.analysis || data.pending_request?.analysis_snapshot || null;
      } finally {
        loading.value = false;
      }
    }

    async function submit() {
      error.value = '';
      saving.value = true;
      try {
        const { data } = await window.axios.post(`/director/ponds/${pondId.value}/closure-request`, {
          closed_on: form.closed_on,
          notes: form.notes || null,
          confirm_all_finished: confirmations.all_finished ? 1 : 0,
          confirm_no_fish_left: confirmations.no_fish_left ? 1 : 0,
          confirm_sales_recorded: confirmations.sales_recorded ? 1 : 0,
        });
        pendingRequest.value = data.request;
        showSaved(t('closureRequestSent'));
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      } finally {
        saving.value = false;
      }
    }

    onMounted(load);

    return {
      pondId,
      loading,
      saving,
      error,
      closed,
      pond,
      closure,
      pendingRequest,
      analysis,
      analysisLines,
      form,
      confirmations,
      canSubmit,
      fishLabel,
      formatInt,
      formatDateTime,
      submit,
    };
  },
};
</script>
