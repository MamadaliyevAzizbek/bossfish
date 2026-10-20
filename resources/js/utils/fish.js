export const FISH_TYPES = ['sazan', 'tolstolob', 'oq_amur', 'targil_tolstolob'];

const FISH_LABEL_KEYS = {
  sazan: 'fishSazan',
  tolstolob: 'fishTolstolob',
  oq_amur: 'fishWhiteAmur',
  targil_tolstolob: 'fishBighead',
};

const FISH_IMAGE_PATHS = {
  sazan: '/images/fish/sazan.png',
  tolstolob: '/images/fish/tolstolob.png',
  oq_amur: '/images/fish/oq_amur.png',
  targil_tolstolob: '/images/fish/targil_tolstolob.png',
};

export function fishImageSrc(type) {
  return FISH_IMAGE_PATHS[type] || FISH_IMAGE_PATHS.sazan;
}

export function fishLabel(type, translate) {
  const key = FISH_LABEL_KEYS[type];
  return key && translate ? translate(key) : (type || '—');
}
