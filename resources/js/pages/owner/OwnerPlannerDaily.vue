<template>
  <div class="owner-planner-daily">
    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="owner-planner-legend">
        <span class="owner-planner-legend__item owner-planner-legend__item--empty">{{ $t('ownerPlannerLegendFree') }}</span>
        <span class="owner-planner-legend__item owner-planner-legend__item--tone-red">{{ $t('ownerPlannerLegendMistake') }}</span>
        <span class="owner-planner-legend__hint">{{ $t('ownerPlannerLegendHint') }}</span>
      </div>

      <div class="owner-planner-board owner-planner-board--daily">
        <article
          v-for="bundle in [today, tomorrow]"
          :key="bundle.date"
          class="owner-planner-sheet owner-planner-sheet--active"
        >
          <header class="owner-planner-sheet__title owner-planner-sheet__title--daily">
            <div>
              <strong>{{ bundle.date === today.date ? $t('ownerPlannerToday') : $t('ownerPlannerTomorrow') }}</strong>
              <small>{{ formatPlannerDate(bundle.date) }} · {{ weekdayLabel(bundle.weekday) }}</small>
            </div>
            <span v-if="savingDate === bundle.date" class="owner-planner-save-hint">{{ $t('saving') }}</span>
          </header>

          <div v-if="bundle.from_monthly?.length" class="owner-planner-daily-yearly owner-planner-daily-yearly--monthly">
            <h3>{{ $t('ownerPlannerFromMonthly') }}</h3>
            <ul>
              <li v-for="(item, i) in bundle.from_monthly" :key="`m-${bundle.date}-${i}`">
                <span class="owner-planner-daily-dot" :class="item.color ? `owner-planner-tone--${item.color}` : ''" />
                {{ item.text }}
              </li>
            </ul>
          </div>

          <div v-if="bundle.from_yearly.length" class="owner-planner-daily-yearly">
            <h3>{{ $t('ownerPlannerFromYearly') }}</h3>
            <ul>
              <li v-for="(item, i) in bundle.from_yearly" :key="`y-${bundle.date}-${i}`">
                <span class="owner-planner-daily-dot" :class="item.color ? `owner-planner-tone--${item.color}` : ''" />
                {{ item.text }}
              </li>
            </ul>
          </div>

          <div class="owner-planner-sheet__body">
            <section
              v-for="prayer in prayers"
              :key="`${bundle.date}-${prayer.key}`"
              class="owner-planner-prayer-block"
            >
              <div class="owner-planner-lines">
                <div
                  v-for="(cell, cellIndex) in bundle.schedule[prayer.beforeKey]"
                  :key="`${bundle.date}-${prayer.beforeKey}-${cellIndex}`"
                  class="owner-planner-line-row"
                >
                  <button
                    v-if="!isCellEmpty(cell)"
                    type="button"
                    class="owner-planner-tone"
                    :class="cell.color ? `owner-planner-tone--${cell.color}` : ''"
                    @click="cycleCellColor(cell, bundle)"
                  />
                  <textarea
                    v-model="cell.text"
                    class="owner-planner-line"
                    :class="lineToneClass(cell)"
                    rows="1"
                    @input="onCellInput(cell, bundle, $event)"
                  />
                  <button
                    v-if="bundle.schedule[prayer.beforeKey].length > 1"
                    type="button"
                    class="owner-planner-line-remove"
                    tabindex="-1"
                    @click="removeCell(bundle, prayer.beforeKey, cellIndex)"
                  ><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
              </div>

              <div class="owner-planner-mark">
                <span>{{ $t(prayer.labelKey) }}</span>
                <button
                  type="button"
                  class="owner-planner-plus"
                  :aria-expanded="addMenuKey === menuKey(bundle.date, prayer.key)"
                  :aria-label="$t('add')"
                  @click="toggleAddMenu(bundle.date, prayer.key)"
                >
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </button>
              </div>

              <div
                v-if="addMenuKey === menuKey(bundle.date, prayer.key)"
                class="owner-planner-add-picker"
              >
                <button type="button" @click="addCell(bundle, prayer.beforeKey)">
                  {{ $t('ownerPlannerAddBeforeShort') }}
                </button>
                <button type="button" @click="addCell(bundle, prayer.afterKey)">
                  {{ $t('ownerPlannerAddAfterShort') }}
                </button>
              </div>

              <div class="owner-planner-lines">
                <div
                  v-for="(cell, cellIndex) in bundle.schedule[prayer.afterKey]"
                  :key="`${bundle.date}-${prayer.afterKey}-${cellIndex}`"
                  class="owner-planner-line-row"
                >
                  <button
                    v-if="!isCellEmpty(cell)"
                    type="button"
                    class="owner-planner-tone"
                    :class="cell.color ? `owner-planner-tone--${cell.color}` : ''"
                    @click="cycleCellColor(cell, bundle)"
                  />
                  <textarea
                    v-model="cell.text"
                    class="owner-planner-line"
                    :class="lineToneClass(cell)"
                    rows="1"
                    @input="onCellInput(cell, bundle, $event)"
                  />
                  <button
                    v-if="bundle.schedule[prayer.afterKey].length > 1"
                    type="button"
                    class="owner-planner-line-remove"
                    tabindex="-1"
                    @click="removeCell(bundle, prayer.afterKey, cellIndex)"
                  ><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
              </div>
            </section>
          </div>
        </article>
      </div>

      <section class="owner-planner-inbox">
        <header class="owner-planner-inbox__head">
          <div>
            <h2>{{ $t('ownerPlannerInboxTitle') }}</h2>
            <small>{{ $t('ownerPlannerInboxHint') }}</small>
          </div>
        </header>

        <form class="owner-planner-inbox-add" @submit.prevent="addInbox">
          <input v-model.trim="inboxDraft" type="text" :placeholder="$t('ownerPlannerInboxPlaceholder')" />
          <button class="btn btn-primary btn-sm" type="submit" :disabled="!inboxDraft || inboxSaving">
            {{ $t('add') }}
          </button>
        </form>

        <div v-if="!inbox.length" class="owner-planner-daily-empty">{{ $t('ownerPlannerInboxEmpty') }}</div>

        <ul v-else class="owner-planner-inbox-list">
          <li v-for="task in inbox" :key="task.id">
            <span class="owner-planner-daily-dot" :class="task.color ? `owner-planner-tone--${task.color}` : ''" />
            <span class="owner-planner-inbox-list__text">{{ task.text }}</span>
            <button type="button" class="btn btn-outline btn-sm" @click="completeInbox(task)">
              {{ $t('ownerPlannerMarkDone') }}
            </button>
            <button type="button" class="owner-planner-line-remove" @click="removeInbox(task)"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
          </li>
        </ul>

        <div v-if="completed.length" class="owner-planner-inbox-done">
          <h3>{{ $t('ownerPlannerCompleted') }}</h3>
          <ul>
            <li v-for="task in completed" :key="`c-${task.id}`">
              <s>{{ task.text }}</s>
            </li>
          </ul>
        </div>
      </section>
    </template>
  </div>
