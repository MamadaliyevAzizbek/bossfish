<template>
  <div class="owner-planner-page">
    <div class="page-head owner-planner-head">
      <div>
        <h1>{{ $t('ownerPlannerTitle') }}</h1>
        <p>{{ $t('ownerPlannerHubHint') }}</p>
      </div>
    </div>

    <nav class="owner-planner-hub-tabs panel" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="owner-planner-hub-tab"
        :class="{ 'owner-planner-hub-tab--active': section === tab.key }"
        role="tab"
        :aria-selected="section === tab.key"
        @click="section = tab.key"
      >
        {{ $t(tab.labelKey) }}
      </button>
    </nav>

    <OwnerPlannerDaily v-if="section === 'daily'" />
    <OwnerPlannerWeekly v-else-if="section === 'weekly'" />
    <OwnerPlannerMonthly v-else-if="section === 'monthly'" />
    <OwnerPlannerYearly v-else />
  </div>
</template>

<script>
import { ref } from 'vue';
import OwnerPlannerDaily from './OwnerPlannerDaily.vue';
import OwnerPlannerWeekly from './OwnerPlannerWeekly.vue';
import OwnerPlannerMonthly from './OwnerPlannerMonthly.vue';
import OwnerPlannerYearly from './OwnerPlannerYearly.vue';

export default {
  components: { OwnerPlannerDaily, OwnerPlannerWeekly, OwnerPlannerMonthly, OwnerPlannerYearly },
  setup() {
    const section = ref('daily');
    const tabs = [
      { key: 'daily', labelKey: 'ownerPlannerTabDaily' },
      { key: 'weekly', labelKey: 'ownerPlannerTabWeekly' },
      { key: 'monthly', labelKey: 'ownerPlannerTabMonthly' },
      { key: 'yearly', labelKey: 'ownerPlannerTabYearly' },
    ];

    return { section, tabs };
  },
};
</script>
