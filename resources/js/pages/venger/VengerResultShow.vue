<template>
  <div class="vr-report venger-readable">
    <OwnerVengerNav v-if="isOwnerView" class="no-print" />
    <div class="page-head vr-report__toolbar no-print">
      <div>
        <h1>{{ $t('vengerResultWindow') }}</h1>
        <p>{{ $t('vengerBoardShareHint') }}</p>
      </div>
      <div class="page-head-actions">
        <button
          v-if="result"
          type="button"
          class="btn btn-primary"
          :disabled="exporting"
          @click="prepareTelegramShare"
        >
          {{ exporting ? $t('vengerTgPreparing') : $t('vengerTgShareBtn') }}
        </button>
        <router-link class="btn btn-outline btn-sm" :to="backTo">{{ $t('back') }}</router-link>
        <router-link
          v-if="canEdit"
          class="btn btn-outline"
          :to="{ name: 'venger-result-edit', params: { resultId: route.params.resultId } }"
        >
          {{ $t('edit') }}
        </router-link>
      </div>
    </div>

    <div v-if="shareOpen" class="vr-tg-share no-print panel">
      <div class="vr-tg-share__head">
        <div>
          <h3>{{ $t('vengerTgShareTitle') }}</h3>
          <p>{{ shareStatus || $t('vengerTgShareHint') }}</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" @click="shareOpen = false">{{ $t('cancel') }}</button>
      </div>
      <label class="vr-tg-share__label" for="vr-tg-caption">{{ $t('vengerTgCaptionLabel') }}</label>
      <textarea id="vr-tg-caption" v-model="shareText" class="vr-tg-share__text" rows="12" readonly />
      <div class="vr-tg-share__actions">
        <button type="button" class="btn btn-primary" @click="copyShareText">
          {{ shareCopied ? $t('vengerTgCopied') : $t('vengerTgCopyText') }}
        </button>
        <button type="button" class="btn btn-outline" :disabled="exporting" @click="prepareTelegramShare">
          {{ $t('vengerTgAgain') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>
    <div v-else-if="error" class="panel empty">{{ error }}</div>

    <article v-else-if="result" ref="boardEl" class="vr-board">
      <header class="vr-hero">
        <div class="vr-hero__top">
          <div class="vr-hero__brand">
            <VengerCarpLogo hero />
            <div>
              <h2>{{ result.title || $t('vengerFeedEffectTitle') }}</h2>
              <p class="vr-hero__client">
                {{ result.client?.name }}
                <span v-if="result.client?.phone"> · {{ formatPhone(result.client.phone) }}</span>
              </p>
              <p v-if="feedSummary" class="vr-hero__feed">
                <span>{{ $t('vengerFeedUsedLabel') }}</span>
                <strong>{{ feedSummary }}</strong>
              </p>
            </div>
          </div>
          <span class="vr-phase" :class="`vr-phase--${phase}`">{{ phaseLabel }}</span>
        </div>

        <div class="vr-hero__strip">
          <div class="vr-hero__date">
            <span>{{ $t('stockedOn') }}</span>
            <strong>{{ formatDate(metrics.stocked_on || result.stocked_on) }}</strong>
          </div>
          <div class="vr-hero__line" aria-hidden="true" />
          <div class="vr-hero__date">
            <span>{{ endDateLabel }}</span>
            <strong>{{ formatDate(metrics.sold_on || result.sold_on) }}</strong>
          </div>
          <div class="vr-hero__chips">
            <span>{{ $t('durationDays', { n: metrics.duration_days || 0 }) }}</span>
            <span>{{ fmt(result.area_ha, 2) }} ha</span>
          </div>
        </div>
      </header>

      <section class="vr-spotlight">
        <div class="vr-fcr">
          <span class="vr-fcr__label">{{ $t('fcr') }}</span>
          <strong class="vr-fcr__value">{{ metrics.fcr != null ? fmt(metrics.fcr, 2) : '—' }}</strong>
          <p class="vr-fcr__hint">{{ $t('vengerFcrSazanOnly') }}</p>
          <div class="vr-fcr__formula">
            <span>{{ fmt(result.feed_kg) }} kg yem</span>
            <span class="vr-fcr__div">÷</span>
            <span>{{ fmt(sazan.biomass_gain_kg) }} kg o‘sish</span>
          </div>
        </div>

        <div class="vr-spotlight__side">
          <div class="vr-metric vr-metric--gain">
            <span>{{ gainLabel }}</span>
            <strong>{{ fmt(sazan.biomass_gain_kg) }} <small>kg</small></strong>
            <em v-if="sazan.weight_multiplier">×{{ fmt(sazan.weight_multiplier, 2) }} marta</em>
          </div>
          <div class="vr-metric vr-metric--feed">
            <span>{{ $t('feedUsedKg') }}</span>
            <strong>{{ fmt(result.feed_kg) }} <small>kg</small></strong>
            <em>{{ feedSummary || $t('vengerFeedSoFarShort') }}</em>
          </div>
          <div class="vr-metric vr-metric--end">
            <span>{{ endWeightLabel }}</span>
            <strong>{{ fmt(sazan.end_weight_kg ?? sazan.stocked_weight_kg) }} <small>kg</small></strong>
            <em>{{ $t('vengerSazanMain') }}</em>
          </div>
        </div>
      </section>

      <section class="vr-flow">
        <div class="vr-flow__item vr-flow__item--stock">
          <span>{{ $t('vengerBoardStocked') }}</span>
          <strong>{{ fmt(sazan.stocked_weight_kg) }} <small>kg</small></strong>
          <em v-if="sazan.count">≈ {{ fmt(sazan.count) }} {{ $t('vengerPcsApprox') }}</em>
        </div>
        <div class="vr-flow__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></div>
        <div v-if="phase === 'mid'" class="vr-flow__item vr-flow__item--mid">
          <span>{{ $t('vengerEstWeightKg') }}</span>
          <strong>{{ fmt(sazan.current_weight_kg) }} <small>kg</small></strong>
          <em v-if="sazan.avg_end_weight_g">≈ {{ fmt(sazan.avg_end_weight_g, 0) }} g</em>
        </div>
        <div v-if="phase === 'mid'" class="vr-flow__arrow" aria-hidden="true"><svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2l4 4-4 4"/></svg></div>
        <div class="vr-flow__item vr-flow__item--sold">
          <span>{{ phase === 'final' ? $t('soldWeightKg') : endWeightLabel }}</span>
          <strong>{{ fmt(phase === 'final' ? sazan.sold_weight_kg : (sazan.end_weight_kg ?? sazan.stocked_weight_kg)) }} <small>kg</small></strong>
          <em v-if="phase === 'final' && metrics.sold_pieces">≈ {{ fmt(metrics.sold_pieces) }} {{ $t('vengerPcsApprox') }}</em>
        </div>
        <div class="vr-flow__arrow" aria-hidden="true">=</div>
        <div class="vr-flow__item vr-flow__item--gain">
          <span>{{ gainLabel }}</span>
          <strong>+{{ fmt(sazan.biomass_gain_kg) }} <small>kg</small></strong>
        </div>
        <div v-if="Number(sazan.loss_weight_kg) > 0" class="vr-flow__item vr-flow__item--loss">
          <span>{{ $t('lossWeightKg') }}</span>
          <strong>−{{ fmt(sazan.loss_weight_kg) }} <small>kg</small></strong>
          <em v-if="metrics.loss_pieces">≈ {{ fmt(metrics.loss_pieces) }} {{ $t('vengerPcsApprox') }}</em>
        </div>
      </section>

      <section class="vr-story">
        <h3>{{ $t('vengerBoardHowTitle') }}</h3>
        <p>{{ $t('vengerBoardHowText', {
          feed: fmt(result.feed_kg),
          gain: fmt(sazan.biomass_gain_kg),
          fcr: metrics.fcr != null ? fmt(metrics.fcr, 2) : '—',
        }) }}</p>
        <ul class="vr-story__list">
          <li>{{ $t('vengerBoardPointStock', { kg: fmt(sazan.stocked_weight_kg) }) }}
            <span v-if="sazan.count"> · ≈ {{ fmt(sazan.count) }} {{ $t('vengerPcsApprox') }}</span>
          </li>
          <li v-if="densityPerHa != null">{{ $t('vengerBoardPointDensity', { n: fmt(densityPerHa) }) }}</li>
          <li v-if="phase === 'mid'">{{ $t('vengerBoardPointMid', { kg: fmt(sazan.current_weight_kg) }) }}</li>
          <li v-if="phase === 'final'">{{ $t('vengerBoardPointSold', { kg: fmt(sazan.sold_weight_kg) }) }}
            <span v-if="metrics.sold_pieces"> · ≈ {{ fmt(metrics.sold_pieces) }} {{ $t('vengerPcsApprox') }}</span>
          </li>
          <li v-if="Number(sazan.loss_weight_kg) > 0">{{ $t('vengerBoardPointLoss', { kg: fmt(sazan.loss_weight_kg) }) }}</li>
        </ul>
        <div v-if="feedRows.length" class="vr-feeds">
          <span v-for="f in feedRows" :key="f.type" class="vr-feed-chip">
            {{ feedTypeLabel(f) }} · {{ fmt(f.kg) }} kg
          </span>
        </div>
        <p v-if="feedRows.length" class="vr-feeds-total">
          {{ $t('vengerFeedTotalLabel') }}: <strong>{{ fmt(result.feed_kg) }} kg</strong>
        </p>
      </section>

      <section v-if="controlRows.length" class="vr-panel vr-controls" :class="{ 'is-history': phase === 'final' }">
        <div class="vr-panel__head vr-controls__head">
          <div class="vr-controls__title-row">
            <img class="vr-controls__fish" src="/images/fish/sazan.png" alt="" />
            <div>
              <h3>{{ $t('vengerControlJourneyTitle') }}</h3>
              <small>{{ $t('vengerControlJourneyHint') }}</small>
            </div>
          </div>
          <span class="vr-controls__badge">{{ $t('fishSazan') }}</span>
        </div>

        <p class="vr-controls__story">{{ controlStoryText }}</p>
        <p v-if="phase === 'final'" class="vr-controls__note">{{ $t('vengerControlsHistoryNote') }}</p>

        <svg
          v-if="controlChartPoints.length"
          class="vr-ctrl-chart"
          viewBox="0 0 360 150"
          role="img"
          :aria-label="$t('vengerControlJourneyTitle')"
        >
          <defs>
            <linearGradient id="vrCtrlFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#1a6fb5" stop-opacity="0.28" />
              <stop offset="100%" stop-color="#1a6fb5" stop-opacity="0.02" />
            </linearGradient>
          </defs>
          <line x1="36" y1="18" x2="36" y2="112" stroke="rgba(10,50,30,0.1)" stroke-dasharray="3 4" />
          <line x1="36" y1="112" x2="340" y2="112" stroke="rgba(10,50,30,0.1)" />
          <path :d="controlAreaPath" fill="url(#vrCtrlFill)" />
          <path :d="controlLinePath" fill="none" stroke="#1a6fb5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
          <g v-for="(p, i) in controlChartPoints" :key="`cp-${i}`">
            <circle
              :cx="p.x"
              :cy="p.y"
              :r="i === 0 || i === controlChartPoints.length - 1 ? 5.5 : 4"
              :fill="p.color"
              stroke="#fff"
              stroke-width="1.6"
            />
            <text :x="p.x" :y="p.y - 10" text-anchor="middle" font-size="10" font-weight="700" :fill="p.color">
              {{ fmt(p.avg_g, 0) }}g
            </text>
            <text :x="p.x" y="128" text-anchor="middle" font-size="9" font-weight="650" fill="#5a7164">
              {{ p.shortLabel }}
            </text>
          </g>
        </svg>

        <div class="vr-ctrl-timeline">
          <div
            v-for="(step, i) in controlJourney"
            :key="`cs-${step.kind}-${i}`"
            class="vr-ctrl-step"
            :class="`vr-ctrl-step--${step.kind}`"
          >
            <div class="vr-ctrl-step__dot" aria-hidden="true" />
            <div class="vr-ctrl-step__body">
              <div class="vr-ctrl-step__top">
                <strong>{{ step.title }}</strong>
                <span>{{ formatDate(step.date) }}</span>
              </div>
              <div class="vr-ctrl-step__metrics">
                <span class="vr-ctrl-pill">{{ fmt(step.avg_g, 0) }} g</span>
                <span v-if="step.est_kg != null" class="vr-ctrl-pill vr-ctrl-pill--muted">
                  ≈ {{ fmt(step.est_kg) }} kg
                </span>
                <span
                  v-if="step.delta_g != null"
                  class="vr-ctrl-pill"
                  :class="step.delta_g >= 0 ? 'vr-ctrl-pill--up' : 'vr-ctrl-pill--down'"
                >
                  {{ step.delta_g >= 0 ? '+' : '' }}{{ fmt(step.delta_g, 0) }} g
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="controlSummary" class="vr-ctrl-summary">
          <div class="vr-ctrl-summary__item">
            <span>{{ $t('vengerControlPerFishGain') }}</span>
            <strong>+{{ fmt(controlSummary.perFishGain, 0) }} g</strong>
          </div>
          <div class="vr-ctrl-summary__item">
            <span>{{ $t('vengerControlEstBiomass') }}</span>
            <strong>{{ fmt(controlSummary.estKg) }} kg</strong>
          </div>
          <div v-if="controlSummary.days != null" class="vr-ctrl-summary__item">
            <span>{{ $t('vengerControlSpanDays') }}</span>
            <strong>{{ $t('durationDays', { n: controlSummary.days }) }}</strong>
          </div>
        </div>
      </section>

      <section class="vr-visuals">
        <div class="vr-panel vr-growth">
          <div class="vr-panel__head vr-growth__head">
            <div>
              <h3>{{ $t('growthCurveTitle') }}</h3>
              <small>{{ $t('vengerGrowthSazanHint') }}</small>
            </div>
            <img class="vr-growth__fish" src="/images/fish/sazan.png" alt="Sazan" />
          </div>

          <div class="vr-growth__daily" v-if="sazan.avg_daily_weight_gain_g != null || sazan.daily_gain_kg != null">
            <div class="vr-growth__daily-item">
              <span>{{ $t('vengerDailyGainPerFish') }}</span>
              <strong>{{ sazan.avg_daily_weight_gain_g != null ? `${fmt(sazan.avg_daily_weight_gain_g, 1)} g` : '—' }}</strong>
            </div>
            <div class="vr-growth__daily-item">
              <span>{{ $t('vengerDailyGainBiomass') }}</span>
              <strong>{{ sazan.daily_gain_kg != null ? `${fmt(sazan.daily_gain_kg, 2)} kg` : '—' }}</strong>
            </div>
          </div>

          <svg class="vr-chart" viewBox="0 0 360 168" role="img" :aria-label="$t('growthCurveTitle')">
            <defs>
              <linearGradient id="vrGainFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#12a86a" stop-opacity="0.45" />
                <stop offset="100%" stop-color="#12a86a" stop-opacity="0.02" />
              </linearGradient>
              <linearGradient id="vrLine" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#0a6b40" />
                <stop offset="100%" stop-color="#1fd18a" />
              </linearGradient>
            </defs>
            <line x1="40" y1="16" x2="40" y2="130" stroke="rgba(10,50,30,0.12)" stroke-dasharray="3 4" />
            <line x1="40" y1="130" x2="348" y2="130" stroke="rgba(10,50,30,0.12)" />
            <path :d="growthAreaPath" fill="url(#vrGainFill)" />
            <path :d="growthLinePath" fill="none" stroke="url(#vrLine)" stroke-width="3.5" stroke-linecap="round" />
            <circle
              v-for="(p, i) in growthPoints"
              :key="i"
              :cx="p.x"
              :cy="p.y"
              :r="i === 0 || i === growthPoints.length - 1 ? 5 : 3.2"
              :fill="i === growthPoints.length - 1 ? '#0ea864' : '#0a4f30'"
              stroke="#fff"
              stroke-width="1.5"
            />
            <text x="40" y="152" font-size="11" font-weight="700" fill="#5a7164">1-kun</text>
            <text x="300" y="152" font-size="11" font-weight="700" fill="#5a7164">{{ metrics.duration_days }}-kun</text>
            <text x="6" y="24" font-size="10" fill="#0a6b40" font-weight="700">{{ fmt(sazan.end_weight_kg ?? sazan.stocked_weight_kg) }}</text>
            <text x="6" y="130" font-size="10" fill="#5a7164">{{ fmt(sazan.stocked_weight_kg) }}</text>
          </svg>
        </div>

        <div class="vr-panel">
          <div class="vr-panel__head">
            <h3>{{ $t('feedVsGainTitle') }}</h3>
            <small>{{ $t('vengerFeedVsGainSazan') }}</small>
          </div>
          <div class="vr-bars">
            <div class="vr-bar">
              <strong>{{ fmt(result.feed_kg) }} <small>kg</small></strong>
              <div class="vr-bar__track">
                <div class="vr-bar__fill vr-bar__fill--feed" :style="{ height: feedBarHeight }" />
              </div>
              <span>{{ $t('feedUsedKg') }}</span>
            </div>
            <div class="vr-bar">
              <strong>{{ fmt(sazan.biomass_gain_kg) }} <small>kg</small></strong>
              <div class="vr-bar__track">
                <div class="vr-bar__fill vr-bar__fill--gain" :style="{ height: gainBarHeight }" />
              </div>
              <span>{{ $t('vengerSazanGain') }}</span>
            </div>
          </div>
          <p class="vr-panel__note">{{ $t('fcrExplain', { fcr: metrics.fcr != null ? fmt(metrics.fcr, 2) : '—' }) }}</p>
        </div>
      </section>

      <section v-if="fishRows.length" class="vr-panel vr-fish">
        <div class="vr-panel__head">
          <h3>{{ $t('fishByTypeTitle') }}</h3>
          <small>{{ $t('vengerFishTableHint') }}</small>
        </div>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>{{ $t('fishType') }}</th>
                <th>{{ $t('vengerColStock') }}</th>
                <th v-if="showCurrentCol">{{ $t('vengerEstWeightKg') }}</th>
                <th>{{ $t('vengerColSold') }}</th>
                <th>{{ $t('vengerColLoss') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in fishRows" :key="row.fish_type" :class="{ 'is-sazan': row.fish_type === 'sazan' }">
                <td>
                  <FishTypeBadge :type="row.fish_type" compact />
                  <span v-if="row.fish_type === 'sazan'" class="vr-fish__tag">FCR</span>
                </td>
                <td>
                  {{ fmt(row.stocked_weight_kg) }} kg
                  <small v-if="row.stocked_pieces" class="vr-pcs">≈ {{ fmt(row.stocked_pieces) }} dona</small>
                </td>
                <td v-if="showCurrentCol">{{ row.current_weight_kg != null ? `${fmt(row.current_weight_kg)} kg` : '—' }}</td>
                <td class="vr-num--sold">
                  {{ fmt(row.sold_weight_kg) }} kg
                  <small v-if="row.sold_pieces" class="vr-pcs">≈ {{ fmt(row.sold_pieces) }} dona</small>
                </td>
                <td class="vr-num--loss">
                  {{ fmt(row.loss_weight_kg) }} kg
                  <small v-if="row.loss_pieces" class="vr-pcs">≈ {{ fmt(row.loss_pieces) }} dona</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="vr-stats">
        <div class="vr-stat vr-stat--slate">
          <span>{{ $t('vengerStatPieces') }}</span>
          <strong>{{ sazan.count ? `≈ ${fmt(sazan.count)}` : '—' }}</strong>
          <em>{{ $t('vengerStatPiecesHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--blue">
          <span>{{ $t('vengerStatDensity') }}</span>
          <strong>{{ densityPerHa != null ? `≈ ${fmt(densityPerHa)}` : '—' }}</strong>
          <em>{{ $t('vengerStatDensityHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--sage">
          <span>{{ $t('vengerStatStartG') }}</span>
          <strong>{{ sazan.avg_start_weight_g != null ? `${fmt(sazan.avg_start_weight_g, 0)} g` : '—' }}</strong>
          <em>{{ $t('vengerStatStartGHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--green">
          <span>{{ $t('vengerStatNowG') }}</span>
          <strong>{{ sazan.avg_end_weight_g != null ? `${fmt(sazan.avg_end_weight_g, 0)} g` : '—' }}</strong>
          <em>{{ $t('vengerStatNowGHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--amber">
          <span>{{ $t('vengerStatDailyG') }}</span>
          <strong>{{ sazan.avg_daily_weight_gain_g != null ? `${fmt(sazan.avg_daily_weight_gain_g, 1)} g` : '—' }}</strong>
          <em>{{ $t('vengerStatDailyGHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--teal">
          <span>{{ $t('vengerStatYield') }}</span>
          <strong>{{ fmt(metrics.yield_per_ha_kg) }} kg</strong>
          <em>{{ $t('vengerStatYieldHint') }}</em>
        </div>
        <div class="vr-stat vr-stat--orange">
          <span>{{ $t('vengerStatFeedPerFish') }}</span>
          <strong>{{ feedPerFishLabel }}</strong>
          <em>{{ $t('vengerStatFeedPerFishHint') }}</em>
        </div>
        <div v-if="phase === 'final'" class="vr-stat vr-stat--sky">
          <span>{{ $t('vengerStatSoldShare') }}</span>
          <strong>{{ metrics.fsr_percent != null ? `${fmt(metrics.fsr_percent, 1)}%` : '—' }}</strong>
          <em>{{ $t('vengerStatSoldShareHint') }}</em>
        </div>
      </section>

      <section v-if="result.notes" class="vr-notes">
        <h3>{{ $t('otherInfo') }}</h3>
        <p>{{ result.notes }}</p>
      </section>

      <footer class="vr-foot">
        <p>{{ $t('vengerResultFooter') }}</p>
        <small v-if="placeLine">{{ placeLine }}</small>
      </footer>
    </article>
  </div>
</template>

<script>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { toPng } from 'html-to-image';
import FishTypeBadge from '../../components/FishTypeBadge.vue';
import VengerCarpLogo from '../../components/VengerCarpLogo.vue';
import OwnerVengerNav from '../../components/OwnerVengerNav.vue';
import auth from '../../auth';
import { formatPhoneDisplay } from '../../utils/phone';
import { VENGER_FEED_TYPES, normalizeFeedType } from '../../utils/vengerFeeds';
import { t } from '../../i18n';

export default {
  components: { FishTypeBadge, VengerCarpLogo, OwnerVengerNav },
  setup() {
    const route = useRoute();
    const loading = ref(true);
    const error = ref('');
    const result = ref(null);
    const boardEl = ref(null);
    const exporting = ref(false);
    const shareOpen = ref(false);
    const shareText = ref('');
    const shareStatus = ref('');
    const shareCopied = ref(false);
    const canEdit = computed(() => auth.isVenger());
    const isOwnerView = computed(() => auth.isOwner());
    const apiBase = computed(() => (auth.isOwner() ? '/owner/venger' : '/venger'));
    const metrics = computed(() => result.value?.metrics || {});
    const sazan = computed(() => metrics.value.sazan || {});
    const phase = computed(() => metrics.value.phase || 'stocked');
    const fishRows = computed(() => result.value?.fish || metrics.value.fish || []);
    const showCurrentCol = computed(() => fishRows.value.some((r) => r.current_weight_kg != null && Number(r.current_weight_kg) > 0));

    const phaseLabel = computed(() => {
      if (phase.value === 'final') return t('vengerPhaseFinal');
      if (phase.value === 'mid') return t('vengerPhaseMid');
      return t('vengerPhaseStocked');
    });

    const endDateLabel = computed(() => {
      if (phase.value === 'mid') return t('vengerWhenChecked');
      if (phase.value === 'final') return t('soldOn');
      return t('stockedOn');
    });

    const gainLabel = computed(() => (
      phase.value === 'mid' ? t('vengerGainSoFar') : t('weightGain')
    ));

    const endWeightLabel = computed(() => (
      phase.value === 'mid' ? t('vengerEstWeightKg') : t('vengerEndBiomass')
    ));

    const placeLine = computed(() => {
      const c = result.value?.client;
      if (!c) return '';
      return [c.region, c.district, c.address].filter(Boolean).join(' · ');
    });

    const feedRows = computed(() => (result.value?.feeds || []).filter((f) => f?.type && Number(f.kg) > 0));

    function feedTypeLabel(f) {
      const type = normalizeFeedType(f?.type) || f?.type;
      const meta = VENGER_FEED_TYPES.find((x) => x.id === type);
      return meta ? t(meta.labelKey) : (f?.label || type || '—');
    }

    const feedSummary = computed(() => {
      if (!feedRows.value.length) return '';
      return feedRows.value.map((f) => `${feedTypeLabel(f)} ${fmt(f.kg)} kg`).join(' · ');
    });
    const controlRows = computed(() => result.value?.controls || metrics.value.controls || []);

    const controlJourney = computed(() => {
      const rows = controlRows.value;
      if (!rows.length) return [];

      const pieces = Number(sazan.value.count || 0);
      const startAvg = Number(sazan.value.avg_start_weight_g);
      const stockedOn = metrics.value.stocked_on || result.value?.stocked_on || null;
      const steps = [];

      if (Number.isFinite(startAvg) && startAvg > 0) {
        steps.push({
          kind: 'stock',
          title: t('vengerBoardStocked'),
          date: stockedOn,
          avg_g: startAvg,
          est_kg: pieces > 0 ? Math.round(pieces * (startAvg / 1000) * 100) / 100 : Number(sazan.value.stocked_weight_kg) || null,
          delta_g: null,
        });
      }

      let prevAvg = steps.length ? steps[0].avg_g : null;
      rows.forEach((c, i) => {
        const avg = Number(c.avg_g);
        if (!(avg > 0)) return;
        const delta = prevAvg != null ? Math.round((avg - prevAvg) * 10) / 10 : null;
        steps.push({
          kind: 'control',
          title: t('vengerControlN', { n: i + 1 }),
          date: c.checked_on,
          avg_g: avg,
          est_kg: pieces > 0 ? Math.round(pieces * (avg / 1000) * 100) / 100 : null,
          delta_g: delta,
        });
        prevAvg = avg;
      });

      if (phase.value === 'final') {
        const soldAvg = Number(sazan.value.avg_end_weight_g);
        const soldOn = metrics.value.sold_on || result.value?.sold_on;
        if (soldAvg > 0 && soldOn) {
          const lastControl = rows[rows.length - 1];
          // Sotuv o‘rtacha vazni oxirgi kontroldan farq qilsa — qo‘shamiz
          if (!lastControl || Math.abs(soldAvg - Number(lastControl.avg_g)) > 0.5) {
            steps.push({
              kind: 'sold',
              title: t('soldWeightKg'),
              date: soldOn,
              avg_g: soldAvg,
              est_kg: Number(sazan.value.sold_weight_kg) || null,
              delta_g: prevAvg != null ? Math.round((soldAvg - prevAvg) * 10) / 10 : null,
            });
          }
        }
      }

      return steps;
    });

    const controlStoryText = computed(() => {
      const steps = controlJourney.value;
      if (steps.length < 2) return '';
      const first = steps[0];
      const last = steps[steps.length - 1];
      const gain = Math.round((last.avg_g - first.avg_g) * 10) / 10;
      return t('vengerControlJourneyStory', {
        from: fmt(first.avg_g, 0),
        to: fmt(last.avg_g, 0),
        gain: fmt(Math.abs(gain), 0),
        dir: gain >= 0 ? '+' : '−',
        n: Math.max(steps.filter((s) => s.kind === 'control').length, 1),
      });
    });

    const controlChartPoints = computed(() => {
      const steps = controlJourney.value;
      if (steps.length < 2) return [];
      const avgs = steps.map((s) => s.avg_g);
      const maxG = Math.max(...avgs, 1);
      const minG = Math.min(...avgs, 0);
      const span = Math.max(maxG - minG, 1);
      const colors = { stock: '#0a6b40', control: '#1a6fb5', sold: '#c47a12' };
      return steps.map((s, i) => {
        const x = 36 + (i / Math.max(steps.length - 1, 1)) * 300;
        const y = 112 - ((s.avg_g - minG) / span) * 86;
        let shortLabel = s.kind === 'stock' ? t('vengerCtrlChartStart') : s.kind === 'sold' ? t('vengerCtrlChartSold') : `${i}`;
        if (s.kind === 'control') {
          const ctrlIndex = steps.slice(0, i + 1).filter((x) => x.kind === 'control').length;
          shortLabel = `K${ctrlIndex}`;
        }
        return { x, y, avg_g: s.avg_g, color: colors[s.kind] || '#1a6fb5', shortLabel };
      });
    });

    const controlLinePath = computed(() => {
      const pts = controlChartPoints.value;
      if (!pts.length) return '';
      return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
    });

    const controlAreaPath = computed(() => {
      const pts = controlChartPoints.value;
      if (!pts.length) return '';
      const line = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
      const last = pts[pts.length - 1];
      const first = pts[0];
      return `${line} L ${last.x} 112 L ${first.x} 112 Z`;
    });

    const controlSummary = computed(() => {
      const steps = controlJourney.value;
      if (steps.length < 2) return null;
      const first = steps[0];
      const last = steps[steps.length - 1];
      let days = null;
      if (first.date && last.date) {
        try {
          const a = new Date(first.date);
          const b = new Date(last.date);
          days = Math.max(Math.round((b - a) / 86400000), 0);
        } catch (e) { /* ignore */ }
      }
      return {
        perFishGain: Math.round((last.avg_g - first.avg_g) * 10) / 10,
        estKg: last.est_kg ?? sazan.value.end_weight_kg ?? sazan.value.current_weight_kg,
        days,
      };
    });

    const densityPerHa = computed(() => {
      const pieces = Number(sazan.value.count || metrics.value.stocked_pieces || 0);
      const ha = Number(result.value?.area_ha || 0);
      if (!pieces || !ha) return null;
      return Math.round(pieces / ha);
    });

    const feedPerFishKg = computed(() => {
      const feed = Number(result.value?.feed_kg || sazan.value.feed_total_kg || 0);
      const pieces = Number(sazan.value.count || 0);
      if (!feed || !pieces) return null;
      return feed / pieces;
    });

    const feedPerFishLabel = computed(() => {
      const kg = feedPerFishKg.value;
      if (kg == null) return '—';
      if (kg < 1) return `${fmt(kg * 1000, 0)} g`;
      return `${fmt(kg, 2)} kg`;
    });

    const backTo = computed(() => {
      const clientId = result.value?.client?.id;
      if (clientId) {
        return auth.isOwner()
          ? { name: 'owner-venger-client', params: { clientId } }
          : { name: 'venger-client', params: { clientId } };
      }
      return auth.isOwner() ? { name: 'owner-venger-ponds' } : { name: 'venger-ponds' };
    });

    const growthPoints = computed(() => {
      const curve = metrics.value.growth_curve || [];
      if (!curve.length) return [];
      const maxW = Math.max(...curve.map((p) => p.weight_kg), 1);
      const minW = Math.min(...curve.map((p) => p.weight_kg), 0);
      const span = Math.max(maxW - minW, 1);
      return curve.map((p, i) => {
        const x = 40 + (i / Math.max(curve.length - 1, 1) * 300);
        const y = 130 - ((p.weight_kg - minW) / span) * 108;
        return { x, y, ...p };
      });
    });

    const growthLinePath = computed(() => {
      const pts = growthPoints.value;
      if (!pts.length) return '';
      return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
    });

    const growthAreaPath = computed(() => {
      const pts = growthPoints.value;
      if (!pts.length) return '';
      const line = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
      const last = pts[pts.length - 1];
      const first = pts[0];
      return `${line} L ${last.x} 130 L ${first.x} 130 Z`;
    });

    const feedBarHeight = computed(() => {
      const feed = Number(result.value?.feed_kg || 0);
      const gain = Math.max(Number(sazan.value.biomass_gain_kg || 0), 0);
      const max = Math.max(feed, gain, 1);
      return `${Math.max((feed / max) * 100, 8)}%`;
    });

    const gainBarHeight = computed(() => {
      const feed = Number(result.value?.feed_kg || 0);
      const gain = Math.max(Number(sazan.value.biomass_gain_kg || 0), 0);
      const max = Math.max(feed, gain, 1);
      return `${Math.max((gain / max) * 100, 8)}%`;
    });

    function fmt(v, decimals = 0) {
      const n = Number(v);
      if (!Number.isFinite(n)) return '0';
      return n.toLocaleString(undefined, {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals > 0 ? Math.min(decimals, 2) : 0,
      });
    }

    function formatDate(value) {
      if (!value) return '—';
      try { return new Date(value).toLocaleDateString(); } catch (e) { return value; }
    }

    function formatPhone(phone) {
      return formatPhoneDisplay(phone || '');
    }

    function buildTelegramCaption() {
      const r = result.value;
      if (!r) return '';
      const m = metrics.value;
      const s = sazan.value;
      const isFinal = phase.value === 'final';
      const isMid = phase.value === 'mid';
      const client = r.client?.name || '';
      const place = placeLine.value;
      const fcr = m.fcr != null ? fmt(m.fcr, 2) : null;
      const endKg = s.end_weight_kg ?? s.current_weight_kg ?? s.stocked_weight_kg;
      const days = m.duration_days || 0;
      const stockedOn = formatDate(m.stocked_on || r.stocked_on);
      const lastControl = controlRows.value.length
        ? controlRows.value[controlRows.value.length - 1]
        : null;
      const endOn = formatDate(m.sold_on || r.sold_on || lastControl?.checked_on);
      const feedKg = Number(r.feed_kg || 0);
      const gainKg = Number(s.biomass_gain_kg || 0);
      const lines = [];

      lines.push(isFinal ? t('vengerTgCapTitleFinal') : isMid ? t('vengerTgCapTitleMid') : t('vengerTgCapTitleStart'));
      lines.push('');
      lines.push(isFinal ? t('vengerTgCapLeadFinal') : isMid ? t('vengerTgCapLeadMid') : t('vengerTgCapLeadStart'));
      lines.push('');

      if (client) lines.push(t('vengerTgCapClient', { name: client }));
      if (place) lines.push(t('vengerTgCapPlace', { place }));
      lines.push(t('vengerTgCapArea', { ha: fmt(r.area_ha, 2) }));
      lines.push(t('vengerTgCapPeriod', { from: stockedOn, to: endOn, days }));
      lines.push('');

      lines.push(t('vengerTgCapBlockStock'));
      lines.push(t('vengerTgCapStockKg', { kg: fmt(s.stocked_weight_kg) }));
      if (s.count) lines.push(t('vengerTgCapStockPcs', { n: fmt(s.count) }));
      if (s.avg_start_weight_g != null) {
        lines.push(t('vengerTgCapStockAvg', { g: fmt(s.avg_start_weight_g, 0) }));
      }
      lines.push('');

      lines.push(isFinal ? t('vengerTgCapBlockSold') : t('vengerTgCapBlockNow'));
      lines.push(t('vengerTgCapNowKg', {
        kg: fmt(endKg),
        label: isFinal ? t('soldWeightKg') : t('vengerEstWeightKg'),
      }));
      if (isFinal && Number(s.sold_weight_kg) > 0) {
        lines.push(t('vengerTgCapSoldKg', { kg: fmt(s.sold_weight_kg) }));
      }
      if (s.avg_end_weight_g != null) {
        lines.push(t('vengerTgCapNowAvg', { g: fmt(s.avg_end_weight_g, 0) }));
      }
      if (gainKg) {
        lines.push(t('vengerTgCapGainLine', { kg: fmt(gainKg) }));
      }
      if (s.weight_multiplier) {
        lines.push(t('vengerTgCapMult', { n: fmt(s.weight_multiplier, 2) }));
      }
      if (s.avg_start_weight_g != null && s.avg_end_weight_g != null) {
        lines.push(t('vengerTgCapAvgPath', {
          from: fmt(s.avg_start_weight_g, 0),
          to: fmt(s.avg_end_weight_g, 0),
        }));
      }
      lines.push('');

      lines.push(t('vengerTgCapBlockFeed'));
      if (feedKg > 0) {
        lines.push(t('vengerTgCapFeedTotal', { kg: fmt(feedKg) }));
        if (feedSummary.value) lines.push(t('vengerTgCapFeedDetail', { detail: feedSummary.value }));
        if (feedPerFishLabel.value && feedPerFishLabel.value !== '—') {
          lines.push(t('vengerTgCapFeedPerFish', { v: feedPerFishLabel.value }));
        }
      } else {
        lines.push(t('vengerTgCapFeedEmpty'));
      }
      lines.push('');

      if (fcr) {
        lines.push(t('vengerTgCapBlockFcr'));
        lines.push(t('vengerTgCapFcrValue', { fcr }));
        lines.push(t('vengerTgCapFcrExplain', { fcr, feed: fmt(feedKg), gain: fmt(gainKg) }));
        lines.push('');
      }

      if (isFinal && m.fsr_percent != null) {
        lines.push(t('vengerTgCapFsr', { n: fmt(m.fsr_percent, 1) }));
        lines.push('');
      }

      lines.push(isFinal ? t('vengerTgCapCtaFinal') : t('vengerTgCapCtaMid'));
      lines.push(t('vengerTgCapPhone', { phone: t('homeContactPhone') }));
      lines.push('');
      lines.push(t('vengerTgCapTags'));
      return lines.join('\n');
    }

    function safeFileName(name) {
      return String(name || 'natija')
        .replace(/[^\w\u0400-\u04FF\-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 48) || 'natija';
    }

    function loadImageFromDataUrl(dataUrl) {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = dataUrl;
      });
    }

    function triggerDownload(dataUrl, filename) {
      const link = document.createElement('a');
      link.download = filename;
      link.href = dataUrl;
      document.body.appendChild(link);
      link.click();
      link.remove();
    }

    /**
     * Bo‘lish nuqtasi — bo‘limlar oralig‘ida (matn/diagramma o‘rtasidan emas).
     * Afzallik: kontroller → hikoya → oqim → spotlight.
     */
    function findSmartSplitCssY(board) {
      const boardRect = board.getBoundingClientRect();
      const fullH = Math.max(board.scrollHeight, board.offsetHeight, 1);
      const ideal = fullH * 0.5;
      const preferredSelectors = ['.vr-controls', '.vr-story', '.vr-flow', '.vr-spotlight'];
      const candidates = [];

      preferredSelectors.forEach((sel) => {
        const el = board.querySelector(`:scope > ${sel}`);
        if (!el) return;
        const y = el.getBoundingClientRect().bottom - boardRect.top;
        candidates.push({ y, preferred: true });
      });

      [...board.children].forEach((child, i, arr) => {
        if (i >= arr.length - 1) return;
        const y = child.getBoundingClientRect().bottom - boardRect.top;
        candidates.push({ y, preferred: false });
      });

      const scored = candidates
        .map((c) => {
          const ratio = c.y / fullH;
          const inBand = ratio >= 0.30 && ratio <= 0.72;
          const dist = Math.abs(c.y - ideal);
          // Bo‘lim oralig‘i + o‘rtaga yaqinroq = yaxshiroq
          const score = dist - (c.preferred && inBand ? 80 : 0) - (inBand ? 30 : 0);
          return { y: c.y, ratio, score };
        })
        .filter((c) => c.ratio >= 0.22 && c.ratio <= 0.80);

      if (!scored.length) return Math.round(ideal);
      scored.sort((a, b) => a.score - b.score);
      return Math.max(1, Math.round(scored[0].y));
    }

    async function downloadSplitPng(dataUrl, baseName, board) {
      const img = await loadImageFromDataUrl(dataUrl);
      const width = img.naturalWidth || img.width;
      const height = img.naturalHeight || img.height;
      const cssH = Math.max(board.scrollHeight, board.offsetHeight, 1);
      const scaleY = height / cssH;
      const splitCssY = findSmartSplitCssY(board);
      let mid = Math.round(splitCssY * scaleY);
      // Chegarani himoya qilish
      mid = Math.min(Math.max(mid, Math.round(height * 0.22)), Math.round(height * 0.80));

      const parts = [
        { top: 0, h: mid, suffix: '1' },
        { top: mid, h: height - mid, suffix: '2' },
      ];

      for (const part of parts) {
        if (part.h < 8) continue;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = part.h;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#f7fcf9';
        ctx.fillRect(0, 0, width, part.h);
        ctx.drawImage(img, 0, part.top, width, part.h, 0, 0, width, part.h);
        triggerDownload(canvas.toDataURL('image/png'), `${baseName}-qism${part.suffix}.png`);
        await new Promise((resolve) => setTimeout(resolve, 400));
      }
    }

    async function prepareTelegramShare() {
      if (!boardEl.value || exporting.value) return;
      exporting.value = true;
      shareOpen.value = true;
      shareCopied.value = false;
      shareStatus.value = t('vengerTgPreparing');

      const caption = buildTelegramCaption();
      shareText.value = caption;

      try {
        await navigator.clipboard.writeText(caption);
        shareCopied.value = true;
      } catch (e) {
        shareCopied.value = false;
      }

      const el = boardEl.value;
      const prevOverflow = el.style.overflow;
      el.style.overflow = 'visible';

      try {
        await nextTick();
        await new Promise((resolve) => setTimeout(resolve, 120));

        const dataUrl = await toPng(el, {
          cacheBust: true,
          pixelRatio: Math.min(typeof window !== 'undefined' ? (window.devicePixelRatio || 2) : 2, 2),
          backgroundColor: '#f7fcf9',
          style: { transform: 'none' },
        });

        const stamp = Date.now();
        const base = `venger-carp-${safeFileName(result.value?.client?.name)}-${stamp}`;
        await downloadSplitPng(dataUrl, base, el);

        shareStatus.value = shareCopied.value
          ? t('vengerTgReadyCopied')
          : t('vengerTgReadyManual');
      } catch (e) {
        console.error(e);
        shareStatus.value = t('vengerTgError');
      } finally {
        el.style.overflow = prevOverflow;
        exporting.value = false;
      }
    }

    async function copyShareText() {
      try {
        await navigator.clipboard.writeText(shareText.value);
        shareCopied.value = true;
        shareStatus.value = t('vengerTgTextCopied');
      } catch (e) {
        shareStatus.value = t('vengerTgCopyFail');
      }
    }

    async function load() {
      loading.value = true;
      error.value = '';
      try {
        const { data } = await window.axios.get(`${apiBase.value}/results/${route.params.resultId}`);
        result.value = data;
      } catch (e) {
        error.value = e.response?.data?.message || t('errorGeneric');
      } finally {
        loading.value = false;
      }
    }

    onMounted(load);

    return {
      route, loading, error, result, boardEl, metrics, sazan, phase, fishRows, showCurrentCol,
      canEdit, isOwnerView, backTo, phaseLabel, endDateLabel, gainLabel, endWeightLabel, placeLine,
      feedRows, feedSummary, feedTypeLabel, controlRows, controlJourney, controlStoryText,
      controlChartPoints, controlLinePath, controlAreaPath, controlSummary,
      densityPerHa, feedPerFishLabel,
      growthPoints, growthLinePath, growthAreaPath, feedBarHeight, gainBarHeight,
      exporting, shareOpen, shareText, shareStatus, shareCopied,
      prepareTelegramShare, copyShareText,
      fmt, formatDate, formatPhone,
    };
  },
};
</script>

<style scoped>
.vr-tg-share {
  margin: 0 0 1rem;
  padding: 1rem 1.1rem;
  border-radius: 16px;
  border: 1px solid rgba(13, 138, 82, 0.22);
  background: linear-gradient(160deg, #f3fbf6, #eef6f8);
}
.vr-tg-share__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.vr-tg-share__head h3 {
  margin: 0 0 0.25rem;
  font-size: 1.05rem;
}
.vr-tg-share__head p {
  margin: 0;
  font-size: 0.86rem;
  color: #4d6558;
  font-weight: 650;
  line-height: 1.4;
}
.vr-tg-share__label {
  display: block;
  font-size: 0.78rem;
  font-weight: 750;
  color: #4d6558;
  margin-bottom: 0.35rem;
}
.vr-tg-share__text {
  width: 100%;
  box-sizing: border-box;
  resize: vertical;
  min-height: 180px;
  padding: 0.75rem 0.85rem;
  border-radius: 12px;
  border: 1px solid rgba(10, 60, 40, 0.14);
  background: #fff;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.86rem;
  line-height: 1.45;
  color: #0b1f16;
}
.vr-tg-share__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.vr-board {
  --vr-ink: #0b1f16;
  --vr-muted: #4d6558;
  --vr-green: #0d8a52;
  --vr-green-deep: #065c36;
  --vr-teal: #0b7a6a;
  --vr-amber: #c47a12;
  --vr-blue: #1a6fb5;
  --vr-rose: #b84343;
  --vr-paper: #f4faf6;
  position: relative;
  overflow: hidden;
  border-radius: 22px;
  border: 1px solid rgba(6, 70, 42, 0.16);
  background:
    radial-gradient(900px 320px at 100% -20%, rgba(13, 138, 82, 0.14), transparent 55%),
    radial-gradient(700px 280px at -10% 30%, rgba(26, 111, 181, 0.1), transparent 50%),
    linear-gradient(165deg, #f7fcf9 0%, #eef6f1 48%, #f5f9f6 100%);
  color: var(--vr-ink);
  box-shadow: 0 18px 48px rgba(8, 40, 26, 0.1);
  padding: 1.25rem;
}

.vr-hero {
  margin-bottom: 1.1rem;
  padding: 1.05rem 1.1rem;
  border-radius: 18px;
  background: linear-gradient(135deg, #053825 0%, #0a6b40 55%, #0d8a52 100%);
  color: #fff;
  box-shadow: 0 12px 28px rgba(5, 56, 37, 0.28);
}
.vr-hero__top {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 0.95rem;
}
.vr-hero__brand {
  display: flex;
  gap: 0.85rem;
  align-items: center;
}
.vr-hero h2 {
  margin: 0;
  font-size: clamp(1.4rem, 2.5vw, 1.95rem);
  line-height: 1.12;
  letter-spacing: -0.02em;
}
.vr-hero__client {
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.95rem;
}
.vr-hero__feed {
  margin: 0.45rem 0 0;
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.5rem;
  padding: 0.28rem 0.65rem;
  border-radius: 999px;
  background: rgba(255, 244, 214, 0.95);
  color: #5a3a00;
  font-size: 0.8rem;
  font-weight: 700;
}
.vr-hero__feed span {
  opacity: 0.75;
  font-weight: 650;
}
.vr-hero__feed strong {
  font-weight: 800;
  color: #3d2800;
}
.vr-phase {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.75rem;
  border-radius: 10px;
  font-size: 0.78rem;
  font-weight: 800;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  white-space: nowrap;
}
.vr-phase--mid { background: #f0b429; color: #3d2a00; }
.vr-phase--final { background: #7dd3a0; color: #053825; }

.vr-hero__strip {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  align-items: center;
  padding: 0.75rem 0.85rem;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.18);
}
.vr-hero__date span {
  display: block;
  font-size: 0.72rem;
  color: rgba(255, 255, 255, 0.7);
}
.vr-hero__date strong { font-size: 0.98rem; }
.vr-hero__line {
  width: 28px;
  height: 2px;
  background: rgba(255, 255, 255, 0.45);
  border-radius: 2px;
}
.vr-hero__chips {
  display: flex;
  gap: 0.45rem;
  margin-left: auto;
}
.vr-hero__chips span {
  padding: 0.28rem 0.6rem;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.14);
  font-size: 0.78rem;
  font-weight: 700;
}

.vr-spotlight {
  display: grid;
  grid-template-columns: 1.05fr 1.2fr;
  gap: 0.85rem;
  margin-bottom: 0.9rem;
}
.vr-fcr {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  padding: 1.15rem 1.2rem;
  background: linear-gradient(145deg, #0b3d6e 0%, #1a6fb5 60%, #2f8fd4 100%);
  color: #fff;
  box-shadow: 0 10px 26px rgba(18, 80, 140, 0.25);
}
.vr-fcr::after {
  content: '';
  position: absolute;
  right: -20%;
  top: -30%;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}
.vr-fcr__label {
  display: block;
  font-size: 0.82rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.85;
}
.vr-fcr__value {
  display: block;
  margin: 0.15rem 0;
  font-size: clamp(2.6rem, 5vw, 3.6rem);
  line-height: 1;
  font-weight: 900;
  letter-spacing: -0.03em;
}
.vr-fcr__hint {
  margin: 0 0 0.75rem;
  font-size: 0.82rem;
  opacity: 0.88;
}
.vr-fcr__formula {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 0.55rem;
  align-items: center;
  font-size: 0.86rem;
  font-weight: 700;
  position: relative;
  z-index: 1;
}
.vr-fcr__formula > span:not(.vr-fcr__div) {
  padding: 0.3rem 0.55rem;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.16);
}
.vr-fcr__div { opacity: 0.75; font-size: 1.1rem; }

.vr-spotlight__side {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.7rem;
}
.vr-metric {
  border-radius: 14px;
  padding: 0.85rem 0.95rem;
  display: grid;
  gap: 0.12rem;
  border: 1px solid transparent;
  background: #fff;
}
.vr-metric span { font-size: 0.76rem; font-weight: 800; color: var(--vr-muted); }
.vr-metric strong { font-size: 1.45rem; line-height: 1.05; }
.vr-metric small { font-size: 0.85rem; font-weight: 800; }
.vr-metric em { font-style: normal; font-size: 0.78rem; font-weight: 700; }
.vr-metric--gain {
  grid-column: 1 / -1;
  background: linear-gradient(135deg, #d9f5e6, #f2fcf7);
  border-color: rgba(13, 138, 82, 0.22);
  color: var(--vr-green-deep);
}
.vr-metric--gain strong { font-size: 1.7rem; color: var(--vr-green-deep); }
.vr-metric--gain em { color: var(--vr-green); }
.vr-metric--feed {
  background: linear-gradient(135deg, #fff3df, #fffaf2);
  border-color: rgba(196, 122, 18, 0.25);
}
.vr-metric--feed strong { color: #8a5200; }
.vr-metric--feed em { color: var(--vr-amber); }
.vr-metric--end {
  background: linear-gradient(135deg, #e4f2ff, #f5faff);
  border-color: rgba(26, 111, 181, 0.22);
}
.vr-metric--end strong { color: #0d4d86; }
.vr-metric--end em { color: var(--vr-blue); }

.vr-flow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  align-items: stretch;
  margin-bottom: 0.9rem;
}
.vr-flow__item {
  flex: 1 1 120px;
  min-width: 110px;
  border-radius: 14px;
  padding: 0.75rem 0.85rem;
  background: #fff;
  border: 1px solid rgba(10, 60, 40, 0.1);
  display: grid;
  gap: 0.15rem;
}
.vr-flow__item span { font-size: 0.72rem; font-weight: 800; color: var(--vr-muted); }
.vr-flow__item strong { font-size: 1.15rem; }
.vr-flow__item small { font-size: 0.78rem; font-weight: 800; }
.vr-flow__item em {
  font-style: normal;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--vr-muted);
}
.vr-flow__item--stock { border-top: 3px solid #6b8790; }
.vr-flow__item--mid { border-top: 3px solid var(--vr-amber); background: #fffaf0; }
.vr-flow__item--sold { border-top: 3px solid var(--vr-blue); background: #f3f8ff; }
.vr-flow__item--gain {
  border-top: 3px solid var(--vr-green);
  background: #eaf9f1;
  color: var(--vr-green-deep);
}
.vr-flow__item--loss {
  border-top: 3px solid var(--vr-rose);
  background: #fff1f1;
  color: #8a2e2e;
  flex: 0 1 130px;
}
.vr-flow__arrow {
  display: flex;
  align-items: center;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--vr-green);
  padding: 0 0.1rem;
}

.vr-story {
  margin-bottom: 0.9rem;
  padding: 0.95rem 1.05rem;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid rgba(10, 60, 40, 0.08);
  border-left: 4px solid var(--vr-green);
}
.vr-story h3 { margin: 0 0 0.3rem; font-size: 1.02rem; }
.vr-story p { margin: 0; color: var(--vr-muted); line-height: 1.5; font-size: 0.95rem; }
.vr-story__list {
  margin: 0.55rem 0 0;
  padding-left: 1.1rem;
  color: #234033;
}
.vr-story__list li { margin-bottom: 0.2rem; }
.vr-pcs {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #5b7266;
  font-weight: 650;
}
.vr-feeds-total {
  margin: 0.55rem 0 0;
  color: #3a5548;
  font-size: 0.92rem;
}
.vr-feeds-total strong { color: #0a6b40; }
.vr-feeds {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.7rem;
}
.vr-feed-chip {
  display: inline-flex;
  padding: 0.28rem 0.6rem;
  border-radius: 8px;
  background: #e8f5ee;
  color: #065c36;
  font-size: 0.78rem;
  font-weight: 700;
}
.vr-controls { margin-bottom: 0.9rem; }
.vr-controls.is-history { opacity: 0.88; }
.vr-controls__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.vr-controls__title-row {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  min-width: 0;
}
.vr-controls__fish {
  width: 56px;
  height: 36px;
  object-fit: contain;
  flex-shrink: 0;
}
.vr-controls__badge {
  flex-shrink: 0;
  padding: 0.28rem 0.55rem;
  border-radius: 999px;
  background: rgba(13, 138, 82, 0.12);
  color: var(--vr-green-deep);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}
.vr-controls__story {
  margin: 0 0 0.65rem;
  font-size: 0.92rem;
  font-weight: 650;
  line-height: 1.45;
  color: var(--vr-ink);
}
.vr-controls__note {
  margin: 0 0 0.55rem;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--vr-muted);
  line-height: 1.35;
}
.vr-ctrl-chart {
  display: block;
  width: 100%;
  max-width: 520px;
  height: auto;
  margin: 0 auto 0.85rem;
}
.vr-ctrl-timeline {
  display: grid;
  gap: 0;
  position: relative;
  margin-bottom: 0.75rem;
}
.vr-ctrl-step {
  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 0.65rem;
  position: relative;
  padding-bottom: 0.85rem;
}
.vr-ctrl-step:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 16px;
  bottom: 0;
  width: 2px;
  background: linear-gradient(180deg, rgba(26, 111, 181, 0.45), rgba(26, 111, 181, 0.08));
}
.vr-ctrl-step__dot {
  width: 14px;
  height: 14px;
  margin-top: 4px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 0 0 2px rgba(26, 111, 181, 0.35);
  background: #1a6fb5;
  z-index: 1;
}
.vr-ctrl-step--stock .vr-ctrl-step__dot { background: #0a6b40; box-shadow: 0 0 0 2px rgba(10, 107, 64, 0.35); }
.vr-ctrl-step--sold .vr-ctrl-step__dot { background: #c47a12; box-shadow: 0 0 0 2px rgba(196, 122, 18, 0.35); }
.vr-ctrl-step__body {
  padding: 0.45rem 0.65rem;
  border-radius: 12px;
  background: rgba(244, 250, 246, 0.95);
  border: 1px solid rgba(10, 60, 40, 0.06);
}
.vr-ctrl-step__top {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.35rem 0.75rem;
  margin-bottom: 0.35rem;
  font-size: 0.86rem;
}
.vr-ctrl-step__top strong { font-weight: 800; }
.vr-ctrl-step__top span { color: var(--vr-muted); font-weight: 650; font-size: 0.8rem; }
.vr-ctrl-step__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.vr-ctrl-pill {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 8px;
  background: rgba(26, 111, 181, 0.1);
  color: #145a94;
  font-size: 0.78rem;
  font-weight: 800;
}
.vr-ctrl-pill--muted {
  background: rgba(10, 50, 30, 0.06);
  color: var(--vr-muted);
}
.vr-ctrl-pill--up {
  background: rgba(13, 138, 82, 0.12);
  color: var(--vr-green-deep);
}
.vr-ctrl-pill--down {
  background: rgba(184, 67, 67, 0.12);
  color: var(--vr-rose);
}
.vr-ctrl-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem;
  padding-top: 0.15rem;
}
.vr-ctrl-summary__item {
  padding: 0.55rem 0.65rem;
  border-radius: 12px;
  background: linear-gradient(160deg, rgba(26, 111, 181, 0.08), rgba(13, 138, 82, 0.06));
  border: 1px solid rgba(26, 111, 181, 0.1);
}
.vr-ctrl-summary__item span {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--vr-muted);
  margin-bottom: 0.2rem;
}
.vr-ctrl-summary__item strong {
  font-size: 1rem;
  font-weight: 850;
  color: var(--vr-ink);
}
@media (max-width: 640px) {
  .vr-controls__head { flex-direction: column; }
  .vr-ctrl-summary { grid-template-columns: 1fr; }
  .vr-growth__daily { grid-template-columns: 1fr; }
  .vr-growth__fish { width: 72px; height: 46px; }
}

.vr-visuals {
  display: grid;
  grid-template-columns: 1.35fr 1fr;
  gap: 0.85rem;
  margin-bottom: 0.9rem;
}
.vr-panel {
  background: rgba(255, 255, 255, 0.94);
  border-radius: 16px;
  padding: 1rem;
  border: 1px solid rgba(10, 60, 40, 0.08);
}
.vr-panel__head { margin-bottom: 0.65rem; }
.vr-panel__head h3 { margin: 0; font-size: 1.02rem; }
.vr-panel__head small { color: var(--vr-muted); font-size: 0.82rem; }
.vr-panel__note {
  margin: 0.75rem 0 0;
  font-size: 0.84rem;
  color: var(--vr-muted);
  padding: 0.55rem 0.7rem;
  border-radius: 10px;
  background: #f0f7f3;
}
.vr-chart { width: 100%; height: auto; display: block; }

.vr-growth__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.vr-growth__fish {
  width: 88px;
  height: 56px;
  object-fit: contain;
  flex-shrink: 0;
  filter: drop-shadow(0 4px 10px rgba(8, 40, 26, 0.18));
}
.vr-growth__daily {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.45rem;
  margin-bottom: 0.7rem;
}
.vr-growth__daily-item {
  padding: 0.55rem 0.65rem;
  border-radius: 12px;
  background: linear-gradient(145deg, #e8f8ef, #f7fcf9);
  border: 1px solid rgba(13, 138, 82, 0.18);
}
.vr-growth__daily-item span {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--vr-muted);
  margin-bottom: 0.15rem;
}
.vr-growth__daily-item strong {
  font-size: 1.15rem;
  color: var(--vr-green-deep);
  letter-spacing: -0.02em;
}

.vr-bars {
  display: flex;
  gap: 1.75rem;
  justify-content: center;
  align-items: flex-start; /* ikkala ustun bir xil qatordan */
  min-height: 190px;
  padding: 0.25rem 0 0.15rem;
}
.vr-bar {
  display: grid;
  grid-template-rows: 1.4rem 150px auto;
  justify-items: center;
  align-content: start;
  gap: 0.35rem;
  width: 100px;
}
.vr-bar__track {
  width: 58px;
  height: 150px; /* ikkala treyning balandligi bir xil */
  border-radius: 14px;
  background: #e8efea;
  display: flex;
  align-items: flex-end; /* to‘ldirish pastdan — umumiy “nol” chiziq */
  overflow: hidden;
  box-shadow: inset 0 0 0 1px rgba(10, 60, 40, 0.06);
}
.vr-bar__fill {
  width: 100%;
  border-radius: 12px 12px 0 0;
  transition: height 0.5s ease;
  min-height: 6px;
}
.vr-bar__fill--feed { background: linear-gradient(180deg, #f0b429, #c47a12); }
.vr-bar__fill--gain { background: linear-gradient(180deg, #2fd887, #0d8a52); }
.vr-bar strong {
  font-size: 0.98rem;
  line-height: 1.2;
  text-align: center;
}
.vr-bar strong small {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--vr-muted);
}
.vr-bar span {
  font-size: 0.72rem;
  color: var(--vr-muted);
  text-align: center;
  font-weight: 700;
  line-height: 1.25;
  min-height: 2.2em;
}

.vr-fish { margin-bottom: 0.9rem; }
.vr-fish table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.vr-fish th, .vr-fish td {
  padding: 0.6rem 0.5rem;
  text-align: left;
  border-bottom: 1px solid rgba(10, 60, 40, 0.08);
}
.vr-fish th { font-size: 0.74rem; color: var(--vr-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
.vr-fish tr.is-sazan { background: rgba(13, 138, 82, 0.08); }
.vr-fish__tag {
  display: inline-block;
  margin-left: 0.35rem;
  padding: 0.1rem 0.4rem;
  border-radius: 6px;
  font-size: 0.68rem;
  font-weight: 800;
  background: #0d8a52;
  color: #fff;
  vertical-align: middle;
}
.vr-num--sold { color: var(--vr-blue); font-weight: 700; }
.vr-num--loss { color: var(--vr-rose); font-weight: 700; }
.vr-num--gain { color: var(--vr-green-deep); font-weight: 800; }

.vr-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(148px, 1fr));
  gap: 0.6rem;
  margin-bottom: 0.9rem;
}
.vr-stat {
  position: relative;
  border-radius: 14px;
  padding: 0.75rem 0.8rem 0.7rem 0.95rem;
  border: 1px solid transparent;
  overflow: hidden;
  box-shadow: 0 4px 14px rgba(8, 40, 26, 0.06);
}
.vr-stat::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: currentColor;
  opacity: 0.85;
}
.vr-stat span {
  display: block;
  font-size: 0.76rem;
  margin-bottom: 0.2rem;
  font-weight: 800;
  letter-spacing: 0.01em;
}
.vr-stat strong {
  display: block;
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.15;
}
.vr-stat em {
  display: block;
  margin-top: 0.28rem;
  font-style: normal;
  font-size: 0.7rem;
  line-height: 1.3;
  font-weight: 600;
  opacity: 0.78;
}

/* kontekst — o‘rtacha muhim */
.vr-stat--slate {
  color: #3a4a55;
  background: linear-gradient(145deg, #eef2f5 0%, #f8fafb 100%);
  border-color: rgba(58, 74, 85, 0.14);
}
.vr-stat--slate strong { color: #243039; }

.vr-stat--blue {
  color: #1a5f96;
  background: linear-gradient(145deg, #e4f0fb 0%, #f5f9fd 100%);
  border-color: rgba(26, 95, 150, 0.18);
}
.vr-stat--blue strong { color: #0d4570; }

.vr-stat--sage {
  color: #4a6b58;
  background: linear-gradient(145deg, #e8f0eb 0%, #f6faf7 100%);
  border-color: rgba(74, 107, 88, 0.16);
}
.vr-stat--sage strong { color: #2a4434; }

/* asosiy natijalar — kuchliroq */
.vr-stat--green {
  color: #0a6b40;
  background: linear-gradient(145deg, #d8f3e4 0%, #eefaf3 55%, #fff 100%);
  border-color: rgba(10, 107, 64, 0.28);
  box-shadow: 0 6px 18px rgba(10, 107, 64, 0.12);
}
.vr-stat--green strong { color: #053825; font-size: 1.28rem; }

.vr-stat--amber {
  color: #9a6208;
  background: linear-gradient(145deg, #fff0c8 0%, #fff8e8 55%, #fff 100%);
  border-color: rgba(196, 122, 18, 0.32);
  box-shadow: 0 6px 18px rgba(196, 122, 18, 0.14);
}
.vr-stat--amber strong { color: #7a4a00; font-size: 1.28rem; }

.vr-stat--teal {
  color: #0d6e6a;
  background: linear-gradient(145deg, #d4f2f0 0%, #eefaf9 55%, #fff 100%);
  border-color: rgba(13, 110, 106, 0.24);
}
.vr-stat--teal strong { color: #084845; }

.vr-stat--orange {
  color: #b85a12;
  background: linear-gradient(145deg, #ffe6d0 0%, #fff4ea 55%, #fff 100%);
  border-color: rgba(196, 100, 30, 0.28);
  box-shadow: 0 6px 18px rgba(196, 100, 30, 0.12);
}
.vr-stat--orange strong { color: #8a3d08; font-size: 1.22rem; }

.vr-stat--sky {
  color: #1458a0;
  background: linear-gradient(145deg, #dcecff 0%, #eef5ff 55%, #fff 100%);
  border-color: rgba(20, 88, 160, 0.26);
}
.vr-stat--sky strong { color: #0a3d75; }

.vr-notes {
  background: rgba(255, 255, 255, 0.92);
  border-radius: 14px;
  padding: 0.95rem 1rem;
  margin-bottom: 0.9rem;
  border: 1px solid rgba(10, 60, 40, 0.08);
}
.vr-notes h3 { margin: 0 0 0.35rem; }
.vr-notes p { margin: 0; white-space: pre-wrap; color: var(--vr-muted); }

.vr-foot {
  border-top: 1px dashed rgba(10, 60, 40, 0.2);
  padding-top: 0.8rem;
  color: var(--vr-muted);
  font-size: 0.86rem;
}
.vr-foot p { margin: 0 0 0.2rem; }

@media (max-width: 980px) {
  .vr-spotlight,
  .vr-visuals,
  .vr-spotlight__side,
  .vr-stats { grid-template-columns: 1fr 1fr; }
  .vr-metric--gain { grid-column: auto; }
}
@media (max-width: 640px) {
  .vr-board { padding: 0.9rem; border-radius: 16px; }
  .vr-spotlight,
  .vr-visuals,
  .vr-spotlight__side,
  .vr-stats { grid-template-columns: 1fr; }
  .vr-hero__chips { margin-left: 0; width: 100%; }
  .vr-flow__arrow { display: none; }
}

@media print {
  .no-print { display: none !important; }
  .vr-board { box-shadow: none; border: none; border-radius: 0; }
}
</style>
