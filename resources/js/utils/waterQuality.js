/** KPR 3-in-1 + soat bandlari — backend WaterQualityStandards bilan mos. */

export const LEVEL_OK = 'ok';
export const LEVEL_CAUTION = 'caution';
export const LEVEL_DANGER = 'danger';

/** API kelmaguncha shkala ko‘rinishi uchun */
export const DEFAULT_WATER_STANDARDS = {
  kit: {
    name: 'KPR 3-in-1',
    ph: { min: 6.5, max: 9.5 },
    ammonia_mg_l: { min: 0.2, max: 15.0 },
    nitrite_mg_l: { min: 0.005, max: 0.3 },
  },
  metrics: {
    do_mg_l: {
      unit: 'mg/l',
      scale_min: 0,
      scale_max: 12,
      bands: {
        morning: { ok_min: 4.0, caution_min: 2.5, danger_below: 2.5 },
        day: { ok_min: 5.0, caution_min: 3.0, danger_below: 3.0 },
        evening: { ok_min: 5.0, caution_min: 3.5, danger_below: 3.5 },
        night: { ok_min: 4.0, caution_min: 2.5, danger_below: 2.5 },
        unknown: { ok_min: 4.0, caution_min: 3.0, danger_below: 3.0 },
      },
    },
    ph_level: {
      unit: '',
      scale_min: 6.5,
      scale_max: 9.5,
      bands: {
        morning: { ok: [6.5, 8.0], caution: [8.0, 8.6], danger_above: 8.6, danger_below: 6.5 },
        day: { ok: [6.8, 8.5], caution: [8.5, 9.0], danger_above: 9.0, danger_below: 6.5 },
        evening: { ok: [7.0, 8.8], caution: [8.8, 9.2], danger_above: 9.2, danger_below: 6.5 },
        night: { ok: [6.5, 8.5], caution: [8.5, 9.0], danger_above: 9.0, danger_below: 6.5 },
        unknown: { ok: [6.5, 8.5], caution: [8.5, 9.0], danger_above: 9.0, danger_below: 6.5 },
      },
    },
    ammonia_mg_l: {
      unit: 'mg/l',
      scale_min: 0,
      scale_max: 3,
      bands: {
        morning: { ok_max: 0.5, caution_max: 1.0 },
        day: { ok_max: 0.5, caution_max: 1.0 },
        evening: { ok_max: 0.3, caution_max: 0.7 },
        night: { ok_max: 0.5, caution_max: 1.0 },
        unknown: { ok_max: 0.5, caution_max: 1.0 },
      },
    },
    nitrite_mg_l: {
      unit: 'mg/l',
      scale_min: 0,
      scale_max: 0.3,
      bands: {
        morning: { ok_max: 0.05, caution_max: 0.15 },
        day: { ok_max: 0.05, caution_max: 0.15 },
        evening: { ok_max: 0.05, caution_max: 0.15 },
        night: { ok_max: 0.05, caution_max: 0.15 },
        unknown: { ok_max: 0.05, caution_max: 0.15 },
      },
    },
    water_transparency_cm: {
      unit: 'cm',
      scale_min: 0,
      scale_max: 80,
      bands: {
        morning: { ok: [25, 45], caution_low: [15, 25], caution_high: [45, 60] },
        day: { ok: [25, 45], caution_low: [15, 25], caution_high: [45, 60] },
        evening: { ok: [25, 45], caution_low: [15, 25], caution_high: [45, 60] },
        night: { ok: [25, 45], caution_low: [15, 25], caution_high: [45, 60] },
        unknown: { ok: [25, 45], caution_low: [15, 25], caution_high: [45, 60] },
      },
    },
  },
};

