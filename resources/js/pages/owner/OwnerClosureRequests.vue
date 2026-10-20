<template>
  <div class="owner-closure-requests-page">
    <div class="page-head">
      <div>
        <h1>{{ $t('closureRequests') }}</h1>
        <p>{{ $t('requestsHint') }}</p>
      </div>
    </div>

    <div class="owner-filter-bar panel">
      <div class="field">
        <label>{{ $t('requestType') }}</label>
        <select v-model="typeFilter" @change="load">
          <option value="create_pond">{{ $t('requestTypeCreatePond') }}</option>
          <option value="closure">{{ $t('requestTypeClosure') }}</option>
        </select>
      </div>
      <div class="field">
        <label>{{ $t('filterStatus') }}</label>
        <select v-model="statusFilter" @change="load">
          <option value="pending">{{ $t('statusPending') }}</option>
          <option value="approved">{{ $t('statusApproved') }}</option>
          <option value="rejected">{{ $t('statusRejected') }}</option>
          <option value="all">{{ $t('all') }}</option>
        </select>
      </div>
      <span v-if="pendingCount" class="badge active">{{ pendingCount }} {{ $t('pendingShort') }}</span>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="!requests.length" class="panel empty">{{ $t('noClosureRequests') }}</div>

    <div v-else class="closure-request-list">
      <template v-if="typeFilter === 'create_pond'">
        <article v-for="item in requests" :key="`create-${item.id}`" class="panel closure-request-card">
          <div class="closure-request-head">
            <div>
              <h2>{{ $t('createPondRequestCardTitle', { director: item.requester?.name || '—', pond: item.name || '—' }) }}</h2>
              <p class="closure-request-subtitle">{{ $t('createPondRequestCardSubtitle') }}</p>
            </div>
            <span class="badge" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
          </div>

          <div class="closure-request-meta-grid">
            <section class="closure-request-meta-block">
              <h3>{{ $t('closureRequestDirector') }}</h3>
              <dl class="record-facts">
                <div>
                  <dt>{{ $t('name') }}</dt>
                  <dd><strong>{{ item.requester?.name || '—' }}</strong></dd>
                </div>
                <div>
                  <dt>{{ $t('phone') }}</dt>
                  <dd>
                    <a v-if="item.requester?.phone" class="phone-link" :href="`tel:${normalizePhone(item.requester.phone)}`">
                      {{ formatPhone(item.requester.phone) }}
                    </a>
                    <span v-else>—</span>
                  </dd>
                </div>
              </dl>
            </section>

            <section class="closure-request-meta-block">
              <h3>{{ $t('createPondRequestDetails') }}</h3>
              <dl class="record-facts">
                <div>
                  <dt>{{ $t('pondName') }}</dt>
                  <dd><strong>{{ item.name || '—' }}</strong></dd>
                </div>
                <div>
                  <dt>{{ $t('farm') }}</dt>
                  <dd>{{ item.farm?.name || '—' }}</dd>
                </div>
                <div>
                  <dt>{{ $t('region') }}</dt>
                  <dd>{{ item.region || '—' }}</dd>
                </div>
                <div>
                  <dt>{{ $t('district') }}</dt>
                  <dd>{{ item.district || '—' }}</dd>
                </div>
                <div v-if="item.area_m2 != null">
                  <dt>{{ $t('area') }}</dt>
                  <dd>{{ formatArea(item.area_m2) }} {{ $t('hectare') }}</dd>
                </div>
              </dl>
            </section>
          </div>

          <dl class="record-facts closure-request-dates">
            <div>
              <dt>{{ $t('sentAt') }}</dt>
              <dd>{{ formatDateTime(item.created_at) }}</dd>
            </div>
          </dl>

          <p v-if="item.notes" class="closure-request-notes">
            <strong>{{ $t('notes') }}:</strong> {{ item.notes }}
          </p>

          <div v-if="item.status === 'pending'" class="closure-request-actions">
            <div class="field form-wide">
              <label>{{ $t('reviewNotes') }}</label>
              <textarea v-model="reviewNotes[item.id]" rows="2" />
            </div>
            <div class="actions">
              <button class="btn btn-primary" type="button" :disabled="actingId === item.id" @click="approveCreate(item)">
                {{ actingId === item.id ? $t('loading') : $t('approveCreatePond') }}
              </button>
              <button class="btn btn-outline" type="button" :disabled="actingId === item.id" @click="rejectCreate(item)">
                {{ $t('rejectClosure') }}
              </button>
            </div>
          </div>

          <p v-else-if="item.review_notes" class="closure-request-notes">
            <strong>{{ $t('reviewNotes') }}:</strong> {{ item.review_notes }}
          </p>
        </article>
      </template>

      <template v-else>
        <article v-for="item in requests" :key="`close-${item.id}`" class="panel closure-request-card">
          <div class="closure-request-head">
            <div>
              <h2>{{ $t('closureRequestCardTitle', { director: item.requester?.name || '—', pond: item.pond?.name || '—' }) }}</h2>
              <p class="closure-request-subtitle">{{ $t('closureRequestCardSubtitle') }}</p>
            </div>
            <span class="badge" :class="statusClass(item.status)">{{ statusLabel(item.status) }}</span>
          </div>

          <div class="closure-request-meta-grid">
            <section class="closure-request-meta-block">
              <h3>{{ $t('closureRequestDirector') }}</h3>
              <dl class="record-facts">
                <div>
                  <dt>{{ $t('name') }}</dt>
                  <dd><strong>{{ item.requester?.name || '—' }}</strong></dd>
                </div>
                <div>
                  <dt>{{ $t('phone') }}</dt>
                  <dd>
                    <a v-if="item.requester?.phone" class="phone-link" :href="`tel:${normalizePhone(item.requester.phone)}`">
                      {{ formatPhone(item.requester.phone) }}
                    </a>
                    <span v-else>—</span>
                  </dd>
                </div>
              </dl>
            </section>

            <section class="closure-request-meta-block">
              <h3>{{ $t('closureRequestPond') }}</h3>
              <dl class="record-facts">
                <div>
                  <dt>{{ $t('pondName') }}</dt>
                  <dd><strong>{{ item.pond?.name || '—' }}</strong></dd>
                </div>
                <div>
                  <dt>{{ $t('region') }}</dt>
                  <dd>{{ item.pond?.region || '—' }}</dd>
                </div>
                <div>
                  <dt>{{ $t('farm') }}</dt>
                  <dd>{{ item.pond?.farm?.name || '—' }}</dd>
                </div>
                <div v-if="item.pond?.area_m2 != null">
                  <dt>{{ $t('area') }}</dt>
                  <dd>{{ formatArea(item.pond.area_m2) }} {{ $t('hectare') }}</dd>
                </div>
                <div v-if="item.pond?.director && item.pond.director.id !== item.requester?.id">
                  <dt>{{ $t('pondDirector') }}</dt>
                  <dd>
                    {{ item.pond.director.name }}
                    <a v-if="item.pond.director.phone" class="phone-link" :href="`tel:${normalizePhone(item.pond.director.phone)}`">
                      · {{ formatPhone(item.pond.director.phone) }}
                    </a>
                  </dd>
                </div>
              </dl>
            </section>
          </div>

          <dl class="record-facts closure-request-dates">
            <div>
              <dt>{{ $t('closeDate') }}</dt>
              <dd>{{ item.closed_on }}</dd>
            </div>
            <div>
              <dt>{{ $t('sentAt') }}</dt>
              <dd>{{ formatDateTime(item.created_at) }}</dd>
            </div>
          </dl>

          <p v-if="item.notes" class="closure-request-notes">
            <strong>{{ $t('notes') }}:</strong> {{ item.notes }}
          </p>

          <div v-if="item.analysis_snapshot?.fish_types?.length" class="table-scroll">
            <table class="report-table">
              <thead>
                <tr>
                  <th>{{ $t('fishType') }}</th>
                  <th>{{ $t('added') }}</th>
                  <th>{{ $t('sold') }}</th>
                  <th>{{ $t('loss') }}</th>
                  <th>{{ $t('inStock') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in item.analysis_snapshot.fish_types" :key="row.fish_type">
                  <td>{{ row.label }}</td>
                  <td class="num">{{ formatInt(row.added) }}</td>
                  <td class="num">{{ formatInt(row.sold) }}</td>
                  <td class="num">{{ formatInt(row.loss) }}</td>
                  <td class="num">{{ formatInt(row.remaining) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="item.status === 'pending'" class="closure-request-actions">
            <div class="field form-wide">
              <label>{{ $t('reviewNotes') }}</label>
              <textarea v-model="reviewNotes[item.id]" rows="2" />
            </div>
            <div class="actions">
              <button class="btn btn-primary" type="button" :disabled="actingId === item.id" @click="approve(item)">
                {{ actingId === item.id ? $t('loading') : $t('approveClosure') }}
              </button>
              <button class="btn btn-outline" type="button" :disabled="actingId === item.id" @click="reject(item)">
                {{ $t('rejectClosure') }}
              </button>
            </div>
          </div>

          <p v-else-if="item.review_notes" class="closure-request-notes">
            <strong>{{ $t('reviewNotes') }}:</strong> {{ item.review_notes }}
          </p>
        </article>
      </template>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import { showSaved } from '../../flash';
import { t } from '../../i18n';
import { formatInt } from '../../utils/number';
import { formatPhoneDisplay, toFullPhone } from '../../utils/phone';

export default {
  setup() {
    const loading = ref(true);
    const requests = ref([]);
    const pendingCount = ref(0);
    const typeFilter = ref('create_pond');
    const statusFilter = ref('pending');
    const actingId = ref(null);
    const reviewNotes = reactive({});

    function formatPhone(value) {
      return formatPhoneDisplay(value || '');
    }

    function normalizePhone(value) {
      return toFullPhone(value || '');
    }

    function formatArea(areaHa) {
      const ha = Number(areaHa);
      if (!Number.isFinite(ha)) return '—';
      return ha.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

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

    function statusLabel(status) {
      return ({
        pending: t('statusPending'),
        approved: t('statusApproved'),
        rejected: t('statusRejected'),
      }[status] || status);
    }

    function statusClass(status) {
      return ({
        pending: 'badge-warn',
        approved: 'active',
        rejected: 'badge-muted',
      }[status] || '');
    }

    async function load() {
      loading.value = true;
      try {
        const endpoint = typeFilter.value === 'create_pond'
          ? '/owner/pond-create-requests'
          : '/owner/closure-requests';
        const { data } = await window.axios.get(endpoint, {
          params: { status: statusFilter.value },
        });
        requests.value = data.requests || [];
        pendingCount.value = data.pending_count || 0;
      } finally {
        loading.value = false;
      }
    }

    async function approve(item) {
      actingId.value = item.id;
      try {
        await window.axios.post(`/owner/closure-requests/${item.id}/approve`, {
          review_notes: reviewNotes[item.id] || null,
        });
        showSaved(t('closureApproved'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        actingId.value = null;
      }
    }

    async function reject(item) {
      actingId.value = item.id;
      try {
        await window.axios.post(`/owner/closure-requests/${item.id}/reject`, {
          review_notes: reviewNotes[item.id] || null,
        });
        showSaved(t('closureRejected'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        actingId.value = null;
      }
    }

    async function approveCreate(item) {
      actingId.value = item.id;
      try {
        await window.axios.post(`/owner/pond-create-requests/${item.id}/approve`, {
          review_notes: reviewNotes[item.id] || null,
        });
        showSaved(t('createPondApproved'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || Object.values(e.response?.data?.errors || {}).flat().join(' ') || t('noData'));
      } finally {
        actingId.value = null;
      }
    }

    async function rejectCreate(item) {
      actingId.value = item.id;
      try {
        await window.axios.post(`/owner/pond-create-requests/${item.id}/reject`, {
          review_notes: reviewNotes[item.id] || null,
        });
        showSaved(t('createPondRejected'));
        await load();
      } catch (e) {
        window.alert(e.response?.data?.message || t('noData'));
      } finally {
        actingId.value = null;
      }
    }

    onMounted(load);

    return {
      loading,
      requests,
      pendingCount,
      typeFilter,
      statusFilter,
      actingId,
      reviewNotes,
      formatInt,
      formatPhone,
      normalizePhone,
      formatArea,
      formatDateTime,
      statusLabel,
      statusClass,
      load,
      approve,
      reject,
      approveCreate,
      rejectCreate,
    };
  },
};
</script>
