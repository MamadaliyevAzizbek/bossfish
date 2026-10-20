<template>
  <div class="owner-planner-monthly">
    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <p class="owner-planner-monthly-hint">{{ $t('ownerPlannerMonthlyHint') }}</p>

      <div class="owner-planner-yearly-layout owner-planner-monthly-layout">
        <div class="owner-planner-cal-box">
          <div class="owner-planner-cal-nav">
            <strong>{{ $t('ownerPlannerMonthlyPickDay') }}</strong>
          </div>

          <div class="owner-planner-month-day-grid">
            <button
              v-for="day in dayNumbers"
              :key="day"
              type="button"
              class="owner-planner-cal-day"
              :class="{
                'owner-planner-cal-day--selected': day === selectedDay,
                'owner-planner-cal-day--has': !!taskCounts[day],
                'owner-planner-cal-day--today': day === todayDay,
              }"
              @click="selectDay(day)"
            >
              {{ day }}
            </button>
          </div>
        </div>

        <div class="owner-planner-day-editor">
          <div class="owner-planner-day-editor__head">
            <div>
              <h3>{{ $t('ownerPlannerMonthlyDayTitle', { day: selectedDay }) }}</h3>
              <small>{{ $t('ownerPlannerMonthlyWriteHint') }}</small>
            </div>
            <button type="button" class="owner-planner-plus" :aria-label="$t('add')" @click="addCell">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
          </div>

          <div class="owner-planner-lines">
            <div
              v-for="(cell, cellIndex) in selectedCells"
              :key="`${selectedDay}-${cellIndex}`"
              class="owner-planner-line-row"
            >
              <button
                v-if="!isCellEmpty(cell)"
                type="button"
                class="owner-planner-tone"
                :class="cell.color ? `owner-planner-tone--${cell.color}` : ''"
                @click="cycleCellColor(cell)"
              />
              <textarea
                v-model="cell.text"
                class="owner-planner-line"
                :class="lineToneClass(cell)"
                rows="1"
                :placeholder="$t('ownerPlannerMonthlyPlaceholder')"
                @input="onCellInput(cell, $event)"
              />
              <button
                v-if="selectedCells.length > 1"
                type="button"
                class="owner-planner-line-remove"
                tabindex="-1"
                @click="removeCell(cellIndex)"
              >
                ×
              </button>
            </div>
          </div>

          <div class="owner-planner-day-editor__actions">
            <span v-if="dirty" class="owner-planner-day-editor__dirty">{{ $t('ownerPlannerUnsaved') }}</span>
            <span v-else-if="justSaved" class="owner-planner-day-editor__saved">{{ $t('ownerPlannerSaved') }}</span>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="saving || !dirty"
              @click="saveNow"
            >
              {{ saving ? $t('saving') : $t('save') }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, onMounted, reactive, ref } from 'vue';
import {
  PLANNER_TONES,
  emptyCell,
  isCellEmpty,
  lineToneClass,
  nextTone,
  normalizeSlotCells,
} from '../../config/ownerPlanner';

export default {
  setup() {
    const loading = ref(true);
    const saving = ref(false);
    const dirty = ref(false);
    const justSaved = ref(false);
    const now = new Date();
    const todayDay = now.getDate();
    const selectedDay = ref(todayDay);
    const days = reactive({});
    const dayNumbers = Array.from({ length: 31 }, (_, i) => i + 1);
    let toneCounter = 0;
    let savedHintTimer = null;

    const taskCounts = computed(() => {
      const counts = {};
      Object.entries(days).forEach(([day, cells]) => {
        const n = (cells || []).filter((c) => c?.text?.trim()).length;
        if (n) counts[Number(day)] = n;
      });
      return counts;
    });

    const selectedCells = computed({
      get() {
        const key = String(selectedDay.value);
        if (!days[key]) {
          days[key] = [emptyCell()];
        }
        return days[key];
      },
      set(value) {
        days[String(selectedDay.value)] = value;
      },
    });

    function pickAutoTone() {
      const tone = PLANNER_TONES[toneCounter % PLANNER_TONES.length];
      toneCounter += 1;
      return tone === 'red' ? PLANNER_TONES[(toneCounter++) % PLANNER_TONES.length] : tone;
    }

    function resizeLine(event) {
      const el = event.target;
      el.style.height = 'auto';
      el.style.height = `${el.scrollHeight}px`;
    }

    function markDirty() {
      dirty.value = true;
      justSaved.value = false;
    }

    function applyContent(payload) {
      Object.keys(days).forEach((k) => delete days[k]);
      Object.entries(payload?.days || {}).forEach(([day, cells]) => {
        days[String(Number(day))] = normalizeSlotCells(cells);
      });
      const key = String(selectedDay.value);
      if (!days[key]) {
        days[key] = [emptyCell()];
      }
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/planner/monthly');
        applyContent(data.content || {});
        dirty.value = false;
      } finally {
        loading.value = false;
      }
    }

    async function saveNow() {
      if (saving.value || !dirty.value) return;
      saving.value = true;
      try {
        const { data } = await window.axios.put('/owner/planner/monthly', {
          day: selectedDay.value,
          cells: days[String(selectedDay.value)] || [emptyCell()],
        });
        applyContent(data.content || {});
        dirty.value = false;
        justSaved.value = true;
        if (savedHintTimer) clearTimeout(savedHintTimer);
        savedHintTimer = setTimeout(() => {
          justSaved.value = false;
        }, 2500);
      } finally {
        saving.value = false;
      }
    }

    function selectDay(day) {
      selectedDay.value = day;
      const key = String(day);
      if (!days[key]) days[key] = [emptyCell()];
      dirty.value = false;
      justSaved.value = false;
    }

    function onCellInput(cell, event) {
      if (isCellEmpty(cell)) cell.color = null;
      else if (!cell.color) cell.color = pickAutoTone();
      resizeLine(event);
      markDirty();
    }

    function cycleCellColor(cell) {
      if (isCellEmpty(cell)) return;
      cell.color = nextTone(cell.color);
      markDirty();
    }

    function addCell() {
      selectedCells.value.push(emptyCell());
      markDirty();
    }

    function removeCell(index) {
      if (selectedCells.value.length <= 1) return;
      selectedCells.value.splice(index, 1);
      markDirty();
    }

    onMounted(load);

    return {
      loading,
      saving,
      dirty,
      justSaved,
      selectedDay,
      todayDay,
      dayNumbers,
      taskCounts,
      selectedCells,
      isCellEmpty,
      lineToneClass,
      selectDay,
      onCellInput,
      cycleCellColor,
      addCell,
      removeCell,
      saveNow,
    };
  },
};
</script>
