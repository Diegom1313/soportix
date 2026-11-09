<script setup>
import { onMounted, ref } from 'vue';
import { api, fecha } from '../api';

const props = defineProps({ usuario: Object });
const equipos = ref([]);
const nombre = ref('');
const clave = ref(null);
const error = ref('');

async function cargar() {
  try {
    equipos.value = await api('GET', '/devices');
  } catch (e) {
    error.value = e.message;
  }
}

async function alta() {
  error.value = '';
  try {
    clave.value = await api('POST', '/devices', { hostname: nombre.value });
    nombre.value = '';
    await cargar();
  } catch (e) {
    error.value = e.message;
  }
}

onMounted(cargar);
</script>

<template>
  <h1>Equipos</h1>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <form v-if="props.usuario.role === 'admin'" class="filtros" @submit.prevent="alta">
    <label>Nombre del equipo <input v-model="nombre" required maxlength="190" /></label>
    <button>Dar de alta</button>
  </form>
  <div v-if="clave" class="tarjeta aviso">
    <b>Clave de agente de {{ clave.hostname }}</b>
    <code>{{ clave.agent_key }}</code>
    <small>Guárdala ahora: no se volverá a mostrar.</small>
  </div>
  <div class="tabla-envoltura">
    <table>
      <thead><tr><th>Equipo</th><th>Sistema</th><th>RAM</th><th>Disco usado</th><th>IP</th><th>Último informe</th></tr></thead>
      <tbody>
        <tr v-for="e in equipos" :key="e.id">
          <td><a :href="`#/equipos/${e.id}`">{{ e.hostname }}</a></td>
          <td>{{ e.os || '—' }}</td>
          <td>{{ e.ram_mb ? Math.round(e.ram_mb / 1024) + ' GB' : '—' }}</td>
          <td :class="{ error: e.disk_used_pct >= 90 }">{{ e.disk_used_pct ?? '—' }}{{ e.disk_used_pct != null ? ' %' : '' }}</td>
          <td>{{ e.ip || '—' }}</td>
          <td>{{ fecha(e.last_seen) }}</td>
        </tr>
        <tr v-if="!equipos.length"><td colspan="6" class="tenue">No hay equipos.</td></tr>
      </tbody>
    </table>
  </div>
</template>
