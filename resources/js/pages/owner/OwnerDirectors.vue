<template>
  <div class="owner-directors-page owner-clients-page">
    <div class="page-head">
      <div>
        <h1>{{ $t('ownerClientsTitle') }}</h1>
        <p>{{ $t('ownerClientsUnifiedHint') }}</p>
      </div>
      <button class="btn btn-primary" type="button" @click="openForm">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        {{ $t('addDirector') }}
      </button>
    </div>

    <div class="sec-kpi">
      <div class="sec-kpi__card" role="button" tabindex="0" @click="clearFocusFilters">
        <span>{{ $t('ownerClientsTotal') }}</span>
        <strong>{{ number(summary.total) }}</strong>
      </div>
      <div
        class="sec-kpi__card"
        :class="{ 'sec-kpi__card--warn': summary.due_checks > 0, 'is-active': dueOnly }"
        role="button"
        tabindex="0"
        @click="toggleDue"
      >
        <span>{{ $t('ownerClientsDue') }}</span>
        <strong>{{ number(summary.due_checks) }}</strong>
      </div>
      <div
        class="sec-kpi__card"
        :class="{ 'sec-kpi__card--warn': summary.attention > 0, 'is-active': subStatusFilter === 'attention' }"
        role="button"
        tabindex="0"
        @click="toggleAttention"
      >
        <span>{{ $t('ownerClientsAttention') }}</span>
        <strong>{{ number(summary.attention) }}</strong>
      </div>
      <div
        class="sec-kpi__card"
        :class="{ 'sec-kpi__card--warn': summary.expiring > 0, 'is-active': expiringOnly }"
        role="button"
        tabindex="0"
        @click="toggleExpiring"
      >
        <span>{{ $t('ownerClientsExpiring') }}</span>
        <strong>{{ number(summary.expiring) }}</strong>
      </div>
      <div
        class="sec-kpi__card"
        :class="{ 'is-active': noPlanOnly }"
        role="button"
        tabindex="0"
        @click="toggleNoPlan"
      >
        <span>{{ $t('ownerClientsNoPlan') }}</span>
        <strong>{{ number(summary.without_plan) }}</strong>
      </div>
    </div>

    <div class="owner-filter-bar panel">
      <div class="field">
        <label>{{ $t('ownerSearchNamePhone') }}</label>
        <input
          v-model="searchQ"
          type="search"
          :placeholder="$t('ownerSearchNamePhonePh')"
          @input="onSearchInput"
        />
      </div>
      <div class="field">
        <label>{{ $t('filterByRegion') }}</label>
        <select v-model="regionFilter" @change="load">
          <option value="">{{ $t('allRegions') }}</option>
          <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
        </select>
      </div>
      <div class="field">
        <label>{{ $t('ownerClientsPlan') }}</label>
        <select v-model="planFilter" @change="onPlanFilterChange">
          <option value="">{{ $t('all') }}</option>
          <option value="cabinet">{{ $t('homePlanCabinetName') }}</option>
          <option value="control">{{ $t('homePlanControlName') }}</option>
          <option value="individual">{{ $t('homePlanIndividualName') }}</option>
        </select>
      </div>
      <div class="field field--count">
        <label>{{ $t('status') }}</label>
        <span class="owner-filter-count">{{ number(directors.length) }}</span>
      </div>
    </div>

    <div class="owner-clients-grid">
      <article
        v-for="d in directors"
        :key="d.id"
        class="owner-client-card"
        :class="{
          'is-due': d.subscription?.needs_check || d.subscription?.status === 'attention',
          'is-expiring': isExpiring(d) && !d.access_expired,
          'is-expired': !!d.access_expired,
          'is-loss': Number(d.profit) < 0,
          'is-no-plan': !d.subscription,
        }"
      >
        <div class="owner-client-card__head">
          <div>
            <h3>{{ d.name }}</h3>
            <p class="phone-cell">{{ formatPhone(d.phone) }}</p>
          </div>
          <div class="owner-client-card__badges">
            <span class="badge" :class="d.is_active ? 'active' : 'inactive'">
              {{ d.is_active ? $t('active') : $t('inactive') }}
            </span>
            <span v-if="d.access_expired" class="badge inactive">{{ $t('accessExpired') }}</span>
            <span v-else-if="isExpiring(d)" class="badge pending">
              {{ $t('daysLeftShort', { days: d.access_days_left ?? 0 }) }}
            </span>
            <span v-if="d.subscription" class="badge" :class="planBadgeClass(d.subscription.plan)">
              {{ planLabel(d.subscription.plan) }}
            </span>
            <span v-else class="badge pending">{{ $t('ownerClientsNoPlan') }}</span>
          </div>
        </div>

        <div class="owner-client-card__body">
          <div class="owner-client-stat">
            <span>{{ $t('ownerClientsAccessUntil') }}</span>
            <strong :class="{ 'is-warn': isExpiring(d) || d.access_expired }">
              <template v-if="d.access_expired">{{ $t('accessExpired') }}</template>
              <template v-else-if="d.subscription?.ends_at || d.access_expires_at">
                {{ d.subscription?.ends_at || (d.access_expires_at || '').slice(0, 10) }}
                <em v-if="d.access_days_left != null">({{ $t('daysLeftShort', { days: d.access_days_left }) }})</em>
              </template>
              <template v-else>—</template>
            </strong>
          </div>
          <div class="owner-client-stat">
            <span>{{ $t('ownerClientsPeriod') }}</span>
            <strong>
              <template v-if="d.subscription">
                {{ periodLabel(d.subscription.billing_period) }}
              </template>
              <template v-else>—</template>
            </strong>
          </div>
          <div class="owner-client-stat">
            <span>{{ $t('region') }}</span>
            <strong>{{ d.regions?.length ? d.regions.join(', ') : '—' }}</strong>
          </div>
          <div class="owner-client-stat">
            <span>{{ $t('totalPondArea') }}</span>
            <strong>{{ formatArea(d.ponds_total_area_m2) }} {{ $t('hectare') }}</strong>
          </div>
          <div class="owner-client-stat owner-client-stat--wide">
            <span>{{ $t('ownerDirectorFinance') }}</span>
            <strong :class="profitClass(d.profit)">
              {{ profitLabel(d.profit) }} · {{ formatMoney(Math.abs(Number(d.profit) || 0)) }}
            </strong>
          </div>
          <div
            v-if="d.subscription && d.subscription.plan !== 'cabinet'"
            class="owner-client-stat"
          >
            <span>{{ $t('ownerClientsNextCheck') }}</span>
            <strong :class="{ 'is-warn': d.subscription.needs_check }">
              {{ d.subscription.next_check_at || $t('ownerClientsCheckMissing') }}
            </strong>
          </div>
          <p v-if="d.subscription?.attention_note" class="owner-client-note">
            {{ d.subscription.attention_note }}
          </p>
        </div>

        <div class="owner-client-card__actions">
          <button class="btn btn-primary" type="button" @click="enterDirector(d)">
            {{ $t('manageDirector') }}
          </button>
          <button class="btn btn-outline" type="button" @click="openPlan(d)">
            {{ d.subscription ? $t('ownerClientsEdit') : $t('ownerClientsAdd') }}
          </button>
          <button
            v-if="d.subscription && (d.subscription.plan === 'control' || d.subscription.plan === 'individual')"
            class="btn btn-outline"
            type="button"
            @click="markChecked(d)"
          >
            {{ $t('ownerClientsMarkChecked') }}
          </button>
        </div>
      </article>

      <div v-if="!directors.length" class="panel empty owner-clients-empty">
        {{ searchActive ? $t('ownerSearchEmpty') : $t('noDirectors') }}
      </div>
    </div>

    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false">
      <div class="modal">
        <h2 class="modal-title">{{ $t('newDirector') }}</h2>
        <p class="field-hint" style="margin-top: -0.35rem">{{ $t('ownerClientsCreateHint') }}</p>
        <form @submit.prevent="create" class="form-grid one-col">
          <div class="field"><label>{{ $t('name') }}</label><input v-model="form.name" required /></div>
          <div class="field">
            <label>{{ $t('phoneLogin') }}</label>
            <PhoneInput v-model="form.phone" required />
          </div>
          <div class="field"><label>{{ $t('shortPassword') }}</label><input v-model="form.password" type="password" required minlength="4" /></div>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $t('save') }}</button>
            <button class="btn btn-outline" type="button" @click="showForm = false">{{ $t('cancel') }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="planOpen" class="modal-backdrop" @click.self="closePlan">
      <div class="modal panel owner-clients-modal">
        <div class="page-head">
          <div>
            <h2>{{ planForm.id ? $t('ownerClientsEdit') : $t('ownerClientsAdd') }}</h2>
            <p>{{ planForm.director_name }} — {{ $t('ownerClientsFormHint') }}</p>
          </div>
          <button class="btn btn-outline" type="button" @click="closePlan">{{ $t('cancel') }}</button>
        </div>

        <form class="owner-clients-form" @submit.prevent="savePlan">
          <div class="field">
            <label>{{ $t('ownerClientsPlan') }}</label>
            <div class="owner-period-toggle owner-plan-pick">
              <button
                v-for="p in planOptions"
                :key="p.id"
                type="button"
                class="owner-period-toggle__btn"
                :class="{ 'is-active': planForm.plan === p.id }"
                @click="pickPlan(p.id)"
              >
                {{ p.label }}
              </button>
            </div>
          </div>

          <div class="field">
            <label>{{ $t('ownerClientsPeriod') }}</label>
            <div class="owner-period-toggle">
              <button
                type="button"
                class="owner-period-toggle__btn"
                :class="{ 'is-active': planForm.billing_period === 'month' }"
                @click="pickPeriod('month')"
              >
                {{ $t('ownerClientsPeriodMonth') }}
              </button>
              <button
                type="button"
                class="owner-period-toggle__btn"
                :class="{ 'is-active': planForm.billing_period === 'year' }"
                @click="pickPeriod('year')"
              >
                {{ $t('ownerClientsPeriodYear') }}
              </button>
            </div>
          </div>

          <div class="field">
            <label>{{ $t('ownerClientsPrice') }}</label>
            <input v-model.number="planForm.price_amount" type="number" min="0" step="1000" :placeholder="$t('ownerClientsPricePh')" />
          </div>

          <div class="owner-clients-form__row">
            <div class="field">
              <label>{{ $t('ownerClientsStarts') }}</label>
              <input v-model="planForm.starts_at" type="date" required @change="recalcEnds" />
            </div>
            <div class="field">
              <label>{{ $t('ownerClientsAccessUntil') }}</label>
              <input v-model="planForm.ends_at" type="date" required />
              <small class="field-hint">{{ $t('ownerClientsAccessSyncHint') }}</small>
            </div>
          </div>

          <div v-if="planForm.plan !== 'cabinet'" class="owner-clients-form__row">
            <div class="field">
              <label>{{ $t('ownerClientsNextCheck') }}</label>
              <input v-model="planForm.next_check_at" type="date" />
            </div>
            <div class="field">
              <label>{{ $t('ownerClientsInterval') }}</label>
              <input v-model.number="planForm.check_interval_days" type="number" min="1" max="90" />
            </div>
          </div>

          <div class="field">
            <label>{{ $t('status') }}</label>
            <select v-model="planForm.status">
              <option value="active">{{ $t('active') }}</option>
              <option value="attention">{{ $t('ownerClientsStatusAttention') }}</option>
              <option value="paused">{{ $t('ownerClientsStatusPaused') }}</option>
              <option value="expired">{{ $t('ownerClientsStatusExpired') }}</option>
            </select>
          </div>

          <div v-if="planForm.status === 'attention'" class="field">
            <label>{{ $t('ownerClientsAttentionNote') }}</label>
            <input v-model="planForm.attention_note" type="text" maxlength="500" />
          </div>

          <div class="field">
            <label>{{ $t('notes') }}</label>
            <textarea v-model="planForm.notes" rows="2" />
          </div>

          <p v-if="planError" class="error">{{ planError }}</p>

          <div class="owner-clients-actions">
            <button class="btn btn-outline" type="button" @click="closePlan">{{ $t('cancel') }}</button>
            <button class="btn btn-primary" type="submit" :disabled="planSaving">
              {{ planSaving ? $t('saving') : $t('save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PhoneInput from '../../components/PhoneInput.vue';
import { formatPhoneDisplay, isCompletePhone, toFullPhone } from '../../utils/phone';
import { normalizeRegions } from '../../utils/regionCoordinates';
import { setActingDirector } from '../../utils/ownerContext';
import { formatMoney } from '../../utils/number';
import { i18nState, t } from '../../i18n';

function todayPlus(days) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

function defaultPrice(plan, period) {
  if (plan === 'individual') return null;
  if (period === 'month') {
    return plan === 'control' ? 450000 : 135000;
  }
  return plan === 'control' ? 4299000 : 1299000;
}

function periodDays(period) {
  return period === 'month' ? 30 : 365;
}

function emptyPlanForm() {
  return {
    id: null,
    director_id: '',
    director_name: '',
    plan: 'cabinet',
    billing_period: 'year',
    price_amount: 1299000,
    starts_at: todayPlus(0),
    ends_at: todayPlus(365),
    next_check_at: '',
    check_interval_days: 7,
    status: 'active',
    attention_note: '',
    notes: '',
  };
}

export default {
  components: { PhoneInput },
  setup() {
    const router = useRouter();
    const route = useRoute();
    const directors = ref([]);
    const allForSummary = ref([]);
    const regions = ref([]);
    const regionFilter = ref('');
    const searchQ = ref('');
    const planFilter = ref('');
    const subStatusFilter = ref('');
    const dueOnly = ref(false);
    const noPlanOnly = ref(false);
    const expiringOnly = ref(false);
    const showForm = ref(false);
    const error = ref('');
    const form = reactive({ name: '', phone: toFullPhone(''), password: '' });
    const planOpen = ref(false);
    const planSaving = ref(false);
    const planError = ref('');
    const planForm = reactive(emptyPlanForm());
    let searchTimer = null;

    const planOptions = computed(() => [
      { id: 'cabinet', label: t('homePlanCabinetName') },
      { id: 'control', label: t('homePlanControlName') },
      { id: 'individual', label: t('homePlanIndividualName') },
    ]);

    const searchActive = computed(() => (
      !!searchQ.value.trim()
      || !!regionFilter.value
      || !!planFilter.value
      || !!subStatusFilter.value
      || dueOnly.value
      || noPlanOnly.value
      || expiringOnly.value
    ));

    function isExpiring(d) {
      if (!d || d.access_expired) return false;
      return !!(d.subscription?.expiring_soon || d.access_expiring_soon);
    }

    const summary = computed(() => {
      const list = allForSummary.value;
      return {
        total: list.length,
        due_checks: list.filter((d) => d.subscription?.needs_check).length,
        attention: list.filter((d) => d.subscription?.status === 'attention').length,
        expiring: list.filter((d) => isExpiring(d)).length,
        without_plan: list.filter((d) => !d.subscription).length,
      };
    });

    const number = (value) => {
      if (value === null || value === undefined || value === '') return '0';
      return new Intl.NumberFormat(i18nState.locale === 'ru' ? 'ru-RU' : 'uz-UZ').format(Number(value));
    };

    const formatPhone = (phone) => (phone ? formatPhoneDisplay(phone) : '—');

    const formatArea = (areaM2) => {
      if (areaM2 === null || areaM2 === undefined || areaM2 === '') return '0';
      return number(Number(areaM2));
    };

    function profitClass(profit) {
      const value = Number(profit) || 0;
      if (value > 0) return 'is-up';
      if (value < 0) return 'is-down';
      return 'is-zero';
    }

    function profitLabel(profit) {
      const value = Number(profit) || 0;
      if (value > 0) return t('ownerDirectorInProfit');
      if (value < 0) return t('ownerDirectorInLoss');
      return t('ownerDirectorBreakEven');
    }

    function planLabel(plan) {
      if (plan === 'control') return t('homePlanControlName');
      if (plan === 'individual') return t('homePlanIndividualName');
      return t('homePlanCabinetName');
    }

    function periodLabel(period) {
      return period === 'month' ? t('ownerClientsPeriodMonth') : t('ownerClientsPeriodYear');
    }

    function planBadgeClass(plan) {
      if (plan === 'individual') return 'pending';
      return 'active';
    }

    async function loadSummaryBase() {
      try {
        const { data } = await window.axios.get('/owner/directors');
        allForSummary.value = data;
      } catch (e) {
        allForSummary.value = [];
      }
    }

    function focusQuery() {
      const query = {};
      if (dueOnly.value) query.due = '1';
      if (noPlanOnly.value) query.no_plan = '1';
      if (expiringOnly.value) query.expiring = '1';
      if (subStatusFilter.value) query.status = subStatusFilter.value;
      return query;
    }

    function pushFocusQuery() {
      const query = focusQuery();
      const current = route.query || {};
      const same = ['due', 'no_plan', 'expiring', 'status'].every(
        (key) => String(current[key] || '') === String(query[key] || ''),
      );
      if (!same) {
        router.replace({ name: route.name, query }).catch(() => {});
      }
    }

    async function load() {
      const params = {};
      if (regionFilter.value) params.region = regionFilter.value;
      if (searchQ.value.trim()) params.q = searchQ.value.trim();
      if (planFilter.value) params.plan = planFilter.value;
      if (subStatusFilter.value) params.sub_status = subStatusFilter.value;
      if (dueOnly.value) params.due = 1;
      if (noPlanOnly.value) params.no_plan = 1;
      if (expiringOnly.value) params.expiring = 1;
      let rows = [];
      try {
        const { data } = await window.axios.get('/owner/directors', { params });
        rows = Array.isArray(data) ? [...data] : [];
      } catch (e) {
        directors.value = [];
        return;
      }
      if (expiringOnly.value) {
        rows.sort((a, b) => {
          const da = a.access_days_left ?? 9999;
          const db = b.access_days_left ?? 9999;
          return da - db;
        });
      }
      directors.value = rows;
    }

    function onSearchInput() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(load, 280);
    }

    function onPlanFilterChange() {
      noPlanOnly.value = false;
      expiringOnly.value = false;
      load();
    }

    function clearFocusFilters() {
      dueOnly.value = false;
      noPlanOnly.value = false;
      expiringOnly.value = false;
      subStatusFilter.value = '';
      planFilter.value = '';
      pushFocusQuery();
      load();
    }

    function toggleDue() {
      dueOnly.value = !dueOnly.value;
      if (dueOnly.value) {
        noPlanOnly.value = false;
        expiringOnly.value = false;
        subStatusFilter.value = '';
      }
      pushFocusQuery();
      load();
    }

    function toggleAttention() {
      subStatusFilter.value = subStatusFilter.value === 'attention' ? '' : 'attention';
      if (subStatusFilter.value) {
        dueOnly.value = false;
        noPlanOnly.value = false;
        expiringOnly.value = false;
      }
      pushFocusQuery();
      load();
    }

    function toggleExpiring() {
      expiringOnly.value = !expiringOnly.value;
      if (expiringOnly.value) {
        dueOnly.value = false;
        noPlanOnly.value = false;
        subStatusFilter.value = '';
        planFilter.value = '';
      }
      pushFocusQuery();
      load();
    }

    function toggleNoPlan() {
      noPlanOnly.value = !noPlanOnly.value;
      if (noPlanOnly.value) {
        dueOnly.value = false;
        expiringOnly.value = false;
        planFilter.value = '';
        subStatusFilter.value = '';
      }
      pushFocusQuery();
      load();
    }

    function applyRouteQuery() {
      dueOnly.value = route.query.due === '1';
      noPlanOnly.value = route.query.no_plan === '1';
      expiringOnly.value = route.query.expiring === '1';
      const status = route.query.status || route.query.sub_status || '';
      subStatusFilter.value = status === 'attention' ? 'attention' : '';
    }

    async function loadRegions() {
      const { data } = await window.axios.get('/owner/regions');
      regions.value = normalizeRegions(data).map((r) => r.name);
    }

    function openForm() {
      Object.assign(form, { name: '', phone: toFullPhone(''), password: '' });
      error.value = '';
      showForm.value = true;
    }

    async function create() {
      error.value = '';
      if (!isCompletePhone(form.phone)) {
        error.value = t('phoneIncomplete');
        return;
      }
      try {
        const { data } = await window.axios.post('/owner/directors', {
          name: form.name,
          phone: form.phone,
          password: form.password,
        });
        showForm.value = false;
        await Promise.all([load(), loadSummaryBase()]);
        if (data?.id) {
          // Standart tarif avtomatik ochiladi — login darhol ishlaydi; kerak bo‘lsa tahrirlash.
          openPlan({
            id: data.id,
            name: data.name,
            subscription: data.subscription || null,
          });
        }
      } catch (e) {
        const errors = e.response?.data?.errors;
        error.value = errors
          ? Object.values(errors).flat().join(' ')
          : (e.response?.data?.message || t('errorGeneric'));
      }
    }

    function enterDirector(director) {
      setActingDirector(director);
      router.push({ name: 'owner-director-hub', params: { directorId: director.id } });
    }

    function applyPlanDefaults() {
      planForm.price_amount = defaultPrice(planForm.plan, planForm.billing_period);
      if (planForm.plan === 'cabinet') {
        planForm.next_check_at = '';
        planForm.check_interval_days = 0;
      } else {
        planForm.check_interval_days = 7;
        if (!planForm.next_check_at) planForm.next_check_at = todayPlus(7);
      }
      recalcEnds();
    }

    function recalcEnds() {
      const start = planForm.starts_at || todayPlus(0);
      const d = new Date(`${start}T12:00:00`);
      d.setDate(d.getDate() + periodDays(planForm.billing_period));
      planForm.ends_at = d.toISOString().slice(0, 10);
    }

    function pickPlan(plan) {
      planForm.plan = plan;
      applyPlanDefaults();
    }

    function pickPeriod(period) {
      planForm.billing_period = period;
      applyPlanDefaults();
    }

    function openPlan(director) {
      const sub = director.subscription;
      Object.assign(planForm, emptyPlanForm(), {
        id: sub?.id || null,
        director_id: director.id,
        director_name: director.name,
        plan: sub?.plan || 'cabinet',
        billing_period: sub?.billing_period || 'year',
        price_amount: sub?.price_amount ?? defaultPrice(sub?.plan || 'cabinet', sub?.billing_period || 'year'),
        starts_at: sub?.starts_at || todayPlus(0),
        ends_at: sub?.ends_at || todayPlus(365),
        next_check_at: sub?.next_check_at || '',
        check_interval_days: sub?.check_interval_days || 7,
        status: sub?.status || 'active',
        attention_note: sub?.attention_note || '',
        notes: sub?.notes || '',
      });
      if (!sub) applyPlanDefaults();
      planError.value = '';
      planOpen.value = true;
    }

    function closePlan() {
      planOpen.value = false;
      planError.value = '';
    }

    async function savePlan() {
      planSaving.value = true;
      planError.value = '';
      const payload = {
        director_id: planForm.director_id,
        plan: planForm.plan,
        billing_period: planForm.billing_period,
        price_amount: planForm.price_amount || null,
        starts_at: planForm.starts_at,
        ends_at: planForm.ends_at,
        next_check_at: planForm.plan === 'cabinet' ? null : (planForm.next_check_at || null),
        check_interval_days: planForm.plan === 'cabinet' ? 0 : (planForm.check_interval_days || 7),
        status: planForm.status,
        attention_note: planForm.status === 'attention' ? planForm.attention_note : null,
        notes: planForm.notes || null,
      };
      try {
        if (planForm.id) {
          await window.axios.put(`/owner/subscriptions/${planForm.id}`, payload);
        } else {
          await window.axios.post('/owner/subscriptions', payload);
        }
        closePlan();
        await Promise.all([load(), loadSummaryBase()]);
      } catch (e) {
        planError.value = e?.response?.data?.message
          || Object.values(e?.response?.data?.errors || {})?.[0]?.[0]
          || t('errorGeneric');
      } finally {
        planSaving.value = false;
      }
    }

    async function markChecked(director) {
      const id = director.subscription?.id;
      if (!id) return;
      try {
        await window.axios.post(`/owner/subscriptions/${id}/check`);
        await Promise.all([load(), loadSummaryBase()]);
      } catch (e) {
        // ignore
      }
    }

    watch(
      () => [
        route.query.due,
        route.query.no_plan,
        route.query.expiring,
        route.query.status,
        route.query.sub_status,
      ],
      async () => {
        applyRouteQuery();
        await load();
      },
    );

    onMounted(async () => {
      applyRouteQuery();
      await Promise.all([loadRegions(), load(), loadSummaryBase()]);
    });

    return {
      directors,
      summary,
      regions,
      regionFilter,
      searchQ,
      planFilter,
      subStatusFilter,
      dueOnly,
      noPlanOnly,
      expiringOnly,
      searchActive,
      showForm,
      form,
      error,
      planOpen,
      planForm,
      planSaving,
      planError,
      planOptions,
      isExpiring,
      onSearchInput,
      onPlanFilterChange,
      clearFocusFilters,
      toggleDue,
      toggleAttention,
      toggleExpiring,
      toggleNoPlan,
      number,
      formatPhone,
      formatArea,
      formatMoney,
      profitClass,
      profitLabel,
      planLabel,
      periodLabel,
      planBadgeClass,
      openForm,
      create,
      load,
      enterDirector,
      openPlan,
      closePlan,
      savePlan,
      pickPlan,
      pickPeriod,
      recalcEnds,
      markChecked,
    };
  },
};
</script>
