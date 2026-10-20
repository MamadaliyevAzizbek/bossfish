<template>
  <div class="owner-home">
    <header class="owner-home__hero">
      <div>
        <p class="owner-home__eyebrow">{{ $t('ownerHomeEyebrow') }}</p>
        <h1>{{ $t('ownerCabinet') }}</h1>
        <p>{{ regionFilter ? $t('ownerRegionStatsOverview', { region: regionFilter }) : $t('ownerHomeLead') }}</p>
      </div>
      <div class="owner-home__hero-actions">
        <router-link class="btn btn-primary" :to="{ name: 'owner-clients' }">{{ $t('ownerClientsNav') }}</router-link>
        <router-link class="btn btn-outline" :to="{ name: 'owner-security' }">{{ $t('security') }}</router-link>
      </div>
    </header>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="loadError" class="panel empty">{{ loadError }}</div>

    <template v-else-if="data">
      <div v-if="data.expiring_directors?.length && !regionFilter" class="owner-home__alert">
        <div>
          <strong>{{ $t('accessWarningTitle') }}</strong>
          <p>
            {{ data.expiring_directors.map((d) => `${d.name} (${d.access_days_left})`).join(' · ') }}
          </p>
        </div>
        <router-link class="btn btn-outline btn-sm" :to="{ name: 'owner-clients', query: { expiring: '1' } }">
          {{ $t('ownerClientsNav') }}
        </router-link>
      </div>

      <section class="owner-home__section" aria-labelledby="owner-home-scale">
        <div class="owner-home__section-head">
          <h2 id="owner-home-scale">{{ $t('ownerHomeScaleTitle') }}</h2>
          <p>{{ $t('ownerHomeScaleLead') }}</p>
        </div>

        <div class="owner-home__stats">
          <router-link class="owner-home-stat owner-home-stat--clients" :to="{ name: 'owner-clients' }">
            <span>{{ $t('ownerClientsNav') }}</span>
            <strong>{{ number(data.directors_count) }}</strong>
            <small>{{ $t('people') }}</small>
          </router-link>
          <div class="owner-home-stat owner-home-stat--workers">
            <span>{{ $t('workers') }}</span>
            <strong>{{ number(data.workers_count) }}</strong>
            <small>{{ $t('people') }}</small>
          </div>
          <div class="owner-home-stat owner-home-stat--farms">
            <span>{{ $t('farms') }}</span>
            <strong>{{ number(data.farms_count) }}</strong>
            <small>{{ $t('pieces') }}</small>
          </div>
          <div class="owner-home-stat owner-home-stat--ponds">
            <span>{{ $t('ponds') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.ponds_count : data.ponds_count) }}</strong>
            <small>{{ $t('pieces') }}</small>
          </div>
          <div class="owner-home-stat owner-home-stat--area">
            <span>{{ $t('totalPondArea') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.area_ha : data.total_area_ha) }}</strong>
            <small>{{ $t('hectare') }}</small>
          </div>
          <div class="owner-home-stat owner-home-stat--fish">
            <span>{{ $t('totalFishCount') }}</span>
            <strong>{{ number(regionFilter ? activeRegionRow?.fish_count : data.total_fish_count) }}</strong>
            <small>{{ $t('pieces') }}</small>
          </div>
        </div>
      </section>

      <section class="owner-home__section" aria-labelledby="owner-home-focus">
        <div class="owner-home__section-head">
          <h2 id="owner-home-focus">{{ $t('ownerHomeFocusTitle') }}</h2>
          <p>{{ $t('ownerHomeFocusLead') }}</p>
        </div>

        <div class="owner-home__focus">
          <router-link
            class="owner-home-focus"
            :class="{ 'owner-home-focus--warn': clients.due_checks > 0 }"
            :to="{ name: 'owner-clients', query: { due: '1' } }"
          >
            <span>{{ $t('ownerClientsDue') }}</span>
            <strong>{{ number(clients.due_checks) }}</strong>
          </router-link>
          <router-link
            class="owner-home-focus"
            :class="{ 'owner-home-focus--warn': clients.attention > 0 }"
            :to="{ name: 'owner-clients', query: { status: 'attention' } }"
          >
            <span>{{ $t('ownerClientsAttention') }}</span>
            <strong>{{ number(clients.attention) }}</strong>
          </router-link>
          <router-link
            class="owner-home-focus"
            :to="{ name: 'owner-clients', query: { no_plan: '1' } }"
          >
            <span>{{ $t('ownerClientsNoPlan') }}</span>
            <strong>{{ number(clients.without_plan) }}</strong>
          </router-link>
          <router-link
            class="owner-home-focus"
            :class="{ 'owner-home-focus--warn': clients.expiring > 0 || data.expiring_directors?.length }"
            :to="{ name: 'owner-clients', query: { expiring: '1' } }"
          >
            <span>{{ $t('ownerClientsExpiring') }}</span>
            <strong>{{ number(Math.max(clients.expiring, data.expiring_directors?.length || 0)) }}</strong>
          </router-link>
        </div>

        <div class="owner-home__plans">
          <div class="owner-home-plan">
            <span>{{ $t('homePlanCabinetName') }}</span>
            <strong>{{ number(clients.cabinet) }}</strong>
          </div>
          <div class="owner-home-plan owner-home-plan--accent">
            <span>{{ $t('homePlanControlName') }}</span>
            <strong>{{ number(clients.control) }}</strong>
          </div>
          <div class="owner-home-plan">
            <span>{{ $t('homePlanIndividualName') }}</span>
            <strong>{{ number(clients.individual) }}</strong>
          </div>
        </div>
      </section>

      <section class="owner-home__section panel owner-home__regions" aria-labelledby="owner-home-regions">
        <div class="owner-home__section-head owner-home__section-head--row">
          <div>
            <h2 id="owner-home-regions">{{ regionFilter ? $t('regionDetailTitle', { region: regionFilter }) : $t('regionBreakdown') }}</h2>
            <p>{{ $t('regionBreakdownHint') }}</p>
          </div>
          <div class="owner-home__region-filter">
            <label class="visually-hidden" for="owner-home-region">{{ $t('filterByRegion') }}</label>
            <select id="owner-home-region" v-model="regionFilter">
              <option value="">{{ $t('allRegions') }}</option>
              <option v-for="r in allRegionNames" :key="r" :value="r">{{ r }}</option>
            </select>
            <button
              v-if="regionFilter"
              class="btn btn-outline btn-sm"
              type="button"
              @click="regionFilter = ''"
            >
              {{ $t('showAllRegions') }}
            </button>
          </div>
        </div>

        <div v-if="!tableRows.length" class="empty">{{ $t('noPondsInRegion') }}</div>
        <div v-else class="table-scroll">
          <table class="report-table owner-region-table">
            <thead>
              <tr>
                <th>{{ $t('region') }}</th>
                <th>{{ $t('ponds') }}</th>
                <th>{{ $t('ownerClientsNav') }}</th>
                <th>{{ $t('totalPondArea') }}</th>
                <th>{{ $t('totalFishCount') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in tableRows"
                :key="row.region"
                :class="{ 'owner-region-row--active': row.region === regionFilter }"
              >
                <td>
                  <button
                    v-if="!regionFilter"
                    type="button"
                    class="owner-region-link"
                    @click="regionFilter = row.region"
                  >
                    {{ row.region }}
                  </button>
                  <strong v-else>{{ row.region }}</strong>
                </td>
                <td class="num">{{ number(row.ponds_count) }}</td>
                <td class="num">{{ number(row.directors_count) }}</td>
                <td class="num">{{ number(row.area_ha) }} {{ $t('hectare') }}</td>
                <td class="num">{{ number(row.fish_count) }} {{ $t('pieces') }}</td>
              </tr>
            </tbody>
            <tfoot v-if="!regionFilter && tableRows.length > 1">
              <tr>
                <td><strong>{{ $t('total') }}</strong></td>
                <td class="num">{{ number(data.ponds_count) }}</td>
                <td class="num">{{ number(data.directors_count) }}</td>
                <td class="num">{{ number(data.total_area_ha) }} {{ $t('hectare') }}</td>
                <td class="num">{{ number(data.total_fish_count) }} {{ $t('pieces') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue';
import { i18nState, t } from '../../i18n';
import { regionCoordinates } from '../../utils/regionCoordinates';

export default {
  name: 'OwnerCabinet',
  setup() {
    const data = ref(null);
    const loading = ref(true);
    const loadError = ref('');
    const regionFilter = ref('');

    const number = (value) => {
      if (value === null || value === undefined || value === '') return '0';
      return new Intl.NumberFormat(i18nState.locale === 'ru' ? 'ru-RU' : 'uz-UZ').format(Number(value));
    };

    const clients = computed(() => ({
      with_plan: 0,
      without_plan: 0,
      cabinet: 0,
      control: 0,
      individual: 0,
      due_checks: 0,
      attention: 0,
      expiring: 0,
      ...(data.value?.clients || {}),
    }));

    const allRegionNames = computed(() => (
      Object.keys(regionCoordinates).sort((a, b) => a.localeCompare(b, 'uz'))
    ));

    const regionStatsByName = computed(() => {
      const map = {};
      (data.value?.by_region || []).forEach((row) => {
        map[row.region] = row;
      });
      return map;
    });

    const activeRegionRow = computed(() => {
      if (!regionFilter.value) return null;
      return regionStatsByName.value[regionFilter.value] || {
        region: regionFilter.value,
        ponds_count: 0,
        directors_count: 0,
        area_ha: 0,
        fish_count: 0,
      };
    });

    const tableRows = computed(() => {
      const stats = regionStatsByName.value;
      if (regionFilter.value) {
        const row = stats[regionFilter.value];
        return row ? [row] : [{
          region: regionFilter.value,
          ponds_count: 0,
          directors_count: 0,
          area_ha: 0,
          fish_count: 0,
        }];
      }
      return Object.values(stats).sort((a, b) => a.region.localeCompare(b.region, 'uz'));
    });

    onMounted(async () => {
      loading.value = true;
      loadError.value = '';
      try {
        const res = await window.axios.get('/owner/dashboard');
        data.value = res.data;
      } catch (e) {
        loadError.value = e.response?.data?.message || t('loadFailed');
      } finally {
        loading.value = false;
      }
    });

    return {
      data,
      loading,
      loadError,
      regionFilter,
      clients,
      allRegionNames,
      activeRegionRow,
      tableRows,
      number,
    };
  },
};
</script>
