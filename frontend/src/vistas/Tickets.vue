<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api, ESTADOS, fecha, PRIORIDADES } from '../api';

const tickets = ref([]);
const filtros = reactive({ status: '', priority: '' });
const creando = ref(false);
const nuevo = reactive({ title: '', description: '', priority: 'media' });
const error = ref('');

async function cargar() {
  try {
    const q = new URLSearchParams(filtros);
    tickets.value = await api('GET', `/tickets?${q}`);
  } catch (e) {
    error.value = e.message;
  }
}

async function crear() {
  error.value = '';
  try {
    const t = await api('POST', '/tickets', { ...nuevo });
    location.hash = `#/tickets/${t.id}`;
  } catch (e) {
    error.value = e.message;
  }
}

onMounted(cargar);
watch(filtros, cargar);
</script>

<template>
  <div class="cabecera">
    <h1>Tickets</h1>
    <button @click="creando = !creando">{{ creando ? 'Cancelar' : 'Nuevo ticket' }}</button>
  </div>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <form v-if="creando" class="tarjeta formulario" @submit.prevent="crear">
    <label>Título <input v-model="nuevo.title" required minlength="3" maxlength="160" /></label>
    <label>Descripción <textarea v-model="nuevo.description" required minlength="3" rows="4"></textarea></label>
    <label>Prioridad
      <select v-model="nuevo.priority"><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select>
    </label>
    <button>Crear ticket</button>
  </form>
  <div class="filtros">
    <label>Estado
      <select v-model="filtros.status"><option value="">Todos</option><option v-for="(t, k) in ESTADOS" :key="k" :value="k">{{ t }}</option></select>
    </label>
    <label>Prioridad
      <select v-model="filtros.priority"><option value="">Todas</option><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select>
    </label>
  </div>
  <div class="tabla-envoltura">
    <table>
      <thead><tr><th>#</th><th>Título</th><th>Estado</th><th>Prioridad</th><th>Técnico</th><th>Plazo</th></tr></thead>
      <tbody>
        <tr v-for="t in tickets" :key="t.id">
          <td>{{ t.id }}</td>
          <td><a :href="`#/tickets/${t.id}`">{{ t.title }}</a> <small v-if="t.source === 'agente'" class="etiqueta">agente</small></td>
          <td><span class="etiqueta" :class="t.status">{{ ESTADOS[t.status] }}</span></td>
          <td><span class="etiqueta" :class="t.priority">{{ PRIORIDADES[t.priority] }}</span></td>
          <td>{{ t.assignee_name || '—' }}</td>
          <td :class="{ error: t.overdue }">{{ fecha(t.due_at) }}</td>
        </tr>
        <tr v-if="!tickets.length"><td colspan="6" class="tenue">No hay tickets.</td></tr>
      </tbody>
    </table>
  </div>
</template>
