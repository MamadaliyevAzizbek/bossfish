/** Oddiy bir martalik GPS o‘qish */
export function getCurrentPosition(options = {}) {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('unsupported'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy,
      }),
      (err) => reject(err),
      {
        enableHighAccuracy: true,
        timeout: 20000,
        maximumAge: 0,
        ...options,
      },
    );
  });
}

/**
 * Telegram uslubida aniqroq joylashuv (~10 m).
 * watchPosition orqali eng yaxshi accuracy topilguncha kutadi.
 */
export function getPrecisePosition({
  targetAccuracy = 10,
  timeoutMs = 22000,
} = {}) {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('unsupported'));
      return;
    }

    let watchId = null;
    let best = null;
    let settled = false;

    const done = (payload) => {
      if (settled) return;
      settled = true;
      if (watchId != null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }
      resolve(payload);
    };

    const fail = (err) => {
      if (settled) return;
      if (best) {
        done(best);
        return;
      }
      settled = true;
      if (watchId != null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }
      reject(err);
    };

    const options = {
      enableHighAccuracy: true,
      maximumAge: 0,
      timeout: timeoutMs,
    };

    // Tezroq birinchi nuqta
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const current = {
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
        };
        best = current;
        if (current.accuracy <= targetAccuracy) {
          done(current);
        }
      },
      () => {},
      options,
    );

    watchId = navigator.geolocation.watchPosition(
      (pos) => {
        const current = {
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
        };

        if (!best || current.accuracy <= best.accuracy) {
          best = current;
        }

        if (current.accuracy <= targetAccuracy) {
          done(current);
        }
      },
      fail,
      options,
    );

    setTimeout(() => {
      if (best) done(best);
      else fail(Object.assign(new Error('timeout'), { code: 3 }));
    }, timeoutMs);
  });
}
