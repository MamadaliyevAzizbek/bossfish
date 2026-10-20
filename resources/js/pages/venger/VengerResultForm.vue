<template>
  <div class="vsf venger-readable">
    <div class="page-head">
      <div>
        <h1>{{ isEdit ? $t('editVengerResult') : $t('recordVengerResult') }}</h1>
        <p>{{ clientName || $t('vengerSimpleFlowHint') }}</p>
      </div>
      <router-link class="btn btn-outline btn-sm" :to="backTo">{{ $t('back') }}</router-link>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <form v-else class="vsf__body" @submit.prevent="save(false)">
      <!-- Asosiy -->
      <section class="vsf-card">
        <h2>1. {{ $t('vengerStepBasics') }}</h2>
        <div class="vsf-fields">
          <div v-if="clientLocked" class="field">
            <label>{{ $t('vengerClient') }}</label>
            <input :value="clientName" disabled />
          </div>
          <div v-else class="field">
            <label>{{ $t('vengerClient') }}</label>
            <select v-model="form.client_id" required>
              <option disabled value="">{{ $t('selectClient') }}</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ $t('areaHa') }}</label>
            <FormattedNumberInput v-model="form.area_ha" required />
          </div>
          <div class="field">
            <label>{{ $t('resultTitleOptional') }}</label>
            <input v-model="form.title" maxlength="255" :placeholder="$t('vengerPondNamePh')" />
          </div>
          <div class="field vsf-feed-field">
            <label>{{ $t('vengerFeedTypesPick') }}</label>
            <p class="field-hint">{{ $t('vengerFeedTypesPickHint') }}</p>
            <div class="vsf-feed-rows">
              <div v-for="f in VENGER_FEED_TYPES" :key="f.id" class="vsf-feed-row">
                <span>{{ $t(f.labelKey) }}</span>
                <FormattedNumberInput v-model="feedKgMap[f.id]" :max-decimals="2" :placeholder="$t('vengerFeedKgPh')" />
                <em>kg</em>
              </div>
            </div>
            <p class="vsf-feed-total">
              {{ $t('vengerFeedTotalLabel') }}:
              <strong>{{ feedTotalDisplay }} kg</strong>
            </p>
          </div>
        </div>
      </section>

      <!-- Solish -->
      <section class="vsf-card">
        <h2>2. {{ $t('vengerSimpleStockTitle') }}</h2>
        <p class="field-hint">{{ $t('vengerSimpleStockHint') }}</p>

        <div class="vsf-fish-pick">
          <button
            v-for="type in FISH_TYPES"
            :key="type"
            type="button"
            class="vsf-chip"
            :class="{ on: enabled[type] }"
            @click="toggleFish(type)"
          >
            <FishTypeBadge :type="type" compact />
          </button>
        </div>

        <div v-for="type in enabledTypes" :key="`st-${type}`" class="vsf-block">
          <div class="vsf-block__title">
            <FishTypeBadge :type="type" compact />
            <span v-if="type === 'sazan'" class="vsf-tag">FCR</span>
          </div>
          <div class="field">
            <label>{{ $t('vengerWhenStocked') }}</label>
            <input v-model="fish[type].stocked_on" type="date" required />
          </div>
          <div class="field">
            <label>{{ $t('vengerHowManyFishApprox') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].stocked_pieces"
              integer-only
              @update:model-value="(v) => onLinked(type, 'stocked', 'stocked_pieces', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerAvgWeightG') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].stocked_avg_g"
              :max-decimals="1"
              @update:model-value="(v) => onLinked(type, 'stocked', 'stocked_avg_g', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerTotalKg') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].stocked_weight_kg"
              :max-decimals="3"
              required
              @update:model-value="(v) => onLinked(type, 'stocked', 'stocked_weight_kg', v)"
            />
          </div>
        </div>
      </section>

      <!-- Kontrol (ko‘p kartochka) -->
      <section class="vsf-card">
        <h2>3. {{ $t('vengerSimpleControlTitle') }}</h2>
        <p class="field-hint">{{ $t('vengerSimpleControlHint') }}</p>

        <div v-for="(ctrl, idx) in controls" :key="`c-${idx}`" class="vsf-block vsf-control">
          <div class="vsf-block__title">
            <strong>{{ $t('vengerControlN', { n: idx + 1 }) }}</strong>
            <button v-if="controls.length > 1" type="button" class="vsf-x" @click="removeControl(idx)"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
          </div>
          <div class="field">
            <label>{{ $t('vengerWhenChecked') }}</label>
            <input v-model="ctrl.checked_on" type="date" />
          </div>
          <div class="field">
            <label>{{ $t('vengerControlAvgG') }}</label>
            <FormattedNumberInput v-model="ctrl.avg_g" :max-decimals="1" />
          </div>
        </div>

        <button type="button" class="btn btn-outline vsf-add" @click="addControl">
          + {{ $t('vengerAddControl') }}
        </button>
      </section>

      <!-- Sotish / yo‘qotish -->
      <section class="vsf-card">
        <h2>4. {{ $t('vengerSimpleSaleTitle') }}</h2>
        <p class="field-hint">{{ $t('vengerSimpleSaleHint') }}</p>

        <div v-for="type in enabledTypes" :key="`sl-${type}`" class="vsf-block">
          <div class="vsf-block__title"><FishTypeBadge :type="type" compact /></div>

          <h3>{{ $t('soldBlock') }}</h3>
          <div class="field">
            <label>{{ $t('vengerWhenSold') }}</label>
            <input v-model="fish[type].sold_on" type="date" />
          </div>
          <div class="field">
            <label>{{ $t('vengerHowManyFishApprox') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].sold_pieces"
              integer-only
              @update:model-value="(v) => onLinked(type, 'sold', 'sold_pieces', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerAvgWeightG') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].sold_avg_g"
              :max-decimals="1"
              @update:model-value="(v) => onLinked(type, 'sold', 'sold_avg_g', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerTotalKg') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].sold_weight_kg"
              :max-decimals="3"
              @update:model-value="(v) => onLinked(type, 'sold', 'sold_weight_kg', v)"
            />
          </div>

          <h3>{{ $t('vengerStepLoss') }}</h3>
          <div class="field">
            <label>{{ $t('vengerHowManyFishApprox') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].loss_pieces"
              integer-only
              @update:model-value="(v) => onLinked(type, 'loss', 'loss_pieces', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerAvgWeightG') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].loss_avg_g"
              :max-decimals="1"
              @update:model-value="(v) => onLinked(type, 'loss', 'loss_avg_g', v)"
            />
          </div>
          <div class="field">
            <label>{{ $t('vengerTotalKg') }}</label>
            <FormattedNumberInput
              :model-value="fish[type].loss_weight_kg"
              :max-decimals="3"
              @update:model-value="(v) => onLinked(type, 'loss', 'loss_weight_kg', v)"
            />
          </div>
        </div>
      </section>

      <section class="vsf-card">
        <div class="field">
          <label>{{ $t('notes') }}</label>
          <textarea v-model="form.notes" rows="2" maxlength="5000" />
        </div>
      </section>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="vsf-sticky">
        <button class="btn btn-outline" type="submit" :disabled="saving || !enabledTypes.length">
          {{ saving ? $t('loading') : $t('vengerSaveSeeResult') }}
        </button>
        <button class="btn btn-primary" type="button" :disabled="saving || !enabledTypes.length" @click="save(true)">
          {{ $t('vengerConfirmClose') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FormattedNumberInput from '../../components/FormattedNumberInput.vue';
import FishTypeBadge from '../../components/FishTypeBadge.vue';
import { FISH_TYPES } from '../../utils/fish';
import {
  VENGER_FEED_TYPES,
  emptyFeedMap,
  feedsFromPayload,
  feedsToPayload,
  feedsTotalKg,
} from '../../utils/vengerFeeds';
import { avgGramsFrom, syncLinkedMass } from '../../utils/linkedMass';
import { t } from '../../i18n';

const BLOCK_KEYS = {
  stocked: { piecesKey: 'stocked_pieces', avgKey: 'stocked_avg_g', kgKey: 'stocked_weight_kg' },
  sold: { piecesKey: 'sold_pieces', avgKey: 'sold_avg_g', kgKey: 'sold_weight_kg' },
  loss: { piecesKey: 'loss_pieces', avgKey: 'loss_avg_g', kgKey: 'loss_weight_kg' },
};

function emptyFish() {
  return {
    stocked_on: '',
    stocked_pieces: '',
    stocked_avg_g: '',
    stocked_weight_kg: '',
    sold_on: '',
    sold_pieces: '',
    sold_avg_g: '',
    sold_weight_kg: '',
    loss_on: '',
    loss_pieces: '',
    loss_avg_g: '',
    loss_weight_kg: '',
  };
}

function emptyControl() {
  return { checked_on: '', avg_g: '' };
}

export default {
  components: { FormattedNumberInput, FishTypeBadge },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const loading = ref(true);
    const saving = ref(false);
    const error = ref('');
    const clients = ref([]);
    const clientName = ref('');
    const fixedClient = computed(() => !!route.params.clientId);
    const isEdit = computed(() => !!route.params.resultId);
    const clientLocked = computed(() => fixedClient.value || isEdit.value);

    const form = reactive({
      client_id: route.params.clientId ? Number(route.params.clientId) : '',
      title: '',
      area_ha: '',
      notes: '',
    });

    const controls = ref([]);
    const feedKgMap = reactive(emptyFeedMap());
    const enabled = reactive(Object.fromEntries(FISH_TYPES.map((type) => [type, false])));
    const fish = reactive(Object.fromEntries(FISH_TYPES.map((type) => [type, emptyFish()])));
    const editOrders = reactive(Object.fromEntries(
      FISH_TYPES.map((type) => [type, { stocked: [], sold: [], loss: [] }])
    ));
    const enabledTypes = computed(() => FISH_TYPES.filter((type) => enabled[type]));
    const feedTotalKg = computed(() => feedsTotalKg(feedKgMap));
    const feedTotalDisplay = computed(() => {
      const n = feedTotalKg.value;
      return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 2 }) : '0';
    });

    const backTo = computed(() => {
      if (form.client_id) return { name: 'venger-client', params: { clientId: form.client_id } };
      if (route.params.clientId) return { name: 'venger-client', params: { clientId: route.params.clientId } };
      return { name: 'venger-ponds' };
    });

    function onLinked(type, block, field, value) {
      fish[type][field] = value === null || value === undefined ? '' : String(value);
      editOrders[type][block] = syncLinkedMass(
        fish[type],
        field,
        editOrders[type][block] || [],
        BLOCK_KEYS[block]
      );
    }

    function toggleFish(type) {
      enabled[type] = !enabled[type];
      if (!enabled[type]) {
        Object.assign(fish[type], emptyFish());
        editOrders[type] = { stocked: [], sold: [], loss: [] };
      } else if (!fish[type].stocked_on) {
        fish[type].stocked_on = new Date().toISOString().slice(0, 10);
      }
    }

    function applyFeedRows(rows) {
      Object.assign(feedKgMap, emptyFeedMap(), feedsFromPayload(rows));
    }

    function addControl() {
      controls.value.push(emptyControl());
    }

    function removeControl(idx) {
      controls.value.splice(idx, 1);
    }

    function applyFishRows(rows) {
      FISH_TYPES.forEach((type) => {
        enabled[type] = false;
        Object.assign(fish[type], emptyFish());
        editOrders[type] = { stocked: [], sold: [], loss: [] };
      });
      (rows || []).forEach((row) => {
        const type = row.fish_type;
        if (!FISH_TYPES.includes(type)) return;
        enabled[type] = true;
        Object.assign(fish[type], {
          stocked_on: row.stocked_on || '',
          stocked_pieces: row.stocked_pieces ?? '',
          stocked_avg_g: avgGramsFrom(row.stocked_pieces, row.stocked_weight_kg),
          stocked_weight_kg: row.stocked_weight_kg ?? '',
          sold_on: row.sold_on || '',
          sold_pieces: row.sold_pieces ?? '',
          sold_avg_g: avgGramsFrom(row.sold_pieces, row.sold_weight_kg),
          sold_weight_kg: row.sold_weight_kg ?? '',
          loss_on: row.loss_on || '',
          loss_pieces: row.loss_pieces ?? '',
          loss_avg_g: avgGramsFrom(row.loss_pieces, row.loss_weight_kg),
          loss_weight_kg: row.loss_weight_kg ?? '',
        });
      });
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        if (isEdit.value) {
          const { data } = await window.axios.get(`/venger/results/${route.params.resultId}`);
          form.client_id = data.client?.id || '';
          clientName.value = data.client?.name || '';
          form.title = data.title || '';
          form.area_ha = data.area_ha;
          form.notes = data.notes || '';
          applyFishRows(data.fish || []);
          applyFeedRows(data.feeds || []);
          // Eski natijalar: feeds yo‘q — umumiy feed_kg yoki oxirgi kontrol yemidan yuklash
          if (feedsTotalKg(feedKgMap) <= 0) {
            if (Number(data.feed_kg) > 0) {
              feedKgMap.extruder = data.feed_kg;
            } else {
              const legacyFeed = [...(data.controls || [])]
                .reverse()
                .find((c) => Number(c.feed_kg) > 0);
              if (legacyFeed) feedKgMap.extruder = legacyFeed.feed_kg;
            }
          }
          controls.value = (data.controls || []).map((c) => ({
            checked_on: c.checked_on || '',
            avg_g: c.avg_g ?? '',
          }));
          // eski current_* dan bitta kontrol
          if (!controls.value.length) {
            const sazan = (data.fish || []).find((f) => f.fish_type === 'sazan');
            if (sazan?.current_on && sazan?.current_weight_kg) {
              controls.value = [{
                checked_on: sazan.current_on,
                avg_g: avgGramsFrom(sazan.current_pieces || sazan.stocked_pieces, sazan.current_weight_kg),
              }];
            }
          }
        } else {
          const { data } = await window.axios.get('/venger/clients');
          clients.value = data.clients || [];
          if (route.params.clientId) {
            form.client_id = Number(route.params.clientId);
            const c = clients.value.find((x) => String(x.id) === String(route.params.clientId));
            clientName.value = c?.name || '';
            if (!c) {
              const detail = await window.axios.get(`/venger/clients/${route.params.clientId}`);
              clientName.value = detail.data.name;
            }
          }
          enabled.sazan = true;
          fish.sazan.stocked_on = new Date().toISOString().slice(0, 10);
        }
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    function buildFishPayload() {
      return enabledTypes.value.map((type) => {
        const row = fish[type];
        const payload = {
          fish_type: type,
          stocked_on: row.stocked_on,
          stocked_pieces: Number(row.stocked_pieces || 0),
          stocked_weight_kg: Number(row.stocked_weight_kg),
          sold_pieces: Number(row.sold_pieces || 0),
          sold_weight_kg: Number(row.sold_weight_kg || 0),
          loss_pieces: Number(row.loss_pieces || 0),
          loss_weight_kg: Number(row.loss_weight_kg || 0),
        };
        if (row.sold_on) payload.sold_on = row.sold_on;
        return payload;
      });
    }

    function buildControlsPayload() {
      return controls.value
        .filter((c) => c.checked_on && Number(c.avg_g) > 0)
        .map((c) => ({
          checked_on: c.checked_on,
          avg_g: Number(c.avg_g),
          feed_kg: 0,
        }));
    }

    async function save(finalize) {
      saving.value = true;
      error.value = '';
      if (!enabledTypes.value.length) {
        error.value = t('selectAtLeastOneFish');
        saving.value = false;
        return;
      }
      const hasSold = enabledTypes.value.some((type) => Number(fish[type].sold_weight_kg) > 0);
      const typedFeedKg = feedTotalKg.value;
      if (finalize) {
        if (!hasSold) {
          error.value = t('vengerFinalizeNeedSale');
          saving.value = false;
          return;
        }
        if (!(typedFeedKg > 0)) {
          error.value = t('vengerFinalizeNeedFeed');
          saving.value = false;
          return;
        }
      }
      try {
        const controlsPayload = buildControlsPayload();
        const feedsPayload = feedsToPayload(feedKgMap);
        // Yem faqat yuqoridagi miqdorlar — FCR shu asosida
        const payload = {
          title: form.title || null,
          area_ha: Number(form.area_ha),
          feed_kg: typedFeedKg,
          feeds: feedsPayload,
          controls: controlsPayload,
          notes: form.notes || null,
          status: finalize ? 'final' : 'active',
          fish: buildFishPayload(),
        };

        let resultId;
        if (isEdit.value) {
          const { data } = await window.axios.put(`/venger/results/${route.params.resultId}`, payload);
          resultId = data.id;
        } else {
          const clientId = form.client_id || route.params.clientId;
          const { data } = await window.axios.post(`/venger/clients/${clientId}/results`, payload);
          resultId = data.id;
        }
        await router.push({ name: 'venger-result', params: { resultId } });
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || t('errorGeneric'));
      } finally {
        saving.value = false;
      }
    }

    onMounted(load);

    return {
      FISH_TYPES, VENGER_FEED_TYPES,
      loading, saving, error, clients, clientName, clientLocked, isEdit,
      form, controls, feedKgMap, feedTotalDisplay, enabled, fish, enabledTypes, backTo,
      toggleFish, addControl, removeControl, onLinked, save,
    };
  },
};
</script>

<style scoped>
.vsf__body { display: grid; gap: 0.85rem; padding-bottom: 5.5rem; }
.vsf-card {
  background: #fff;
  border: 1px solid rgba(20, 80, 50, 0.12);
  border-radius: 14px;
  padding: 1rem;
}
.vsf-card h2 { margin: 0 0 0.4rem; font-size: 1.08rem; }
.vsf-fields { display: grid; gap: 0.7rem; }
.vsf-sale-feed-note { margin: 0 0 0.75rem; }
.vsf-feed-field { grid-column: 1 / -1; }
.vsf-feed-rows {
  display: grid;
  gap: 0.55rem;
  margin-top: 0.45rem;
}
.vsf-feed-row {
  display: grid;
  grid-template-columns: 1fr minmax(110px, 160px) auto;
  gap: 0.45rem;
  align-items: center;
  padding: 0.55rem 0.65rem;
  border: 1px solid rgba(20, 80, 50, 0.14);
  border-radius: 10px;
  background: #f7faf8;
}
.vsf-feed-row span {
  font-weight: 700;
  color: #143226;
}
.vsf-feed-row em {
  font-style: normal;
  color: #5b7266;
  font-size: 0.88rem;
}
.vsf-feed-total {
  margin: 0.55rem 0 0;
  color: #3a5548;
  font-size: 0.9rem;
}
.vsf-feed-total strong {
  color: #0a6b40;
  font-size: 1.05rem;
}
.vsf-fish-pick {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin: 0.6rem 0 0.75rem;
}
.vsf-chip {
  border: 1px solid rgba(20, 80, 50, 0.18);
  background: #f5f8f6;
  border-radius: 10px;
  padding: 0.45rem 0.55rem;
  min-height: 48px;
  cursor: pointer;
}
.vsf-chip.on {
  border-color: #147a4d;
  background: #e8f6ef;
  box-shadow: 0 0 0 2px rgba(20, 122, 77, 0.15);
}
.vsf-block {
  border: 1px solid rgba(20, 80, 50, 0.1);
  border-radius: 12px;
  padding: 0.8rem;
  margin-bottom: 0.65rem;
  background: #fafcfb;
  display: grid;
  gap: 0.6rem;
}
.vsf-block h3 {
  margin: 0.35rem 0 0;
  font-size: 0.9rem;
  color: #147a4d;
}
.vsf-block__title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.vsf-tag {
  font-size: 0.68rem;
  font-weight: 800;
  background: #147a4d;
  color: #fff;
  padding: 0.15rem 0.4rem;
  border-radius: 6px;
}
.vsf-x {
  border: 0;
  background: transparent;
  font-size: 1.4rem;
  line-height: 1;
  color: #8a3a3a;
  cursor: pointer;
  padding: 0 0.25rem;
}
.vsf-add { width: 100%; min-height: 46px; margin-top: 0.25rem; }
.vsf-sticky {
  position: fixed;
  left: 0; right: 0; bottom: 0;
  z-index: 30;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  padding: 0.65rem 0.85rem calc(0.65rem + env(safe-area-inset-bottom, 0px));
  background: rgba(255,255,255,0.97);
  border-top: 1px solid rgba(20, 80, 50, 0.14);
}
@media (min-width: 901px) {
  .vsf-sticky { position: static; padding: 0; background: transparent; border: 0; }
  .vsf__body { padding-bottom: 0; }
  .vsf-fields { grid-template-columns: 1fr 1fr; }
  .vsf-fields .field:last-child { grid-column: 1 / -1; }
}
</style>
