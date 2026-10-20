import { reactive } from 'vue';

const STORAGE = {
    token: 'token',
    expiresAt: 'auth_expires_at',
    role: 'auth_role',
};

const state = reactive({
    user: null,
    token: localStorage.getItem(STORAGE.token),
});

function isSessionExpired() {
    const expiresAt = localStorage.getItem(STORAGE.expiresAt);
    if (!expiresAt) {
        return false;
    }

    return Date.now() >= new Date(expiresAt).getTime();
}

function persistSessionMeta(role, expiresAt) {
    if (role) {
        localStorage.setItem(STORAGE.role, role);
    } else {
        localStorage.removeItem(STORAGE.role);
    }

    if (expiresAt) {
        localStorage.setItem(STORAGE.expiresAt, expiresAt);
    } else {
        localStorage.removeItem(STORAGE.expiresAt);
    }
}

function applyToken(token) {
    state.token = token;
    if (token) {
        localStorage.setItem(STORAGE.token, token);
        window.axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
        localStorage.removeItem(STORAGE.token);
        delete window.axios.defaults.headers.common.Authorization;
    }
}

export default {
    state,

    setAuth(user, token, expiresAt = null) {
        state.user = user;
        applyToken(token);
        persistSessionMeta(user?.role || null, expiresAt);
    },

    clearAuth() {
        state.user = null;
        applyToken(null);
        persistSessionMeta(null, null);
    },

    hasValidSession() {
        return !!state.token && !isSessionExpired();
    },

    savedRole() {
        return localStorage.getItem(STORAGE.role);
    },

    async fetchUser() {
        if (!this.hasValidSession()) {
            this.clearAuth();
            return null;
        }

        try {
            const { data } = await window.axios.get('/auth/me');
            state.user = data;
            if (!localStorage.getItem(STORAGE.role) && data?.role) {
                localStorage.setItem(STORAGE.role, data.role);
            }
            return data;
        } catch (e) {
            // Token yaroqsiz yoki server xatosi — sessiyani tozalab login sahifasiga yo‘l ochiladi.
            // Aks holda hasValidSession=true + user=null holatida kabinetlar uyga qaytarardi.
            this.clearAuth();
            return null;
        }
    },

    async initAuth() {
        if (isSessionExpired()) {
            this.clearAuth();
            return null;
        }

        const token = localStorage.getItem(STORAGE.token);
        if (!token) {
            this.clearAuth();
            return null;
        }

        applyToken(token);
        return this.fetchUser();
    },

    isOwner() {
        return state.user?.role === 'owner';
    },

    isDirector() {
        return state.user?.role === 'director';
    },

    isWorker() {
        return state.user?.role === 'worker';
    },

    isVenger() {
        return state.user?.role === 'venger';
    },

    isAuthenticated() {
        return this.hasValidSession() && !!state.user;
    },

    homeForRole(role = state.user?.role) {
        if (role === 'owner') return { name: 'owner-cabinet' };
        if (role === 'director') return { name: 'director-home' };
        if (role === 'worker') return { name: 'worker-home' };
        if (role === 'venger') return { name: 'venger-cabinet' };
        return { name: 'home' };
    },

    loginRouteForRole(role = state.user?.role) {
        if (role === 'owner') return { name: 'owner-login' };
        if (role === 'director') return { name: 'director-login' };
        if (role === 'worker') return { name: 'worker-login' };
        if (role === 'venger') return { name: 'venger-login' };
        return { name: 'home' };
    },

    portalRouteForRole(role) {
        if (this.isAuthenticated() && state.user?.role === role) {
            return this.homeForRole(role);
        }

        return this.loginRouteForRole(role);
    },
};
