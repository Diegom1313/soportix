<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, avisar, ESTADOS, fecha, hace, PRIORIDADES } from '../api';
import Icono from '../Icono.vue';

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

async function cambiar(campos, mensaje) {
  try {
    await api('PATCH', `/tickets/${props.id}`, campos);
    avisar(mensaje);
  } catch (e) {
    avisar(e.message, 'error');
  }
  await cargar();
}

async function comentar() {
  try {
    await api('POST', `/tickets/${props.id}/comments`, { body: texto.value, internal: interna.value });
    avisar(interna.value ? 'Nota interna añadida' : 'Comentario enviado');
    texto.value = '';
    interna.value = false;
    await cargar();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

onMounted(async () => {
  await cargar();
  if (personal.value) tecnicos.value = await api('GET', '/technicians').catch(() => []);
});
</script>

<template>
  <a class="volver" href="#/tickets"><Icono nombre="volver" />Tickets</a>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!ticket && !error" class="cargando" aria-label="Cargando"></div>
  <template v-if="ticket">
    <h1>{{ ticket.title }}</h1>
    <p class="subtitulo"><span class="estado" :class="ticket.status">{{ ESTADOS[ticket.status] }}</span><span class="tenue">Ticket #{{ ticket.id }}, abierto {{ hace(ticket.created_at).toLowerCase() }}</span></p>
    <div class="detalle">
      <section>
        <p class="parrafo descripcion">{{ ticket.description }}</p>
        <h2>Conversación</h2>
        <ol class="hilo">
          <li v-for="c in ticket.comments" :key="c.id" :class="{ interna: Number(c.internal) }">
            <small><b>{{ c.author || 'Sistema' }}</b> {{ fecha(c.created_at) }}<span v-if="Number(c.internal)" class="marca-interna">Nota interna</span></small>
            <p class="parrafo">{{ c.body }}</p>
          </li>
          <li v-if="!ticket.comments.length" class="vacio-linea">Aún no hay comentarios.</li>
        </ol>
        <form v-if="ticket.status !== 'cerrado'" class="formulario" @submit.prevent="comentar">
          <label>Añadir comentario <textarea v-model="texto" rows="3" required></textarea></label>
          <div class="acciones">
            <label v-if="personal" class="check"><input v-model="interna" type="checkbox" /> Solo para el equipo técnico</label>
            <button class="principal">{{ interna ? 'Añadir nota' : 'Enviar comentario' }}</button>
          </div>
        </form>
        <p v-else class="tenue">Este ticket está cerrado y ya no admite comentarios.</p>
      </section>
      <aside class="ficha">
        <template v-if="personal">
          <button v-if="ticket.assignee_id !== Number(usuario.id)" @click="cambiar({ assignee_id: usuario.id, status: ticket.status === 'abierto' ? 'en_curso' : ticket.status }, 'Ticket asignado a ti')">Asignármelo</button>
          <label>Estado <select :value="ticket.status" @change="cambiar({ status: $event.target.value }, 'Estado actualizado')"><option v-for="(t, k) in ESTADOS" :key="k" :value="k">{{ t }}</option></select></label>
          <label>Prioridad <select :value="ticket.priority" @change="cambiar({ priority: $event.target.value }, 'Prioridad actualizada')"><option v-for="(t, k) in PRIORIDADES" :key="k" :value="k">{{ t }}</option></select></label>
          <label>Técnico
            <select :value="ticket.assignee_id ?? ''" @change="cambiar({ assignee_id: $event.target.value ? Number($event.target.value) : null }, 'Técnico actualizado')">
              <option value="">Sin asignar</option><option v-for="t in tecnicos" :key="t.id" :value="Number(t.id)">{{ t.name }}</option>
            </select>
          </label>
        </template>
        <dl>
          <template v-if="!personal">
            <dt>Prioridad</dt><dd>{{ PRIORIDADES[ticket.priority] }}</dd>
            <dt>Técnico</dt><dd>{{ ticket.assignee_name || 'Sin asignar' }}</dd>
          </template>
          <dt>Solicitante</dt><dd>{{ ticket.requester_name || (ticket.source === 'agente' ? 'Alerta automática' : 'Sin datos') }}</dd>
          <template v-if="ticket.device_hostname"><dt>Equipo</dt><dd><a v-if="personal" :href="`#/equipos/${ticket.device_id}`">{{ ticket.device_hostname }}</a><span v-else>{{ ticket.device_hostname }}</span></dd></template>
          <dt>Creado</dt><dd>{{ fecha(ticket.created_at) }}</dd>
          <dt>Plazo</dt><dd :class="{ error: ticket.overdue }">{{ fecha(ticket.due_at) }}<span v-if="ticket.overdue"> (vencido)</span></dd>
        </dl>
      </aside>
    </div>
  </template>
</template>
