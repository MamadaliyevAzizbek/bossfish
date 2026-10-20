<template>
  <div class="report-page-layout report-page-layout--daily">
    <div v-if="warehouseError" class="warehouse-alert" role="alert">
      {{ warehouseError }}
    </div>

    <section class="panel report-form-panel">
      <h2>{{ editingId ? $t('editReport') : $t('newReport') }}</h2>

      <form @submit.prevent="save" class="form-section-blocks fishery-form">
        <section class="form-section-block">
          <h3>{{ $t('date') }}</h3>
          <div class="form-grid">
            <div class="field form-wide">
              <label>{{ $t('date') }}</label>
              <input v-model="form.report_date" type="date" required />
            </div>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('warehouseFeed') }}</h3>
          <div v-if="!warehouseOptions.feed.length" class="report-notice">
            {{ $t('warehouseFeedEmpty') }}
          </div>
          <div v-else class="form-grid">
            <div class="field">
              <label>{{ $t('feedName') }} <small class="field-hint">({{ $t('optional') }})</small></label>
              <select v-model="form.feed_selection">
                <option value="">{{ $t('optional') }}</option>
                <option v-for="feed in warehouseOptions.feed" :key="feed.id" :value="feed.name">
                  {{ feed.name }} ({{ stockLabel(feed) }})
                </option>
              </select>
            </div>

            <div v-if="form.feed_selection" class="field">
              <label>{{ $t('feedAmountKg') }}</label>
              <FormattedNumberInput
                v-model="form.feeding_kg"
                :max-decimals="2"
                required
              />
              <small v-if="selectedFeedStock != null" class="field-hint">
                {{ $t('warehouseFeedStock', { qty: formatKg(selectedFeedStock) }) }}
              </small>
            </div>
          </div>
        </section>

        <section v-if="warehouseOptions.bran.length" class="form-section-block">
          <h3>{{ $t('wheatBranKg') }}</h3>
          <div class="form-grid">
            <div class="field">
              <label>{{ $t('warehouseChooseProduct') }}</label>
              <select v-model="form.wheat_item_id">
                <option value="">{{ $t('optional') }}</option>
                <option v-for="item in warehouseOptions.bran" :key="item.id" :value="String(item.id)">
                  {{ item.name }} ({{ stockLabel(item) }})
                </option>
              </select>
            </div>

            <div v-if="form.wheat_item_id" class="field">
              <label>{{ $t('quantity') }}</label>
              <FormattedNumberInput
                v-model="form.wheat_bran_kg"
                :max-decimals="2"
                required
              />
              <small v-if="selectedBranStock != null" class="field-hint">
                {{ $t('warehouseFeedStock', { qty: formatKg(selectedBranStock) }) }}
              </small>
            </div>
          </div>
        </section>

        <section v-if="warehouseOptions.fertilizer.length" class="form-section-block">
          <h3>{{ $t('fertilizer') }}</h3>
          <div class="form-grid">
            <div class="field">
              <label>{{ $t('warehouseChooseProduct') }}</label>
              <select v-model="form.fertilizer_item_id">
                <option value="">{{ $t('optional') }}</option>
                <option v-for="item in warehouseOptions.fertilizer" :key="item.id" :value="String(item.id)">
                  {{ item.name }} ({{ stockLabel(item) }})
                </option>
              </select>
              <small class="field-hint">{{ $t('fertilizerHint') }}</small>
            </div>

            <div v-if="form.fertilizer_item_id" class="field">
              <label>{{ $t('quantity') }}</label>
              <FormattedNumberInput
                v-model="form.fertilizer_kg"
                :max-decimals="2"
                required
              />
              <small v-if="selectedFertilizerStock != null" class="field-hint">
                {{ $t('warehouseFeedStock', { qty: formatKg(selectedFertilizerStock) }) }}
              </small>
            </div>
          </div>
        </section>

        <section class="form-section-block">
          <h3>{{ $t('warehouseMedicine') }}</h3>

          <div v-if="!warehouseOptions.medicines.length" class="report-notice">
            {{ $t('warehouseMedicineEmpty') }}
          </div>

          <div v-else class="form-grid">
            <div class="field">
              <label>{{ $t('warehouseMedicineName') }}</label>
              <select v-model="form.medicine_item_id">
                <option value="">{{ $t('optional') }}</option>
                <option v-for="item in warehouseOptions.medicines" :key="item.id" :value="String(item.id)">
                  {{ item.name }} ({{ stockLabel(item) }})
                </option>
              </select>
            </div>

            <div v-if="form.medicine_item_id" class="field">
              <label>{{ selectedMedicineQuantityLabel }}</label>
              <FormattedNumberInput
                v-model="form.medicine_qty"
                :max-decimals="2"
                required
              />
              <small v-if="selectedMedicineStock != null" class="field-hint">
                {{ $t('warehouseStockLeft', { qty: formatMedicineStockLabel }) }}
              </small>
            </div>
          </div>
        </section>

        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="!canSubmit && !error" class="field-hint">{{ $t('dailyReportNeedSomething') }}</p>

        <div class="actions">
          <button class="btn btn-primary btn-block-mobile" type="submit" :disabled="saving || !canSubmit">
            {{ saving ? $t('loading') : $t('save') }}
          </button>
          <button v-if="editingId" class="btn btn-outline btn-block-mobile" type="button" @click="resetForm">
            {{ $t('cancel') }}
          </button>
        </div>
      </form>
    </section>

    <div class="report-history-toggle-wrap report-history-toggle-wrap--daily">
      <button
        class="btn btn-outline btn-block-mobile report-history-toggle report-history-toggle--daily"
        type="button"
        :aria-expanded="showHistory"
        @click="showHistory = !showHistory"
      >
        {{ showHistory ? $t('dailyReportHideHistory') : $t('dailyReportShowHistory') }}
        <span v-if="reports.length" class="report-history-count report-history-count--daily">{{ reports.length }}</span>
      </button>
    </div>

    <section v-if="showHistory" class="panel report-history-panel report-history-panel--daily">
      <h2>
        <span class="report-history-accent report-history-accent--daily" aria-hidden="true" />
        {{ $t('previousReports') }}
      </h2>
      <p class="field-hint report-history-hint">{{ $t('previousReportsHint') }}</p>
      <div v-if="!reports.length" class="empty">{{ $t('noReports') }}</div>
      <div v-else class="table-scroll report-history-scroll">
        <table class="report-table finance-table report-history-table report-history-table--daily">
          <thead>
            <tr>
              <th>{{ $t('date') }}</th>
              <th>{{ $t('feedName') }}</th>
              <th>{{ $t('feedAmountKg') }}</th>
              <th>{{ $t('wheatBranKg') }}</th>
              <th>{{ $t('fertilizer') }}</th>
              <th>{{ $t('dailyReportMedicineUsed') }}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="report in reports" :key="report.id">
              <td>{{ dateOnly(report.report_date) }}</td>
              <td><strong>{{ report.feed_name || '—' }}</strong></td>
              <td class="num">{{ kg(report.feeding_kg) }}</td>
              <td class="num">{{ kg(report.wheat_bran_kg) }}</td>
              <td class="num">{{ kg(report.fertilizer_kg) }}</td>
              <td>{{ medicineSummary(report) }}</td>
              <td class="finance-row-actions">
                <button class="btn btn-outline btn-sm" type="button" @click="startEdit(report)">{{ $t('edit') }}</button>
                <button class="btn btn-outline btn-sm" type="button" @click="remove(report)">{{ $t('delete') }}</button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2"><strong>{{ $t('total') }}</strong></td>
              <td class="num"><strong>{{ kg(totalFeedKg) }}</strong></td>
              <td class="num"><strong>{{ kg(totalBranKg) }}</strong></td>
              <td class="num"><strong>{{ kg(totalFertilizerKg) }}</strong></td>
              <td colspan="2" />
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { showSaved } from '../flash';
import { t } from '../i18n';
import { formatKg } from '../utils/number';

