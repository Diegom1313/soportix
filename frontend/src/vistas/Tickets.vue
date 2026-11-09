<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { api, avisar, ESTADOS, fecha, PRIORIDADES } from '../api';
import Icono from '../Icono.vue';
import Modal from '../Modal.vue';

const props = defineProps({ consulta: Object });
const lista = ref(null);
const cargando = ref(true);
const filtros = reactive({ q: '', status: props.consulta?.status || '', priority: props.consulta?.priority || '' });
const pagina = ref(1);
const creando = ref(false);
const nuevo = reactive({ title: '', description: '', priority: 'media' });
const error = ref('');
const enviando = ref(false);
const hayFiltros = () => filtros.q || filtros.status || filtros.priority;

async function cargar() {
  cargando.value = true;
  error.value = '';
  try {
    const q = new URLSearchParams({ ...filtros, page: pagina.value });
    if (props.consulta?.overdue) q.set('overdue', '1');
    lista.value = await api('GET', `/tickets?${q}`);
    pagina.value = lista.value.page;
  } catch (e) {
    error.value = e.message;
  } finally {
    cargando.value = false;
  }
}

async function crear() {
  enviando.value = true;
  try {
    const t = await api('POST', '/tickets', { ...nuevo });
    avisar('Ticket creado');
    location.hash = `#/tickets/${t.id}`;
  } catch (e) {
    avisar(e.message, 'error');
  } finally {
    enviando.value = false;
  }
}

function limpiar() {
  Object.assign(filtros, { q: '', status: '', priority: '' });
}

let espera;
watch(() => [filtros.status, filtros.priority], () => { pagina.value = 1; cargar(); });
watch(() => filtros.q, () => {
  clearTimeout(espera);
  espera = setTimeout(() => { pagina.value = 1; cargar(); }, 250);
});
watch(pagina, (a, b) => { if (a !== b && !cargando.value) cargar(); });
onMounted(cargar);
</script>

<template>
  <div class="cabecera">
    <h1>Tickets</h1>
    <button class="principal" @click="creando = true"><Icono nombre="mas" />Nuevo ticket</button>
  </div>
  <div class="filtros" role="search">
    <label class="busqueda"><span class="oculto">Buscar por título</span><Icono nombre="buscar" /><input v-model="filtros.q" type="search" placeholder="Buscar por título" maxlength="100" /></label>
    <label><span class="oculto">Estado</span><select v-model="filtros.status"><option value="">Todos los estados</option><option v-for="(t, k) in ESTADOS" :key="k" :value="k">{{ t }}</option></select></label>
    <label><span class="oculto">Prioridad</span><select v-model="filtros.priority"><option value="">Todas las prioridades</option><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select></label>
  </div>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!lista && cargando" class="cargando" aria-label="Cargando"></div>
  <template v-if="lista">
    <ul class="tickets" :class="{ atenuado: cargando }">
      <li v-for="t in lista.items" :key="t.id" :class="`borde-${t.priority}`">
        <a class="ticket-titulo" :href="`#/tickets/${t.id}`"><span class="tenue">#{{ t.id }}</span> {{ t.title }}</a>
        <div class="ticket-meta">
          <span class="estado" :class="t.status">{{ ESTADOS[t.status] }}</span>
          <span>{{ PRIORIDADES[t.priority] }}</span>
          <span v-if="t.source === 'agente'" class="tenue">Alerta automática</span>
          <span class="tenue">{{ t.assignee_name || 'Sin asignar' }}</span>
          <span class="ticket-plazo" :class="t.overdue ? 'error' : 'tenue'">{{ t.overdue ? 'Venció el' : 'Plazo:' }} {{ fecha(t.due_at) }}</span>
        </div>
      </li>
    </ul>
    <div v-if="!lista.items.length" class="vacio">
      <template v-if="hayFiltros()"><p>Ningún ticket coincide con estos filtros.</p><button @click="limpiar">Quitar filtros</button></template>
      <template v-else><p>Todavía no hay tickets.</p><button class="principal" @click="creando = true">Crear el primero</button></template>
    </div>
    <nav v-if="lista.pages > 1" class="paginas" aria-label="Paginación">
      <button :disabled="pagina <= 1" @click="pagina--">Anterior</button>
      <span>Página {{ lista.page }} de {{ lista.pages }}, {{ lista.total }} tickets</span>
      <button :disabled="pagina >= lista.pages" @click="pagina++">Siguiente</button>
    </nav>
  </template>
  <Modal v-if="creando" titulo="Nuevo ticket" @cerrar="creando = false">
    <form class="formulario" @submit.prevent="crear">
      <label>¿Qué ocurre? <input v-model="nuevo.title" required minlength="3" maxlength="160" autofocus /></label>
      <label>Cuéntanos los detalles <textarea v-model="nuevo.description" required minlength="3" rows="5"></textarea></label>
      <label>Prioridad <select v-model="nuevo.priority"><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select></label>
      <button class="principal" :disabled="enviando">Crear ticket</button>
    </form>
  </Modal>
</template>
