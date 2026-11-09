<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ESTADOS, hace } from '../api';
import Icono from '../Icono.vue';

const props = defineProps({ id: Number });
const equipo = ref(null);
const error = ref('');
const busqueda = ref('');
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
const software = computed(() => (equipo.value?.software || []).filter((s) => s.toLowerCase().includes(busqueda.value.toLowerCase())));
</script>

<template>
  <a class="volver" href="#/equipos"><Icono nombre="volver" />Equipos</a>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!equipo && !error" class="cargando" aria-label="Cargando"></div>
  <template v-if="equipo">
    <h1>{{ equipo.hostname }}</h1>
    <p class="subtitulo tenue">Último informe: {{ hace(equipo.last_seen).toLowerCase() }}</p>
    <div class="detalle">
      <section>
        <h2 class="sin-margen">Uso de disco</h2>
        <svg v-if="puntos" viewBox="0 0 300 100" preserveAspectRatio="none" class="grafica" role="img" :aria-label="`Uso de disco actual: ${equipo.disk_used_pct} %`">
          <line x1="0" y1="10" x2="300" y2="10" class="umbral" /><polyline :points="puntos" />
        </svg>
        <div v-else-if="equipo.disk_used_pct != null" class="medidor grande" :class="{ lleno: equipo.disk_used_pct >= 90 }"><div class="pista"><i :style="{ width: equipo.disk_used_pct + '%' }"></i></div><span>{{ equipo.disk_used_pct }} %</span></div>
        <p v-else class="vacio-linea">Este equipo todavía no ha enviado ningún informe.</p>
        <h2>Tickets del equipo</h2>
        <ul class="filas">
          <li v-for="t in equipo.tickets" :key="t.id" :class="`borde-${t.priority}`"><a :href="`#/tickets/${t.id}`">{{ t.title }}</a><span class="estado" :class="t.status">{{ ESTADOS[t.status] }}</span></li>
          <li v-if="!equipo.tickets.length" class="vacio-linea">Este equipo no tiene tickets.</li>
        </ul>
        <div class="cabecera software-cabecera"><h2>Software instalado ({{ equipo.software.length }})</h2></div>
        <label v-if="equipo.software.length" class="busqueda"><span class="oculto">Buscar software</span><Icono nombre="buscar" /><input v-model="busqueda" type="search" placeholder="Buscar software" /></label>
        <ul class="software"><li v-for="s in software" :key="s">{{ s }}</li></ul>
      </section>
      <aside class="ficha">
        <dl>
          <dt>Sistema</dt><dd>{{ equipo.os || 'Sin datos' }}</dd>
          <dt>Procesador</dt><dd>{{ equipo.cpu || 'Sin datos' }}</dd>
          <dt>Memoria</dt><dd>{{ equipo.ram_mb ? Math.round(equipo.ram_mb / 1024) + ' GB' : 'Sin datos' }}</dd>
          <dt>Disco</dt><dd>{{ equipo.disk_total_gb != null ? equipo.disk_total_gb + ' GB' : 'Sin datos' }}</dd>
          <dt>IP</dt><dd>{{ equipo.ip || 'Sin datos' }}</dd>
        </dl>
      </aside>
    </div>
  </template>
</template>
