<template>
  <DirectorShell>
    <div class="page-head page-head--toolbar">
      <router-link class="btn btn-outline btn-sm" :to="{ name: 'director-finance' }">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="panel empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="finance-category-total panel">
        <span>{{ $t('financeInvestmentTotal') }}</span>
        <strong>{{ formatMoney(total) }}</strong>
      </div>

      <div class="panel">
        <h2>{{ editingId ? $t('edit') : $t('financeAddInvestment') }}</h2>
        <form class="form-section-blocks" @submit.prevent="save">
          <section class="form-section-block">
            <h3>{{ $t('date') }}</h3>
            <div class="form-grid">
              <div class="field form-wide">
                <label>{{ $t('date') }}</label>
                <input v-model="form.invested_at" type="date" required />
              </div>
            </div>
          </section>

          <section class="form-section-block">
            <h3>{{ $t('financeInvestor') }}</h3>
            <div class="form-grid">
              <div class="field form-wide">
                <label>{{ $t('financeInvestor') }}</label>
                <input v-model.trim="form.investor" type="text" maxlength="120" required :placeholder="$t('financeInvestorPlaceholder')" />
              </div>
              <div class="field form-wide">
                <label>{{ $t('amount') }}</label>
                <FormattedNumberInput v-model="form.amount" :max-decimals="0" required />
              </div>
            </div>
          </section>

          <section class="form-section-block">
            <h3>{{ $t('notes') }}</h3>
            <div class="form-grid">
              <div class="field form-wide">
                <label>{{ $t('notes') }}</label>
                <textarea v-model="form.notes" rows="2" :placeholder="$t('optional')" />
              </div>
            </div>
          </section>

          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary btn-block-mobile" type="submit">{{ $t('save') }}</button>
            <button v-if="editingId" class="btn btn-outline btn-block-mobile" type="button" @click="resetForm">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <h2>{{ $t('financeInvestmentHistory') }}</h2>
        <div v-if="!entries.length" class="empty">{{ $t('noData') }}</div>
        <div v-else class="table-scroll">
          <table class="report-table finance-table">
            <thead>
              <tr>
                <th>{{ $t('date') }}</th>
                <th>{{ $t('financeInvestor') }}</th>
                <th>{{ $t('amount') }}</th>
                <th>{{ $t('notes') }}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in entries" :key="row.id">
                <td>{{ dateOnly(row.invested_at) }}</td>
                <td><strong>{{ row.investor }}</strong></td>
                <td class="num"><strong>{{ formatMoney(row.amount) }}</strong></td>
                <td>{{ row.notes || '—' }}</td>
                <td class="finance-row-actions">
                  <button class="btn btn-outline btn-sm" type="button" @click="startEdit(row)">{{ $t('edit') }}</button>
                  <button class="btn btn-outline btn-sm" type="button" @click="remove(row)">{{ $t('delete') }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </DirectorShell>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { setDirectorFarmId, useDirectorFarmId } from '../../utils/directorFarm';
import { useRoute } from 'vue-router';
import DirectorShell from '../../components/DirectorShell.vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatMoney } from '../../utils/number';

export default {
  components: { DirectorShell },
  setup() {
    const route = useRoute();
    const farmId = useDirectorFarmId();
    const loading = ref(true);
    const farm = ref(null);
    const entries = ref([]);
    const total = ref(0);
    const editingId = ref(null);
    const error = ref('');
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      invested_at: today,
      investor: '',
      amount: '',
      notes: '',
    });

    function dateOnly(value) {
      return typeof value === 'string' ? value.slice(0, 10) : value;
    }

    function resetForm() {
      editingId.value = null;
      Object.assign(form, {
        invested_at: today,
        investor: '',
        amount: '',
        notes: '',
      });
      error.value = '';
    }

    function startEdit(row) {
      editingId.value = row.id;
      Object.assign(form, {
        invested_at: dateOnly(row.invested_at),
        investor: row.investor,
        amount: row.amount,
        notes: row.notes || '',
      });
      error.value = '';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function load() {
      loading.value = true;
      try {
        const [farmRes, entriesRes] = await Promise.all([
          window.axios.get(`/director/farms/${farmId.value}`),
          window.axios.get(`/director/farms/${farmId.value}/finance/investments`),
        ]);
        farm.value = farmRes.data;
        entries.value = entriesRes.data.data || [];
        total.value = entriesRes.data.total || 0;
      } finally {
        loading.value = false;
      }
    }

    async function save() {
      error.value = '';
      try {
        const payload = {
          invested_at: form.invested_at,
          investor: form.investor,
          amount: form.amount,
          notes: form.notes || null,
        };

        if (editingId.value) {
          await window.axios.put(`/director/farms/${farmId.value}/finance/investments/${editingId.value}`, payload);
        } else {
          await window.axios.post(`/director/farms/${farmId.value}/finance/investments`, payload);
        }

        showSaved(t('saved'));
        resetForm();
        await load();
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('noData'));
      }
    }

    async function remove(row) {
      if (!confirm(t('confirmDeleteReport'))) return;
      await window.axios.delete(`/director/farms/${farmId.value}/finance/investments/${row.id}`);
      if (editingId.value === row.id) resetForm();
      await load();
    }

    onMounted(load);

    return {
      farmId,
      farm,
      loading,
      entries,
      total,
      editingId,
      form,
      error,
      dateOnly,
      formatMoney,
      resetForm,
      startEdit,
      save,
      remove,
    };
  },
};
</script>
