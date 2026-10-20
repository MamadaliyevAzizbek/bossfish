<template>
  <div class="owner-planner-yearly">
    <div v-if="loading" class="empty">{{ $t('loading') }}</div>

    <template v-else>
      <section class="owner-planner-long-term">
        <header class="owner-planner-long-term__head">
          <div>
            <h2>{{ $t('ownerPlannerLongTermTitle') }}</h2>
            <small>{{ $t('ownerPlannerLongTermHint') }}</small>
          </div>
        </header>

        <form class="owner-planner-inbox-add" @submit.prevent="addLongTerm">
          <input v-model.trim="longTermDraft" type="text" :placeholder="$t('ownerPlannerLongTermPlaceholder')" />
          <button class="btn btn-primary btn-sm" type="submit" :disabled="!longTermDraft || longTermSaving">
            {{ $t('add') }}
          </button>
        </form>

        <div v-if="!activeLongTerm.length" class="owner-planner-daily-empty">{{ $t('ownerPlannerLongTermEmpty') }}</div>

        <ul v-else class="owner-planner-inbox-list">
          <li v-for="task in activeLongTerm" :key="task.id">
            <span class="owner-planner-daily-dot" :class="task.color ? `owner-planner-tone--${task.color}` : ''" />
            <span class="owner-planner-inbox-list__text">{{ task.text }}</span>
            <button type="button" class="btn btn-outline btn-sm" @click="completeLongTerm(task)">
              {{ $t('ownerPlannerMarkDone') }}
            </button>
            <button type="button" class="owner-planner-line-remove" @click="removeLongTerm(task)"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
          </li>
        </ul>

        <div v-if="completedLongTerm.length" class="owner-planner-inbox-done">
          <h3>{{ $t('ownerPlannerCompleted') }}</h3>
          <ul>
            <li v-for="task in completedLongTerm" :key="`ltc-${task.id}`">
              <s>{{ task.text }}</s>
            </li>
          </ul>
        </div>
      </section>

      <div class="owner-planner-yearly-layout">
        <div class="owner-planner-cal-box">
          <div class="owner-planner-cal-nav">
            <button type="button" class="owner-planner-cal-nav__btn" @click="shiftMonth(-1)">‹</button>
            <strong>{{ monthTitle }}</strong>
            <button type="button" class="owner-planner-cal-nav__btn" @click="shiftMonth(1)">›</button>
          </div>

          <div class="owner-planner-cal-grid">
            <div v-for="wd in weekLabels" :key="wd" class="owner-planner-cal-wd">{{ wd }}</div>
            <button
              v-for="(cell, idx) in calendarCells"
              :key="`${viewYear}-${viewMonth}-${idx}`"
              type="button"
              class="owner-planner-cal-day"
              :class="{
                'owner-planner-cal-day--muted': !cell.inMonth,
                'owner-planner-cal-day--selected': cell.date === selectedDate,
                'owner-planner-cal-day--today': cell.date === todayIso,
                'owner-planner-cal-day--has': !!taskCounts[cell.date],
              }"
              @click="selectDate(cell.date)"
            >
              {{ cell.day }}
            </button>
          </div>
        </div>

        <div class="owner-planner-day-editor">
          <div class="owner-planner-day-editor__head">
            <div>
              <h3>{{ formatPlannerDate(selectedDate) }}</h3>
              <small>{{ $t('ownerPlannerYearlyWriteHint') }}</small>
            </div>
            <button type="button" class="owner-planner-plus" :aria-label="$t('add')" @click="addCell">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
          </div>

          <div class="owner-planner-lines">
            <div
              v-for="(cell, cellIndex) in selectedCells"
              :key="`${selectedDate}-${cellIndex}`"
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
                :placeholder="$t('ownerPlannerYearlyPlaceholder')"
                @input="onCellInput(cell, $event)"
              />
              <button
                v-if="selectedCells.length > 1"
                type="button"
                class="owner-planner-line-remove"
                tabindex="-1"
                @click="removeCell(cellIndex)"
              ><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
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
import { i18nState } from '../../i18n';
import {
  PLANNER_TONES,
  emptyCell,
  formatPlannerDate,
  isCellEmpty,
  lineToneClass,
  newLongTermId,
  nextTone,
  normalizeSlotCells,
  toIsoDate,
} from '../../config/ownerPlanner';

