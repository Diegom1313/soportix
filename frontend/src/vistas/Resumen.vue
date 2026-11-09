<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, fecha, hace, PRIORIDADES } from '../api';
import Icono from '../Icono.vue';

const datos = ref(null);
const urgentes = ref([]);
const equipos = ref([]);
const error = ref('');

onMounted(async () => {
  try {
    const [s, r, d] = await Promise.all([api('GET', '/stats'), api('GET', '/tickets?overdue=1'), api('GET', '/devices')]);
    datos.value = s;
    urgentes.value = r.items.slice(0, 6);
    equipos.value = d.filter((e) => e.disk_used_pct >= 90);
  } catch (e) {
    error.value = e.message;
  }
});

const activos = computed(() => (datos.value ? datos.value.by_status.abierto + datos.value.by_status.en_curso : 0));
const titular = computed(() => {
  if (!datos.value) return '';
  const { overdue, devices_disk_alert: disco } = datos.value;
  const partes = [];
  if (overdue) partes.push(overdue === 1 ? '1 ticket fuera de plazo' : `${overdue} tickets fuera de plazo`);
  if (disco) partes.push(disco === 1 ? '1 equipo con el disco casi lleno' : `${disco} equipos con el disco casi lleno`);
  if (partes.length) return `Hoy hay que mirar ${partes.join(' y ')}.`;
  return activos.value ? `Todo dentro de plazo, con ${activos.value} ${activos.value === 1 ? 'ticket' : 'tickets'} en marcha.` : 'No hay tickets pendientes.';
});
const cola = computed(() => {
  if (!datos.value) return [];
  const p = datos.value.open_by_priority;
  return ['critica', 'alta', 'media', 'baja'].map((k) => ({ k, n: p[k], texto: PRIORIDADES[k] }));
});
</script>

<template>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!datos && !error" class="cargando" aria-label="Cargando"></div>
  <template v-if="datos">
    <h1 class="titular">{{ titular }}</h1>
    <section aria-label="Tickets activos por prioridad" class="cola">
      <div class="cola-barra">
        <i v-for="c in cola.filter((x) => x.n)" :key="c.k" :class="`p-${c.k}`" :style="{ flexGrow: c.n }"></i>
        <i v-if="!activos" class="vacia"></i>
      </div>
      <div class="cola-leyenda">
        <a v-for="c in cola" :key="c.k" :href="`#/tickets?priority=${c.k}`"><span class="punto" :class="`p-${c.k}`"></span>{{ c.texto }} <b>{{ c.n }}</b></a>
      </div>
    </section>
    <dl class="datos-linea">
      <a href="#/tickets?status=abierto"><dt>Sin empezar</dt><dd>{{ datos.by_status.abierto }}</dd></a>
      <a href="#/tickets?status=en_curso"><dt>En curso</dt><dd>{{ datos.by_status.en_curso }}</dd></a>
      <a href="#/tickets?status=resuelto"><dt>Resueltos</dt><dd>{{ datos.by_status.resuelto }}</dd></a>
      <a href="#/equipos"><dt>Equipos</dt><dd>{{ datos.devices }}</dd></a>
      <a href="#/equipos"><dt>Sin informar en 24 h</dt><dd>{{ datos.devices_stale }}</dd></a>
    </dl>
    <div class="dos">
      <section>
        <h2>Fuera de plazo</h2>
        <ul class="filas">
          <li v-for="t in urgentes" :key="t.id" :class="`borde-${t.priority}`">
            <a :href="`#/tickets/${t.id}`">{{ t.title }}</a><small class="error">Venció el {{ fecha(t.due_at) }}</small>
          </li>
          <li v-if="!urgentes.length" class="vacio-linea">Ningún ticket ha superado su plazo.</li>
        </ul>
      </section>
      <section>
        <h2>Disco casi lleno</h2>
        <ul class="filas">
          <li v-for="e in equipos" :key="e.id" class="borde-alta">
            <a :href="`#/equipos/${e.id}`">{{ e.hostname }}</a><small>{{ e.disk_used_pct }} % usado, {{ hace(e.last_seen).toLowerCase() }}</small>
          </li>
          <li v-if="!equipos.length" class="vacio-linea">Todos los discos tienen espacio.</li>
        </ul>
      </section>
    </div>
  </template>
</template>
