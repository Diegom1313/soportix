<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ESTADOS, fecha } from '../api';

const props = defineProps({ id: Number });
const equipo = ref(null);
const error = ref('');
onMounted(async () => {
  try {
    equipo.value = await api('GET', `/devices/${props.id}`);
  } catch (e) {
    error.value = e.message;
  }
});
const puntos = computed(() => {
  const h = (equipo.value?.history || []).filter((p) => p.disk_used_pct != null);
  if (h.length < 2) return '';
  return h.map((p, i) => `${(i / (h.length - 1)) * 300},${100 - p.disk_used_pct}`).join(' ');
});
</script>

<template>
  <p><a href="#/equipos">← Equipos</a></p>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <template v-if="equipo">
    <h1>{{ equipo.hostname }}</h1>
    <div class="columnas">
      <section class="tarjeta datos">
        <dl>
          <dt>Sistema</dt><dd>{{ equipo.os || '—' }}</dd>
          <dt>Procesador</dt><dd>{{ equipo.cpu || '—' }}</dd>
          <dt>RAM</dt><dd>{{ equipo.ram_mb ? Math.round(equipo.ram_mb / 1024) + ' GB' : '—' }}</dd>
          <dt>Disco</dt><dd>{{ equipo.disk_total_gb ?? '—' }} GB, {{ equipo.disk_used_pct ?? '—' }} % usado</dd>
          <dt>IP</dt><dd>{{ equipo.ip || '—' }}</dd>
          <dt>Último informe</dt><dd>{{ fecha(equipo.last_seen) }}</dd>
        </dl>
      </section>
      <section class="tarjeta">
        <h2>Uso de disco</h2>
        <svg v-if="puntos" viewBox="0 0 300 100" class="grafica" role="img" aria-label="Evolución del uso de disco">
          <line x1="0" y1="10" x2="300" y2="10" class="umbral" /><polyline :points="puntos" />
        </svg>
        <p v-else class="tenue">Hacen falta al menos dos informes.</p>
      </section>
    </div>
    <h2>Tickets del equipo</h2>
    <ul class="lista">
      <li v-for="t in equipo.tickets" :key="t.id"><a :href="`#/tickets/${t.id}`">#{{ t.id }} {{ t.title }}</a> <span class="etiqueta" :class="t.status">{{ ESTADOS[t.status] }}</span></li>
      <li v-if="!equipo.tickets.length" class="tenue">Sin tickets.</li>
    </ul>
    <h2>Software ({{ equipo.software.length }})</h2>
    <ul class="software"><li v-for="s in equipo.software" :key="s">{{ s }}</li></ul>
  </template>
</template>
