<template>
  <div class="guide-schedule-block">
    <h3 v-if="showTitle" class="guide-schedule-title">{{ $t('guideScheduleTitle') }}</h3>
    <p v-if="showIntro" class="guide-schedule-intro">{{ $t('guideScheduleIntro') }}</p>
    <div class="guide-schedule-scroll">
      <table class="guide-schedule-table">
        <thead>
          <tr>
            <th>{{ $t('guideSchedColType') }}</th>
            <th>{{ $t('guideSchedColWhen') }}</th>
            <th>{{ $t('guideSchedColWho') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in GUIDE_SCHEDULE_ROWS" :key="row.id">
            <td><strong>{{ $t(row.typeKey) }}</strong></td>
            <td>{{ $t(row.whenKey) }}</td>
            <td>{{ $t(row.whoKey) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { GUIDE_SCHEDULE_ROWS } from '../config/siteGuide';

export default {
  props: {
    showTitle: { type: Boolean, default: true },
    showIntro: { type: Boolean, default: true },
  },
  setup() {
    return { GUIDE_SCHEDULE_ROWS };
  },
};
</script>
