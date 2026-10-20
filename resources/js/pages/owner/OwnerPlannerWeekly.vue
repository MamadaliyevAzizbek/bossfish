<template>
  <div class="owner-planner-weekly">
    <div class="owner-planner-status-row">
      <span v-if="saving" class="owner-planner-save-hint">{{ $t('saving') }}</span>
      <span v-else-if="savedAt" class="owner-planner-save-hint">{{ $t('ownerPlannerSaved') }}</span>
    </div>

    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <div class="owner-planner-legend">
        <span class="owner-planner-legend__item owner-planner-legend__item--empty">{{ $t('ownerPlannerLegendFree') }}</span>
        <span class="owner-planner-legend__item owner-planner-legend__item--tone-red">{{ $t('ownerPlannerLegendMistake') }}</span>
        <span class="owner-planner-legend__hint">{{ $t('ownerPlannerLegendHint') }}</span>
      </div>

      <div class="owner-planner-days" role="tablist">
        <button
          v-for="day in days"
          :key="day.key"
          type="button"
          class="owner-planner-day-tab"
          :class="{ 'owner-planner-day-tab--active': activeDay === day.key }"
          @click="activeDay = day.key"
        >
          {{ $t(day.shortLabelKey) }}
        </button>
      </div>

      <div class="owner-planner-board">
        <article
          v-for="day in days"
          :key="day.key"
          class="owner-planner-sheet"
          :class="{ 'owner-planner-sheet--active': activeDay === day.key }"
        >
          <h2 class="owner-planner-sheet__title">{{ $t(day.labelKey) }}</h2>

          <div class="owner-planner-sheet__body">
            <section
              v-for="prayer in prayers"
              :key="`${day.key}-${prayer.key}`"
              class="owner-planner-prayer-block"
            >
              <div class="owner-planner-lines">
                <div
                  v-for="(cell, cellIndex) in content.days[day.key][prayer.beforeKey]"
                  :key="`${day.key}-${prayer.beforeKey}-${cellIndex}`"
                  class="owner-planner-line-row"
                >
                  <button
                    v-if="!isCellEmpty(cell)"
                    type="button"
                    class="owner-planner-tone"
                    :class="cell.color ? `owner-planner-tone--${cell.color}` : ''"
                    :title="$t('ownerPlannerCycleColor')"
                    @click="cycleCellColor(cell)"
                  />
                  <textarea
                    v-model="cell.text"
                    class="owner-planner-line"
                    :class="lineToneClass(cell)"
                    rows="1"
                    @input="onCellInput(cell, $event)"
                  />
                  <button
                    v-if="content.days[day.key][prayer.beforeKey].length > 1"
                    type="button"
                    class="owner-planner-line-remove"
                    tabindex="-1"
                    @click="removeCell(day.key, prayer.beforeKey, cellIndex)"
                  ><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
              </div>

              <div class="owner-planner-mark">
                <span>{{ $t(prayer.labelKey) }}</span>
                <button
                  type="button"
                  class="owner-planner-plus"
                  :aria-expanded="addMenuKey === menuKey(day.key, prayer.key)"
                  :aria-label="$t('add')"
                  @click="toggleAddMenu(day.key, prayer.key)"
                >
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </button>
              </div>

              <div
                v-if="addMenuKey === menuKey(day.key, prayer.key)"
                class="owner-planner-add-picker"
              >
                <button type="button" @click="addCell(day.key, prayer.beforeKey)">
                  {{ $t('ownerPlannerAddBeforeShort') }}
                </button>
                <button type="button" @click="addCell(day.key, prayer.afterKey)">
                  {{ $t('ownerPlannerAddAfterShort') }}
                </button>
              </div>

              <div class="owner-planner-lines">
                <div
                  v-for="(cell, cellIndex) in content.days[day.key][prayer.afterKey]"
                  :key="`${day.key}-${prayer.afterKey}-${cellIndex}`"
                  class="owner-planner-line-row"
                >
                  <button
                    v-if="!isCellEmpty(cell)"
                    type="button"
                    class="owner-planner-tone"
                    :class="cell.color ? `owner-planner-tone--${cell.color}` : ''"
                    :title="$t('ownerPlannerCycleColor')"
                    @click="cycleCellColor(cell)"
                  />
                  <textarea
                    v-model="cell.text"
                    class="owner-planner-line"
                    :class="lineToneClass(cell)"
                    rows="1"
                    @input="onCellInput(cell, $event)"
                  />
                  <button
                    v-if="content.days[day.key][prayer.afterKey].length > 1"
                    type="button"
                    class="owner-planner-line-remove"
                    tabindex="-1"
                    @click="removeCell(day.key, prayer.afterKey, cellIndex)"
                  ><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
              </div>
            </section>
          </div>
        </article>
      </div>
    </template>
  </div>
