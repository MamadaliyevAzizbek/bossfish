/** Har bir viloyat uchun alohida rang — xaritada viloyat kesimida ko‘rinadi */
export const regionColors = {
  Andijon: '#ef5350',
  Buxoro: '#ab47bc',
  "Farg'ona": '#7e57c2',
  Jizzax: '#5c6bc0',
  Xorazm: '#42a5f5',
  Namangan: '#29b6f6',
  Navoiy: '#26c6da',
  Qashqadaryo: '#00897b',
  "Qoraqalpog'iston": '#2e7d32',
  Samarqand: '#9ccc65',
  Sirdaryo: '#66bb6a',
  Surxondaryo: '#ffca28',
  'Toshkent viloyati': '#ffa726',
  'Toshkent shahri': '#ff7043',
};

export function regionColor(name) {
  return regionColors[name] || '#1565c0';
}