</template>

<script>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { t } from '../../i18n';
import {
  PLANNER_DAYS,
  PLANNER_PRAYERS,
  PLANNER_TONES,
  emptyCell,
  emptyDaySlots,
  formatPlannerDate,
  isCellEmpty,
  lineToneClass,
  nextTone,
  normalizeDaySlots,
} from '../../config/ownerPlanner';

function emptyBundle(date) {
  return {
    date,
    weekday: 'mon',
    schedule: emptyDaySlots(),
    from_yearly: [],
    from_monthly: [],
  };
}

export default {
  setup() {
    const loading = ref(true);
    const savingDate = ref('');
    const addMenuKey = ref('');
    const today = reactive(emptyBundle(''));
    const tomorrow = reactive(emptyBundle(''));
    const inbox = ref([]);
    const completed = ref([]);
    const inboxDraft = ref('');
    const inboxSaving = ref(false);
    const prayers = PLANNER_PRAYERS;
    const saveTimers = {};
    let toneCounter = 0;

    const weekdayMap = Object.fromEntries(PLANNER_DAYS.map((d) => [d.key, d.labelKey]));

    function weekdayLabel(key) {
      return t(weekdayMap[key] || 'plannerDayMon');
    }

    function menuKey(date, prayerKey) {
      return `${date}-${prayerKey}`;
    }

    function pickAutoTone() {
      const tone = PLANNER_TONES[toneCounter % PLANNER_TONES.length];
      toneCounter += 1;
      return tone === 'red' ? PLANNER_TONES[(toneCounter++) % PLANNER_TONES.length] : tone;
    }

    function applyBundle(target, payload) {
      target.date = payload.date;
      target.weekday = payload.weekday;
      target.from_yearly = payload.from_yearly || [];
      target.from_monthly = payload.from_monthly || [];
      const schedule = normalizeDaySlots(payload.schedule || {});
      Object.keys(schedule).forEach((slot) => {
        target.schedule[slot] = schedule[slot];
      });
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/planner/daily');
        applyBundle(today, data.today || emptyBundle(''));
        applyBundle(tomorrow, data.tomorrow || emptyBundle(''));
        inbox.value = data.inbox || [];
        completed.value = data.completed || [];
      } finally {
        loading.value = false;
      }
    }

    async function saveSchedule(bundle) {
      savingDate.value = bundle.date;
      try {
        const { data } = await window.axios.put('/owner/planner/daily/extras', {
          date: bundle.date,
          schedule: bundle.schedule,
        });
        applyBundle(bundle, {
          ...bundle,
          schedule: data.schedule || bundle.schedule,
        });
      } finally {
        savingDate.value = '';
      }
    }

    function queueSave(bundle) {
      if (saveTimers[bundle.date]) clearTimeout(saveTimers[bundle.date]);
      saveTimers[bundle.date] = setTimeout(() => saveSchedule(bundle), 800);
    }

    function onCellInput(cell, bundle, event) {
      if (isCellEmpty(cell)) cell.color = null;
      else if (!cell.color) cell.color = pickAutoTone();
      const el = event.target;
      el.style.height = 'auto';
      el.style.height = `${el.scrollHeight}px`;
      queueSave(bundle);
    }

    function cycleCellColor(cell, bundle) {
      if (isCellEmpty(cell)) return;
      cell.color = nextTone(cell.color);
      queueSave(bundle);
    }

    function toggleAddMenu(date, prayerKey) {
      const key = menuKey(date, prayerKey);
      addMenuKey.value = addMenuKey.value === key ? '' : key;
    }

    function addCell(bundle, slotKey) {
      bundle.schedule[slotKey].push(emptyCell());
      addMenuKey.value = '';
      queueSave(bundle);
    }

    function removeCell(bundle, slotKey, cellIndex) {
      if (bundle.schedule[slotKey].length <= 1) return;
      bundle.schedule[slotKey].splice(cellIndex, 1);
      queueSave(bundle);
    }

    async function addInbox() {
      if (!inboxDraft.value) return;
      inboxSaving.value = true;
      try {
        const { data } = await window.axios.post('/owner/planner/inbox', { text: inboxDraft.value });
        inbox.value.push(data);
        inboxDraft.value = '';
      } finally {
        inboxSaving.value = false;
      }
    }

    async function completeInbox(task) {
      const { data } = await window.axios.post(`/owner/planner/inbox/${task.id}/complete`);
      inbox.value = inbox.value.filter((item) => item.id !== task.id);
      completed.value = [data, ...completed.value].slice(0, 30);
    }

    async function removeInbox(task) {
      await window.axios.delete(`/owner/planner/inbox/${task.id}`);
      inbox.value = inbox.value.filter((item) => item.id !== task.id);
    }

    function onDocClick(event) {
      if (!addMenuKey.value) return;
      const target = event.target;
      if (!(target instanceof Element)) return;
      if (target.closest('.owner-planner-add-picker') || target.closest('.owner-planner-plus')) return;
      addMenuKey.value = '';
    }

    function onKeydown(event) {
      if (event.key === 'Escape') addMenuKey.value = '';
    }

    onMounted(() => {
      load();
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeydown);
    });
    onBeforeUnmount(() => {
      Object.values(saveTimers).forEach((timer) => clearTimeout(timer));
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeydown);
    });

    return {
      loading,
      savingDate,
      addMenuKey,
      today,
      tomorrow,
      inbox,
      completed,
      inboxDraft,
      inboxSaving,
      prayers,
      isCellEmpty,
      lineToneClass,
      formatPlannerDate,
      weekdayLabel,
      menuKey,
      onCellInput,
      cycleCellColor,
      toggleAddMenu,
      addCell,
      removeCell,
      addInbox,
      completeInbox,
      removeInbox,
    };
  },
};
</script>
