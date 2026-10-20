/** Viloyat markazlari — Regions.php bilan mos */
export const regionCoordinates = {
  Andijon: { lat: 40.782, lng: 72.344 },
  Buxoro: { lat: 39.768, lng: 64.422 },
  "Farg'ona": { lat: 40.384, lng: 71.786 },
  Jizzax: { lat: 40.116, lng: 67.842 },
  Xorazm: { lat: 41.55, lng: 60.633 },
  Namangan: { lat: 40.998, lng: 71.673 },
  Navoiy: { lat: 40.103, lng: 65.379 },
  Qashqadaryo: { lat: 38.861, lng: 65.785 },
  "Qoraqalpog'iston": { lat: 42.46, lng: 59.6 },
  Samarqand: { lat: 39.654, lng: 66.96 },
  Sirdaryo: { lat: 40.85, lng: 68.665 },
  Surxondaryo: { lat: 37.94, lng: 67.57 },
  'Toshkent viloyati': { lat: 41.02, lng: 69.62 },
  'Toshkent shahri': { lat: 41.311, lng: 69.279 },
};

export const uzbekistanBounds = [
  [37.08, 56.05],
  [45.52, 73.1],
];

export const uzbekistanCenter = [41.3, 64.5];

export function uzbekistanLatLngBounds() {
  // Leaflet is loaded in map utils; bounds array is enough for contains checks elsewhere.
  return uzbekistanBounds;
}

export function isInsideUzbekistan(lat, lng) {
  const [[south, west], [north, east]] = uzbekistanBounds;
  return lat >= south && lat <= north && lng >= west && lng <= east;
}

export function clampToUzbekistan(lat, lng) {
  const [[south, west], [north, east]] = uzbekistanBounds;
  return {
    lat: Math.min(Math.max(lat, south), north),
    lng: Math.min(Math.max(lng, west), east),
  };
}

export function regionCenter(name) {
  return regionCoordinates[name] || null;
}

export function normalizeRegions(apiRegions) {
  if (!apiRegions?.length) return [];
  if (typeof apiRegions[0] === 'string') {
    return apiRegions.map((name) => ({ name, ...regionCenter(name) }));
  }
  return apiRegions;
}

export function regionName(region) {
  return typeof region === 'string' ? region : region?.name;
}