</template>

<script>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import {
  PLANNER_DAYS,
  PLANNER_PRAYERS,
  PLANNER_TONES,
  emptyCell,
  emptyPlannerContent,
  isCellEmpty,
  lineToneClass,
  mergePlannerContent,
  nextTone,
} from '../../config/ownerPlanner';

export default {
  setup() {
    const loading = ref(true);
    const saving = ref(false);
    const savedAt = ref('');
    const activeDay = ref('mon');
    const addMenuKey = ref('');
    const days = PLANNER_DAYS;
    const prayers = PLANNER_PRAYERS;
    const content = reactive(emptyPlannerContent());
    let saveTimer = null;
    let toneCounter = 0;

    function menuKey(dayKey, prayerKey) {
      return `${dayKey}-${prayerKey}`;
    }

    function resizeLine(event) {
      const el = event.target;
      el.style.height = 'auto';
      el.style.height = `${el.scrollHeight}px`;
    }

    function resizeAllLines() {
      document.querySelectorAll('.owner-planner-weekly .owner-planner-line').forEach((el) => {
        el.style.height = 'auto';
        el.style.height = `${el.scrollHeight}px`;
      });
    }

    function pickAutoTone() {
      const tone = PLANNER_TONES[toneCounter % PLANNER_TONES.length];
      toneCounter += 1;
      return tone === 'red' ? PLANNER_TONES[(toneCounter++) % PLANNER_TONES.length] : tone;
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/planner');
        mergePlannerContent(content, data.content || {});
        savedAt.value = data.updated_at || '';
        requestAnimationFrame(resizeAllLines);
      } finally {
        loading.value = false;
      }
    }

    async function saveNow() {
      if (saving.value) return;
      saving.value = true;
      try {
        const { data } = await window.axios.put('/owner/planner', { content });
        mergePlannerContent(content, data.content || {});
        savedAt.value = data.updated_at || new Date().toISOString();
      } finally {
        saving.value = false;
      }
    }

    function queueSave() {
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(saveNow, 800);
    }

    function onCellInput(cell, event) {
      if (isCellEmpty(cell)) {
        cell.color = null;
      } else if (!cell.color) {
        cell.color = pickAutoTone();
      }
      resizeLine(event);
      queueSave();
    }

    function cycleCellColor(cell) {
      if (isCellEmpty(cell)) return;
      cell.color = nextTone(cell.color);
      queueSave();
    }

    function toggleAddMenu(dayKey, prayerKey) {
      const key = menuKey(dayKey, prayerKey);
      addMenuKey.value = addMenuKey.value === key ? '' : key;
    }

    function addCell(dayKey, slotKey) {
      content.days[dayKey][slotKey].push(emptyCell());
      addMenuKey.value = '';
      queueSave();
      requestAnimationFrame(resizeAllLines);
    }

    function removeCell(dayKey, slotKey, cellIndex) {
      const cells = content.days[dayKey][slotKey];
      if (cells.length <= 1) return;
      cells.splice(cellIndex, 1);
      queueSave();
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
      if (saveTimer) clearTimeout(saveTimer);
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeydown);
    });

    return {
      loading,
      saving,
      savedAt,
      activeDay,
      addMenuKey,
      days,
      prayers,
      content,
      menuKey,
      isCellEmpty,
      lineToneClass,
      onCellInput,
      cycleCellColor,
      toggleAddMenu,
      addCell,
      removeCell,
    };
  },
};
</script>
