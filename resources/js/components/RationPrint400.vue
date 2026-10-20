<template>
  <div id="ration-print-area" class="ration-print-sheet">
    <h1 class="ration-print-title">{{ ration.name }}</h1>
    <table class="ration-print-table">
      <tbody>
        <tr v-for="line in ration.variant_400kg.items" :key="line.product_id">
          <td>{{ line.product_name }}</td>
          <td>{{ formatKgLabelPrint(line.qty_kg) }}</td>
        </tr>
        <tr class="ration-print-empty"><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr class="ration-print-empty"><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr class="ration-print-total">
          <td>{{ $t('printTotal') }}</td>
          <td>{{ Math.round(ration.variant_400kg.batch_kg) }} кг</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import { formatKgLabelPrint } from '../utils/rationFormat';

export default {
  props: {
    ration: { type: Object, required: true },
  },
  setup() {
    return { formatKgLabelPrint };
  },
};
</script>

<style>
.ration-print-sheet {
  display: none;
}

@media print {
  @page {
    margin: 14mm;
  }

  html,
  body {
    background: #fff !important;
  }

  body * {
    visibility: hidden !important;
  }

  #ration-print-area,
  #ration-print-area * {
    visibility: visible !important;
  }

  #ration-print-area {
    display: block !important;
    position: fixed;
    inset: 0;
    padding: 10mm 12mm;
    background: #fff !important;
    color: #000 !important;
    font-family: Arial, 'Segoe UI', sans-serif;
  }

  .ration-print-title {
    margin: 0 0 8mm;
    text-align: center;
    font-size: 18pt;
    font-weight: 700;
    color: #000;
  }

  .ration-print-table {
    width: 72mm;
    margin: 0 auto;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .ration-print-table td {
    border: 1px solid #000;
    padding: 2.5mm 2mm;
    text-align: center;
    font-size: 11pt;
    font-weight: 700;
    vertical-align: middle;
    height: 7mm;
    color: #000;
  }

  .ration-print-table td:first-child {
    width: 55%;
  }

  .ration-print-table td:last-child {
    width: 45%;
  }

  .ration-print-empty td {
    height: 7mm;
  }

  .ration-print-total td {
    font-weight: 700;
  }
}
</style>