export function timeBandKey(testTime) {
  if (!testTime) return 'unknown';
  const m = String(testTime).match(/^(\d{1,2}):(\d{2})/);
  if (!m) return 'unknown';
  const h = Number(m[1]);
  const min = Number(m[2]);
  if (h > 23 || min > 59) return 'unknown';
  const minutes = h * 60 + min;
  if (minutes >= 5 * 60 && minutes < 10 * 60) return 'morning';
  if (minutes >= 10 * 60 && minutes < 16 * 60) return 'day';
  if (minutes >= 16 * 60 && minutes < 21 * 60) return 'evening';
  return 'night';
}

function num(v) {
  if (v === null || v === undefined || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function worse(a, b) {
  const rank = { [LEVEL_OK]: 0, [LEVEL_CAUTION]: 1, [LEVEL_DANGER]: 2 };
  return (rank[a] || 0) >= (rank[b] || 0) ? a : b;
}

function bandCfg(metricsCfg, metric, band) {
  return metricsCfg?.[metric]?.bands?.[band] || metricsCfg?.[metric]?.bands?.unknown || null;
}

function actionKey(metric, level, ctx) {
  if (metric === 'test_time') return 'waterActionMissingTime';

  if (metric === 'do_mg_l') {
    if (level === LEVEL_DANGER) {
      return ctx.band === 'morning' ? 'waterActionDoDangerMorning' : 'waterActionDoDanger';
    }
    return ctx.band === 'morning' ? 'waterActionDoCautionMorning' : 'waterActionDoCaution';
  }

  if (metric === 'ph_level') {
    const high = ctx.value > (ctx.ok_to ?? 8);
    if (level === LEVEL_DANGER) return high ? 'waterActionPhHighDanger' : 'waterActionPhLowDanger';
    return high ? 'waterActionPhHighCaution' : 'waterActionPhLowCaution';
  }

  if (metric === 'ammonia_mg_l') {
    if (ctx.high_ph) {
      return level === LEVEL_DANGER ? 'waterActionAmmoniaHighPhDanger' : 'waterActionAmmoniaHighPhCaution';
    }
    if (ctx.band === 'evening') {
      return level === LEVEL_DANGER ? 'waterActionAmmoniaEveningDanger' : 'waterActionAmmoniaEveningCaution';
    }
    return level === LEVEL_DANGER ? 'waterActionAmmoniaDanger' : 'waterActionAmmoniaCaution';
  }

  if (metric === 'nitrite_mg_l') {
    return level === LEVEL_DANGER ? 'waterActionNitriteDanger' : 'waterActionNitriteCaution';
  }

  if (metric === 'water_transparency_cm') {
    const low = ctx.value < (ctx.ok_from ?? 25);
    if (low) {
      return level === LEVEL_DANGER ? 'waterActionTransparencyLowDanger' : 'waterActionTransparencyLowCaution';
    }
    return level === LEVEL_DANGER ? 'waterActionTransparencyHighDanger' : 'waterActionTransparencyHighCaution';
  }

  return 'waterActionGeneric';
}

function issuePayload(metric, level, value, messageKey, params, extra = {}) {
  const action_key = actionKey(metric, level, { ...params, ...extra, value });
  return {
    metric,
    level,
    value,
    message_key: messageKey,
    message_params: params,
    action_key,
  };
}

function evalDo(value, band, cfg) {
  const b = cfg;
  if (!b) return { level: LEVEL_OK };
  const params = { value, min: b.ok_min, band };
  if (value < b.danger_below) {
    return issuePayload('do_mg_l', LEVEL_DANGER, value, 'waterAlertDoDanger', params);
  }
  if (value < b.ok_min) {
    return issuePayload('do_mg_l', LEVEL_CAUTION, value, 'waterAlertDoCaution', params);
  }
  return { level: LEVEL_OK, value };
}

function evalPh(value, band, cfg) {
  const b = cfg;
  if (!b) return { level: LEVEL_OK };
  const params = { value, ok_from: b.ok[0], ok_to: b.ok[1], band };
  if (value < b.danger_below || value > b.danger_above) {
    return issuePayload('ph_level', LEVEL_DANGER, value, 'waterAlertPhDanger', params);
  }
  if (value < b.ok[0] || value > b.ok[1]) {
    return issuePayload('ph_level', LEVEL_CAUTION, value, 'waterAlertPhCaution', params);
  }
  return { level: LEVEL_OK, value };
}

function evalAmmonia(value, band, cfg, ph) {
  const b = cfg;
  if (!b) return { level: LEVEL_OK };
  let okMax = b.ok_max;
  let cautionMax = b.caution_max;
  const highPh = ph != null && ph >= 8.5;
  if (highPh) {
    okMax = Math.min(okMax, 0.3);
    cautionMax = Math.min(cautionMax, 0.6);
  }
  const params = { value, max: okMax, band, high_ph: highPh };
  if (value > cautionMax) {
    return issuePayload('ammonia_mg_l', LEVEL_DANGER, value, 'waterAlertAmmoniaDanger', params, { high_ph: highPh, band });
  }
  if (value > okMax) {
    return issuePayload('ammonia_mg_l', LEVEL_CAUTION, value, 'waterAlertAmmoniaCaution', params, { high_ph: highPh, band });
  }
  return { level: LEVEL_OK, value };
}

function evalNitrite(value, band, cfg) {
  const b = cfg;
  if (!b) return { level: LEVEL_OK };
  const params = { value, max: b.ok_max, band };
  if (value > b.caution_max) {
    return issuePayload('nitrite_mg_l', LEVEL_DANGER, value, 'waterAlertNitriteDanger', params);
  }
  if (value > b.ok_max) {
    return issuePayload('nitrite_mg_l', LEVEL_CAUTION, value, 'waterAlertNitriteCaution', params);
  }
  return { level: LEVEL_OK, value };
}

function evalTransparency(value, band, cfg) {
  const b = cfg;
  if (!b) return { level: LEVEL_OK };
  const [okFrom, okTo] = b.ok;
  const params = { value, ok_from: okFrom, ok_to: okTo, band };
  if (value < 15 || value > 70) {
    const key = value < 15 ? 'waterAlertTransparencyLowDanger' : 'waterAlertTransparencyHighDanger';
    return issuePayload('water_transparency_cm', LEVEL_DANGER, value, key, params);
  }
  if (value < okFrom || value > okTo) {
    const key = value < okFrom ? 'waterAlertTransparencyLowCaution' : 'waterAlertTransparencyHighCaution';
    return issuePayload('water_transparency_cm', LEVEL_CAUTION, value, key, params);
  }
  return { level: LEVEL_OK, value };
}

/**
 * @param {object} readings
 * @param {object|null} standards clientConfig from API
 */
export function evaluateWaterQuality(readings, standards) {
  const metricsCfg = (standards || DEFAULT_WATER_STANDARDS)?.metrics || {};
  const band = timeBandKey(readings?.test_time);
  const levels = {};
  const metrics = {};
  let overall = LEVEL_OK;
  const issues = [];

  const doV = num(readings?.do_mg_l);
  const phV = num(readings?.ph_level);
  const amV = num(readings?.ammonia_mg_l);
  const niV = num(readings?.nitrite_mg_l);
  const trV = num(readings?.water_transparency_cm);

  const hasAny = [doV, phV, amV, niV, trV].some((v) => v !== null);
  if (hasAny && !readings?.test_time) {
    overall = worse(overall, LEVEL_CAUTION);
    issues.push(issuePayload('test_time', LEVEL_CAUTION, null, 'waterAlertMissingTime', {}));
  }

  const run = (key, value, result) => {
    levels[key] = result.level || LEVEL_OK;
    metrics[key] = { level: result.level || LEVEL_OK, value };
    overall = worse(overall, levels[key]);
    if (result.level && result.level !== LEVEL_OK) {
      issues.push({
        metric: result.metric,
        level: result.level,
        value: result.value,
        message_key: result.message_key,
        message_params: result.message_params || {},
        action_key: result.action_key,
      });
    }
  };

  if (doV !== null) run('do_mg_l', doV, evalDo(doV, band, bandCfg(metricsCfg, 'do_mg_l', band)));
  else metrics.do_mg_l = { level: LEVEL_OK, value: null };

  if (phV !== null) run('ph_level', phV, evalPh(phV, band, bandCfg(metricsCfg, 'ph_level', band)));
  else metrics.ph_level = { level: LEVEL_OK, value: null };

  if (amV !== null) run('ammonia_mg_l', amV, evalAmmonia(amV, band, bandCfg(metricsCfg, 'ammonia_mg_l', band), phV));
  else metrics.ammonia_mg_l = { level: LEVEL_OK, value: null };

  if (niV !== null) run('nitrite_mg_l', niV, evalNitrite(niV, band, bandCfg(metricsCfg, 'nitrite_mg_l', band)));
  else metrics.nitrite_mg_l = { level: LEVEL_OK, value: null };

  if (trV !== null) {
    run('water_transparency_cm', trV, evalTransparency(trV, band, bandCfg(metricsCfg, 'water_transparency_cm', band)));
  } else {
    metrics.water_transparency_cm = { level: LEVEL_OK, value: null };
  }

  const primary = pickPrimaryIssue(issues);

  return {
    level: overall,
    band,
    levels,
    metrics,
    issues,
    primary_action_key: primary?.action_key || (overall === LEVEL_OK ? 'waterActionOk' : 'waterActionGeneric'),
  };
}

function pickPrimaryIssue(issues) {
  if (!issues?.length) return null;
  const rank = { [LEVEL_DANGER]: 2, [LEVEL_CAUTION]: 1, [LEVEL_OK]: 0 };
  return [...issues].sort((a, b) => (rank[b.level] || 0) - (rank[a.level] || 0))[0];
}

export function metricMarkerPercent(value, scaleMin, scaleMax) {
  const v = num(value);
  if (v === null) return null;
  const min = Number(scaleMin) || 0;
  const max = Number(scaleMax) || 1;
  if (max <= min) return 0;
  return Math.max(0, Math.min(100, ((v - min) / (max - min)) * 100));
}

/** Backend / client issue → i18n matn */
export function formatWaterIssue(issue, translate) {
  if (!issue) return '';
  const tr = translate;
  if (issue.message_key) {
    return tr(issue.message_key, issue.message_params || {});
  }
  const level = issue.level || LEVEL_CAUTION;
  const metric = issue.metric;
  const value = issue.value;
  const params = { value, ...(issue.message_params || {}) };
  if (metric === 'test_time') return tr('waterAlertMissingTime');
  if (metric === 'do_mg_l') return tr(level === LEVEL_DANGER ? 'waterAlertDoDanger' : 'waterAlertDoCaution', params);
  if (metric === 'ph_level') return tr(level === LEVEL_DANGER ? 'waterAlertPhDanger' : 'waterAlertPhCaution', params);
  if (metric === 'ammonia_mg_l') return tr(level === LEVEL_DANGER ? 'waterAlertAmmoniaDanger' : 'waterAlertAmmoniaCaution', params);
  if (metric === 'nitrite_mg_l') return tr(level === LEVEL_DANGER ? 'waterAlertNitriteDanger' : 'waterAlertNitriteCaution', params);
  if (metric === 'water_transparency_cm') {
    const low = Number(value) < 25;
    if (low) return tr(level === LEVEL_DANGER ? 'waterAlertTransparencyLowDanger' : 'waterAlertTransparencyLowCaution', params);
    return tr(level === LEVEL_DANGER ? 'waterAlertTransparencyHighDanger' : 'waterAlertTransparencyHighCaution', params);
  }
  return '';
}

export function formatWaterAction(issueOrKey, translate) {
  const tr = translate;
  if (!issueOrKey) return '';
  if (typeof issueOrKey === 'string') return tr(issueOrKey);
  if (issueOrKey.action_key) return tr(issueOrKey.action_key);
  return '';
}

export function summarizeWaterIssues(issues, translate) {
  return (issues || []).map((issue) => formatWaterIssue(issue, translate)).filter(Boolean);
}
