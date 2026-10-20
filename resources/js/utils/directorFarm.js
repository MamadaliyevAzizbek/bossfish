import { computed, ref } from 'vue';

const STORAGE_KEY = 'director_farm_id';
const farmIdRef = ref(typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null);

export function getDirectorFarmId() {
  return farmIdRef.value;
}

export function setDirectorFarmId(id) {
  const next = id == null || id === '' ? null : String(id);
  try {
    if (next) localStorage.setItem(STORAGE_KEY, next);
    else localStorage.removeItem(STORAGE_KEY);
  } catch (e) {
    /* ignore */
  }
  farmIdRef.value = next;
  return next;
}

export function useDirectorFarmId() {
  return computed(() => farmIdRef.value);
}

export function rememberFarmFromRoute(to) {
  if (to?.params?.farmId) {
    setDirectorFarmId(to.params.farmId);
  }
}
