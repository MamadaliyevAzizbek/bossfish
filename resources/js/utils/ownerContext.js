const STORAGE_ID = 'owner_acting_director_id';
const STORAGE_NAME = 'owner_acting_director_name';
const STORAGE_PHONE = 'owner_acting_director_phone';

export function setActingDirector(director) {
  if (!director?.id) {
    clearActingDirector();
    return;
  }
  sessionStorage.setItem(STORAGE_ID, String(director.id));
  sessionStorage.setItem(STORAGE_NAME, director.name || '');
  sessionStorage.setItem(STORAGE_PHONE, director.phone || '');
  window.axios.defaults.headers.common['X-Acting-Director-Id'] = String(director.id);
}

/** @deprecated use setActingDirector */
export function setActingDirectorId(id) {
  if (id) {
    sessionStorage.setItem(STORAGE_ID, String(id));
    window.axios.defaults.headers.common['X-Acting-Director-Id'] = String(id);
  } else {
    clearActingDirector();
  }
}

export function getActingDirectorId() {
  return sessionStorage.getItem(STORAGE_ID);
}

export function getActingDirector() {
  const id = getActingDirectorId();
  if (!id) return null;
  return {
    id,
    name: sessionStorage.getItem(STORAGE_NAME) || '',
    phone: sessionStorage.getItem(STORAGE_PHONE) || '',
  };
}

export function clearActingDirector() {
  sessionStorage.removeItem(STORAGE_ID);
  sessionStorage.removeItem(STORAGE_NAME);
  sessionStorage.removeItem(STORAGE_PHONE);
  delete window.axios.defaults.headers.common['X-Acting-Director-Id'];
}

export function restoreActingDirectorHeader() {
  const id = getActingDirectorId();
  if (id) {
    window.axios.defaults.headers.common['X-Acting-Director-Id'] = id;
  }
}

restoreActingDirectorHeader();
