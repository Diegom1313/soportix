<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ESTADOS, fecha, PRIORIDADES } from '../api';

const props = defineProps({ id: Number, usuario: Object });
const ticket = ref(null);
const tecnicos = ref([]);
const texto = ref('');
const interna = ref(false);
const error = ref('');
const personal = computed(() => ['admin', 'tecnico'].includes(props.usuario.role));

async function cargar() {
  try {
    ticket.value = await api('GET', `/tickets/${props.id}`);
  } catch (e) {
    error.value = e.message;
  }
}

async function cambiar(campos) {
  error.value = '';
  try {
    await api('PATCH', `/tickets/${props.id}`, campos);
  } catch (e) {
    error.value = e.message;
  }
  await cargar();
}

async function comentar() {
  error.value = '';
  try {
    await api('POST', `/tickets/${props.id}/comments`, { body: texto.value, internal: interna.value });
    texto.value = '';
    interna.value = false;
    await cargar();
  } catch (e) {
    error.value = e.message;
  }
}

onMounted(async () => {
  await cargar();
  if (personal.value) tecnicos.value = await api('GET', '/technicians').catch(() => []);
});
</script>

<template>
  <p><a href="#/tickets">← Tickets</a></p>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <template v-if="ticket">
    <h1>#{{ ticket.id }} · {{ ticket.title }}</h1>
    <div class="columnas ancha">
      <section>
        <div class="tarjeta"><p class="parrafo">{{ ticket.description }}</p></div>
        <h2>Conversación</h2>
        <div v-for="c in ticket.comments" :key="c.id" class="tarjeta comentario" :class="{ interna: Number(c.internal) }">
          <small><b>{{ c.author || 'Sistema' }}</b> · {{ fecha(c.created_at) }}<span v-if="Number(c.internal)"> · Nota interna</span></small>
          <p class="parrafo">{{ c.body }}</p>
        </div>
        <p v-if="!ticket.comments.length" class="tenue">Sin comentarios.</p>
        <form v-if="ticket.status !== 'cerrado'" class="formulario" @submit.prevent="comentar">
          <label>Añadir comentario <textarea v-model="texto" rows="3" required></textarea></label>
          <label v-if="personal" class="marca-check"><input v-model="interna" type="checkbox" /> Nota interna</label>
          <button>Enviar</button>
        </form>
      </section>
      <aside class="tarjeta datos">
        <template v-if="personal">
          <label>Estado
            <select :value="ticket.status" @change="cambiar({ status: $event.target.value })"><option v-for="(t, k) in ESTADOS" :key="k" :value="k">{{ t }}</option></select>
          </label>
          <label>Prioridad
            <select :value="ticket.priority" @change="cambiar({ priority: $event.target.value })"><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select>
          </label>
          <label>Técnico
            <select :value="ticket.assignee_id ?? ''" @change="cambiar({ assignee_id: $event.target.value ? Number($event.target.value) : null })">
              <option value="">Sin asignar</option><option v-for="t in tecnicos" :key="t.id" :value="Number(t.id)">{{ t.name }}</option>
            </select>
          </label>
        </template>
        <dl>
          <template v-if="!personal">
            <dt>Estado</dt><dd>{{ ESTADOS[ticket.status] }}</dd>
            <dt>Prioridad</dt><dd>{{ PRIORIDADES[ticket.priority] }}</dd>
            <dt>Técnico</dt><dd>{{ ticket.assignee_name || 'Sin asignar' }}</dd>
          </template>
          <dt>Solicitante</dt><dd>{{ ticket.requester_name || (ticket.source === 'agente' ? 'Agente' : '—') }}</dd>
          <template v-if="ticket.device_hostname"><dt>Equipo</dt><dd><a v-if="personal" :href="`#/equipos/${ticket.device_id}`">{{ ticket.device_hostname }}</a><span v-else>{{ ticket.device_hostname }}</span></dd></template>
          <dt>Creado</dt><dd>{{ fecha(ticket.created_at) }}</dd>
          <dt>Plazo</dt><dd :class="{ error: ticket.overdue }">{{ fecha(ticket.due_at) }}</dd>
        </dl>
      </aside>
    </div>
  </template>
</template>