const EMPTY_WAREHOUSE = () => ({
  feed: [],
  bran: [],
  fertilizer: [],
  medicines: [],
});

function isFertilizerName(name) {
  return /amafos|slitra|selitra|ammophos/i.test(String(name || ''));
}

function isBranOrWheat(name) {
  const value = String(name || '').toLowerCase();
  return /kepak|\bun\b/.test(value) || value === "bug'doy";
}

export default {
  props: {
    pondId: { type: [String, Number], required: true },
    apiPrefix: { type: String, default: '/director' },
  },
  emits: ['saved'],
  setup(props, { emit }) {
    const reports = ref([]);
    const warehouseOptions = ref(EMPTY_WAREHOUSE());
    const error = ref('');
    const warehouseError = ref('');
    const saving = ref(false);
    const editingId = ref(null);
    const showHistory = ref(false);
    const today = new Date().toISOString().slice(0, 10);
    const form = reactive({
      report_date: today,
      feed_selection: '',
      feeding_kg: '',
      wheat_item_id: '',
      wheat_bran_kg: '',
      fertilizer_item_id: '',
      fertilizer_kg: '',
      medicine_item_id: '',
      medicine_qty: '',
    });

    const dateOnly = (value) => (typeof value === 'string' ? value.slice(0, 10) : value);
    const kg = (value) => (value ? `${formatKg(value)} kg` : '—');

    const selectedFeedStock = computed(() => stockForName(form.feed_selection, warehouseOptions.value.feed));
    const selectedBranStock = computed(() => stockForId(form.wheat_item_id, warehouseOptions.value.bran));
    const selectedFertilizerStock = computed(() => stockForId(form.fertilizer_item_id, warehouseOptions.value.fertilizer));
    const selectedMedicineStock = computed(() => stockForId(form.medicine_item_id, warehouseOptions.value.medicines));
    const selectedMedicineItem = computed(() => (
      warehouseOptions.value.medicines.find((item) => String(item.id) === String(form.medicine_item_id)) || null
    ));
    const selectedMedicineQuantityLabel = computed(() => {
      if (selectedMedicineItem.value?.unit === 'piece') {
        return `${t('quantity')} (${t('pieces')})`;
      }
      return `${t('quantity')} (kg)`;
    });
    const formatMedicineStockLabel = computed(() => {
      if (selectedMedicineStock.value == null) return '';
      const unit = selectedMedicineItem.value?.unit === 'piece' ? t('pieces') : 'kg';
      return `${formatKg(selectedMedicineStock.value)} ${unit}`;
    });
    const canSubmit = computed(() => {
      const hasFeed = !!form.feed_selection && Number(form.feeding_kg) > 0;
      const hasBran = !!form.wheat_item_id && Number(form.wheat_bran_kg) > 0;
      const hasFertilizer = !!form.fertilizer_item_id && Number(form.fertilizer_kg) > 0;
      const hasMedicine = !!form.medicine_item_id && Number(form.medicine_qty) > 0;
      return hasFeed || hasBran || hasFertilizer || hasMedicine;
    });

    function stockForName(name, items) {
      const item = items.find((row) => row.name === name);
      return item != null ? Number(item.quantity) : null;
    }

    function stockForId(id, items) {
      if (!id) return null;
      const item = items.find((row) => String(row.id) === String(id));
      return item != null ? Number(item.quantity) : null;
    }

    function stockLabel(item) {
      const unit = item.unit === 'piece' ? t('pieces') : 'kg';
      return `${formatKg(item.quantity)} ${unit}`;
    }

    function formatUsage(line) {
      const unit = line.warehouse_item?.unit === 'piece' ? t('pieces') : 'kg';
      return `${formatKg(line.quantity)} ${unit}`;
    }

    function reportMedicines(report) {
      return (report.items || []).filter(
        (line) => line.warehouse_item?.category === 'medicine' && !isFertilizerName(line.warehouse_item?.name)
      );
    }

    function medicineSummary(report) {
      const lines = reportMedicines(report);
      if (!lines.length) return '—';
      return lines.map((line) => `${line.warehouse_item?.name || '—'} (${formatUsage(line)})`).join(', ');
    }

    const totalFeedKg = computed(() => reports.value.reduce((sum, row) => sum + (Number(row.feeding_kg) || 0), 0));
    const totalBranKg = computed(() => reports.value.reduce((sum, row) => sum + (Number(row.wheat_bran_kg) || 0), 0));
    const totalFertilizerKg = computed(() => reports.value.reduce((sum, row) => sum + (Number(row.fertilizer_kg) || 0), 0));

    async function loadWarehouseOptions(reportId = null) {
      try {
        const params = reportId ? { daily_report_id: reportId } : {};
        const { data } = await window.axios.get(`${props.apiPrefix}/ponds/${props.pondId}/warehouse/available`, { params });
        warehouseOptions.value = {
          feed: data.feed || [],
          bran: data.bran || [],
          fertilizer: data.fertilizer || [],
          medicines: data.medicines || [],
        };
      } catch (e) {
        warehouseOptions.value = EMPTY_WAREHOUSE();
      }
    }

    function resetForm() {
      editingId.value = null;
      Object.assign(form, {
        report_date: today,
        feed_selection: '',
        feeding_kg: '',
        wheat_item_id: '',
        wheat_bran_kg: '',
        fertilizer_item_id: '',
        fertilizer_kg: '',
        medicine_item_id: '',
        medicine_qty: '',
      });
      error.value = '';
      warehouseError.value = '';
      loadWarehouseOptions();
    }

    function mapReportItems(report) {
      const items = report.items || [];
      const branItem = items.find(
        (line) => line.warehouse_item?.category === 'feed' && isBranOrWheat(line.warehouse_item?.name)
      );
      const fertItem = items.find(
        (line) => line.warehouse_item?.category === 'medicine' && isFertilizerName(line.warehouse_item?.name)
      );
      const medItems = items.filter(
        (line) => line.warehouse_item?.category === 'medicine' && !isFertilizerName(line.warehouse_item?.name)
      );

      return {
        wheat_item_id: branItem ? String(branItem.warehouse_item_id) : '',
        wheat_bran_kg: report.wheat_bran_kg ?? '',
        fertilizer_item_id: fertItem ? String(fertItem.warehouse_item_id) : '',
        fertilizer_kg: report.fertilizer_kg ?? '',
        medicine_item_id: medItems[0] ? String(medItems[0].warehouse_item_id) : '',
        medicine_qty: medItems[0]?.quantity ?? '',
      };
    }

    function startEdit(report) {
      editingId.value = report.id;
      const feedName = report.feed_name || '';
      const mapped = mapReportItems(report);

      Object.assign(form, {
        report_date: dateOnly(report.report_date),
        feed_selection: feedName,
        feeding_kg: report.feeding_kg ?? '',
        ...mapped,
      });
      error.value = '';
      warehouseError.value = '';

      loadWarehouseOptions(report.id).then(() => {
        if (feedName && !warehouseOptions.value.feed.some((feed) => feed.name === feedName)) {
          warehouseOptions.value.feed = [
            ...warehouseOptions.value.feed,
            { id: `edit-${report.id}`, name: feedName, quantity: Number(form.feeding_kg) || 0, unit: 'kg' },
          ].sort((a, b) => a.name.localeCompare(b.name, 'uz'));
        }
      });

      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function extractWarehouseError(responseData) {
      const errors = responseData?.errors;
      if (errors?.warehouse) {
        return Array.isArray(errors.warehouse) ? errors.warehouse.join(' ') : String(errors.warehouse);
      }
      if (errors?.items) {
        return Array.isArray(errors.items) ? errors.items.join(' ') : String(errors.items);
      }
      if (errors) {
        const text = Object.values(errors).flat().join(' ');
        if (text.includes('omborga kirim') || text.includes('omborda')) {
          return text;
        }
      }
      return '';
    }

    function validateStock() {
      if (selectedFeedStock.value != null && Number(form.feeding_kg) > selectedFeedStock.value) {
        warehouseError.value = t('warehouseFeedExceedsStock', { qty: formatKg(selectedFeedStock.value) });
        return false;
      }

      if (form.wheat_item_id && selectedBranStock.value != null && Number(form.wheat_bran_kg) > selectedBranStock.value) {
        warehouseError.value = t('warehouseFeedExceedsStock', { qty: formatKg(selectedBranStock.value) });
        return false;
      }

      if (form.fertilizer_item_id && selectedFertilizerStock.value != null && Number(form.fertilizer_kg) > selectedFertilizerStock.value) {
        warehouseError.value = t('warehouseFeedExceedsStock', { qty: formatKg(selectedFertilizerStock.value) });
        return false;
      }

      if (form.medicine_item_id && selectedMedicineStock.value != null && Number(form.medicine_qty) > selectedMedicineStock.value) {
        warehouseError.value = t('warehouseFeedExceedsStock', { qty: formatKg(selectedMedicineStock.value) });
        return false;
      }

      return true;
    }

    async function load() {
      try {
        const reportsResponse = await window.axios.get(`${props.apiPrefix}/ponds/${props.pondId}/daily-reports`);
        reports.value = reportsResponse.data.data || reportsResponse.data;
        await loadWarehouseOptions(editingId.value);
      } catch (e) {
        reports.value = [];
        warehouseOptions.value = EMPTY_WAREHOUSE();
        error.value = e.response?.data?.message || t('noData');
      }
    }

    async function save() {
      error.value = '';
      warehouseError.value = '';

      if (!validateStock()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      saving.value = true;
      try {
        const payload = {
          report_date: form.report_date,
          feed_name: form.feed_selection || null,
          feeding_kg: form.feed_selection && form.feeding_kg ? Number(form.feeding_kg) : null,
          wheat_item_id: form.wheat_item_id ? Number(form.wheat_item_id) : null,
          wheat_bran_kg: form.wheat_item_id && form.wheat_bran_kg ? Number(form.wheat_bran_kg) : null,
          fertilizer_item_id: form.fertilizer_item_id ? Number(form.fertilizer_item_id) : null,
          fertilizer_kg: form.fertilizer_item_id && form.fertilizer_kg ? Number(form.fertilizer_kg) : null,
          medicine_item_id: form.medicine_item_id ? Number(form.medicine_item_id) : null,
          medicine_qty: form.medicine_item_id && form.medicine_qty ? Number(form.medicine_qty) : null,
        };

        if (editingId.value) {
          await window.axios.put(`${props.apiPrefix}/ponds/${props.pondId}/daily-reports/${editingId.value}`, payload);
        } else {
          await window.axios.post(`${props.apiPrefix}/ponds/${props.pondId}/daily-reports`, payload);
        }

        showSaved(t('saved'));
        resetForm();
        await load();
        emit('saved');
      } catch (e) {
        const responseData = e.response?.data;
        const warehouseMsg = extractWarehouseError(responseData);
        if (warehouseMsg) {
          warehouseError.value = warehouseMsg;
          error.value = '';
        } else {
          const errors = responseData?.errors;
          error.value = errors
            ? Object.values(errors).flat().join(' ')
            : (responseData?.message || t('noData'));
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } finally {
        saving.value = false;
      }
    }

    async function remove(report) {
      if (!confirm(t('confirmDeleteReport'))) return;
      await window.axios.delete(`${props.apiPrefix}/ponds/${props.pondId}/daily-reports/${report.id}`);
      if (editingId.value === report.id) resetForm();
      await load();
    }

    onMounted(load);

    watch(() => form.feed_selection, (value) => {
      if (!value) {
        form.feeding_kg = '';
        return;
      }
      if (selectedFeedStock.value != null && Number(form.feeding_kg) > selectedFeedStock.value) {
        form.feeding_kg = String(selectedFeedStock.value);
      }
    });

    watch(() => form.wheat_item_id, (value) => {
      if (!value) {
        form.wheat_bran_kg = '';
        return;
      }
      if (selectedBranStock.value != null && Number(form.wheat_bran_kg) > selectedBranStock.value) {
        form.wheat_bran_kg = String(selectedBranStock.value);
      }
    });

    watch(() => form.fertilizer_item_id, (value) => {
      if (!value) {
        form.fertilizer_kg = '';
        return;
      }
      if (selectedFertilizerStock.value != null && Number(form.fertilizer_kg) > selectedFertilizerStock.value) {
        form.fertilizer_kg = String(selectedFertilizerStock.value);
      }
    });

    watch(() => form.medicine_item_id, (value) => {
      if (!value) {
        form.medicine_qty = '';
        return;
      }
      if (selectedMedicineStock.value != null && Number(form.medicine_qty) > selectedMedicineStock.value) {
        form.medicine_qty = String(selectedMedicineStock.value);
      }
    });

    watch(() => props.pondId, async () => {
      resetForm();
      await load();
    });

    return {
      reports,
      warehouseOptions,
      form,
      error,
      warehouseError,
      saving,
      editingId,
      showHistory,
      selectedFeedStock,
      selectedBranStock,
      selectedFertilizerStock,
      selectedMedicineStock,
      selectedMedicineQuantityLabel,
      formatMedicineStockLabel,
      canSubmit,
      save,
      startEdit,
      resetForm,
      remove,
      reportMedicines,
      medicineSummary,
      totalFeedKg,
      totalBranKg,
      totalFertilizerKg,
      dateOnly,
      kg,
      formatKg,
      stockLabel,
      formatUsage,
    };
  },
};
</script>