export default {
  setup() {
    const loading = ref(true);
    const saving = ref(false);
    const savedAt = ref('');
    const dirty = ref(false);
    const justSaved = ref(false);
    const now = new Date();
    const viewYear = ref(now.getFullYear());
    const viewMonth = ref(now.getMonth());
    const selectedDate = ref(toIsoDate(now));
    const todayIso = toIsoDate(now);
    const dates = reactive({});
    const longTerm = ref([]);
    const longTermDraft = ref('');
    const longTermSaving = ref(false);
    let toneCounter = 0;
    let savedHintTimer = null;

    const activeLongTerm = computed(() => longTerm.value.filter((t) => !t.completed_at));
    const completedLongTerm = computed(() => longTerm.value.filter((t) => t.completed_at));

    const weekLabels = computed(() => {
      if (i18nState.locale === 'ru') return ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
      return ['Du', 'Se', 'Cho', 'Pa', 'Ju', 'Sha', 'Ya'];
    });

    const monthTitle = computed(() => {
      const d = new Date(viewYear.value, viewMonth.value, 1);
      return d.toLocaleDateString(i18nState.locale === 'ru' ? 'ru-RU' : 'uz-UZ', {
        month: 'long',
        year: 'numeric',
      });
    });

    const calendarCells = computed(() => {
      const first = new Date(viewYear.value, viewMonth.value, 1);
      const startPad = (first.getDay() + 6) % 7;
      const start = new Date(viewYear.value, viewMonth.value, 1 - startPad);
      const cells = [];
      for (let i = 0; i < 42; i += 1) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        cells.push({
          date: toIsoDate(d),
          day: d.getDate(),
          inMonth: d.getMonth() === viewMonth.value,
        });
      }
      return cells;
    });

    const taskCounts = computed(() => {
      const counts = {};
      Object.entries(dates).forEach(([date, cells]) => {
        const n = (cells || []).filter((c) => c?.text?.trim()).length;
        if (n) counts[date] = n;
      });
      return counts;
    });

    const selectedCells = computed({
      get() {
        if (!dates[selectedDate.value]) {
          dates[selectedDate.value] = [emptyCell()];
        }
        return dates[selectedDate.value];
      },
      set(value) {
        dates[selectedDate.value] = value;
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
      Object.keys(dates).forEach((k) => delete dates[k]);
      Object.entries(payload?.dates || {}).forEach(([date, cells]) => {
        dates[date] = normalizeSlotCells(cells);
      });
      if (!dates[selectedDate.value]) {
        dates[selectedDate.value] = [emptyCell()];
      }
      longTerm.value = Array.isArray(payload?.long_term) ? payload.long_term : [];
    }

    async function load() {
      loading.value = true;
      try {
        const { data } = await window.axios.get('/owner/planner/yearly');
        applyContent(data.content || {});
        savedAt.value = data.updated_at || '';
        dirty.value = false;
      } finally {
        loading.value = false;
      }
    }

    async function saveNow() {
      if (saving.value || !selectedDate.value || !dirty.value) return;
      saving.value = true;
      try {
        const { data } = await window.axios.put('/owner/planner/yearly', {
          date: selectedDate.value,
          cells: dates[selectedDate.value] || [emptyCell()],
          long_term: longTerm.value,
        });
        applyContent(data.content || {});
        savedAt.value = data.updated_at || new Date().toISOString();
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

    async function saveLongTerm() {
      longTermSaving.value = true;
      try {
        const { data } = await window.axios.put('/owner/planner/yearly', {
          long_term: longTerm.value,
        });
        applyContent(data.content || {});
        savedAt.value = data.updated_at || new Date().toISOString();
      } finally {
        longTermSaving.value = false;
      }
    }

    async function addLongTerm() {
      if (!longTermDraft.value) return;
      longTerm.value = [
        {
          id: newLongTermId(),
          text: longTermDraft.value,
          color: pickAutoTone(),
          completed_at: null,
        },
        ...longTerm.value,
      ];
      longTermDraft.value = '';
      await saveLongTerm();
    }

    async function completeLongTerm(task) {
      longTerm.value = longTerm.value.map((item) => (
        item.id === task.id
          ? { ...item, completed_at: new Date().toISOString() }
          : item
      ));
      await saveLongTerm();
    }

    async function removeLongTerm(task) {
      longTerm.value = longTerm.value.filter((item) => item.id !== task.id);
      await saveLongTerm();
    }

    function selectDate(date) {
      selectedDate.value = date;
      if (!dates[date]) dates[date] = [emptyCell()];
      dirty.value = false;
      justSaved.value = false;
    }

    function shiftMonth(delta) {
      const d = new Date(viewYear.value, viewMonth.value + delta, 1);
      viewYear.value = d.getFullYear();
      viewMonth.value = d.getMonth();
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
      savedAt,
      dirty,
      justSaved,
      selectedDate,
      todayIso,
      weekLabels,
      monthTitle,
      calendarCells,
      taskCounts,
      selectedCells,
      longTermDraft,
      longTermSaving,
      activeLongTerm,
      completedLongTerm,
      isCellEmpty,
      lineToneClass,
      formatPlannerDate,
      selectDate,
      shiftMonth,
      onCellInput,
      cycleCellColor,
      addCell,
      removeCell,
      saveNow,
      addLongTerm,
      completeLongTerm,
      removeLongTerm,
    };
  },
};
</script>
