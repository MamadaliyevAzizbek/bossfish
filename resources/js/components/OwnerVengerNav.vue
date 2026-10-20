<template>
  <nav class="ovn venger-readable">
    <router-link
      v-for="item in items"
      :key="item.name"
      class="ovn__link"
      :to="{ name: item.name }"
      :class="{ on: isActive(item) }"
    >
      {{ $t(item.labelKey) }}
    </router-link>
  </nav>
</template>

<script>
import { useRoute } from 'vue-router';

const ITEMS = [
  { name: 'owner-venger', labelKey: 'ownerVengerOverview', match: ['owner-venger'] },
  { name: 'owner-venger-clients', labelKey: 'vengerClientsTitle', match: ['owner-venger-clients', 'owner-venger-client'] },
  { name: 'owner-venger-ponds', labelKey: 'vengerPondsTitle', match: ['owner-venger-ponds'] },
  { name: 'owner-venger-results', labelKey: 'vengerResultsHubTitle', match: ['owner-venger-results', 'owner-venger-result'] },
  { name: 'owner-venger-staff', labelKey: 'vengerStaff', match: ['owner-venger-staff'] },
];

export default {
  setup() {
    const route = useRoute();
    function isActive(item) {
      return item.match.includes(route.name);
    }
    return { items: ITEMS, isActive };
  },
};
</script>

<style scoped>
.ovn {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 1rem;
  padding: 0.45rem;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid rgba(10, 80, 50, 0.12);
}
.ovn__link {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0.35rem 0.75rem;
  border-radius: 10px;
  font-size: 0.86rem;
  font-weight: 750;
  color: #2a4434;
  text-decoration: none;
  border: 1px solid transparent;
}
.ovn__link:hover { background: #eef6f1; }
.ovn__link.on {
  background: #0d6b3f;
  color: #fff;
  border-color: #0d6b3f;
}
</style>
