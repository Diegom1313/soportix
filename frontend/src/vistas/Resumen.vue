<script setup>
import { onMounted, ref } from 'vue';
import { api, ESTADOS, PRIORIDADES } from '../api';

const datos = ref(null);
const error = ref('');
onMounted(async () => {
  try {
    datos.value = await api('GET', '/stats');
  } catch (e) {
    error.value = e.message;
  }
});
const maximo = (o) => Math.max(1, ...Object.values(o));
</script>

<template>
  <h1>Resumen</h1>
  <p v-if="error" class="error">{{ error }}</p>
  <template v-if="datos">
    <div class="cifras">
      <div class="cifra"><b>{{ datos.by_status.abierto + datos.by_status.en_curso }}</b><span>Tickets activos</span></div>
      <div class="cifra" :class="{ alerta: datos.overdue }"><b>{{ datos.overdue }}</b><span>Fuera de plazo</span></div>
      <div class="cifra"><b>{{ datos.devices }}</b><span>Equipos</span></div>
      <div class="cifra" :class="{ alerta: datos.devices_disk_alert }"><b>{{ datos.devices_disk_alert }}</b><span>Con disco casi lleno</span></div>
      <div class="cifra"><b>{{ datos.devices_stale }}</b><span>Sin informar en 24 h</span></div>
    </div>
    <div class="columnas">
      <section class="tarjeta">
        <h2>Tickets por estado</h2>
        <div v-for="(n, k) in datos.by_status" :key="k" class="fila-barra">
          <span>{{ ESTADOS[k] }}</span><div class="pista"><i :style="{ width: (n / maximo(datos.by_status)) * 100 + '%' }"></i></div><b>{{ n }}</b>
        </div>
      </section>
      <section class="tarjeta">
        <h2>Activos por prioridad</h2>
        <div v-for="(n, k) in datos.open_by_priority" :key="k" class="fila-barra">
          <span>{{ PRIORIDADES[k] }}</span><div class="pista"><i :style="{ width: (n / maximo(datos.open_by_priority)) * 100 + '%' }"></i></div><b>{{ n }}</b>
        </div>
      </section>
    </div>
  </template>
</template>
