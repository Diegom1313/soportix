<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, avisar, copiar, hace } from '../api';
import Icono from '../Icono.vue';
import Modal from '../Modal.vue';

const props = defineProps({ usuario: Object });
const equipos = ref(null);
const busqueda = ref('');
const creando = ref(false);
const nombre = ref('');
const clave = ref(null);
const error = ref('');
const origen = location.origin;
const admin = computed(() => props.usuario.role === 'admin');
const filtrados = computed(() => (equipos.value || []).filter((e) => e.hostname.toLowerCase().includes(busqueda.value.toLowerCase())));
const linux = computed(() => clave.value && `SOPORTIX_URL=${origen} SOPORTIX_KEY=${clave.value.agent_key} ./soportix-agent.sh`);
const windows = computed(() => clave.value && `$env:SOPORTIX_URL="${origen}"; $env:SOPORTIX_KEY="${clave.value.agent_key}"; .\\soportix-agent.ps1`);

async function cargar() {
  try {
    equipos.value = await api('GET', '/devices');
  } catch (e) {
    error.value = e.message;
  }
}

async function alta() {
  try {
    clave.value = await api('POST', '/devices', { hostname: nombre.value });
    avisar('Equipo añadido');
    nombre.value = '';
    await cargar();
  } catch (e) {
    avisar(e.message, 'error');
  }
}

function cerrar() {
  creando.value = false;
  clave.value = null;
}

const sinInformar = (e) => !e.last_seen || Date.now() - new Date(e.last_seen) > 86400000;
onMounted(cargar);
</script>

<template>
  <div class="cabecera">
    <h1>Equipos</h1>
    <button v-if="admin" class="principal" @click="creando = true"><Icono nombre="mas" />Añadir equipo</button>
  </div>
  <div class="filtros"><label class="busqueda"><span class="oculto">Buscar equipo</span><Icono nombre="buscar" /><input v-model="busqueda" type="search" placeholder="Buscar equipo" /></label></div>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div v-if="!equipos && !error" class="cargando" aria-label="Cargando"></div>
  <div v-if="equipos && filtrados.length" class="tabla-envoltura">
    <table>
      <thead><tr><th>Equipo</th><th>Sistema</th><th>Memoria</th><th>Disco</th><th>Último informe</th></tr></thead>
      <tbody>
        <tr v-for="e in filtrados" :key="e.id">
          <td><a :href="`#/equipos/${e.id}`"><b>{{ e.hostname }}</b></a><small class="tenue bloque">{{ e.ip || 'IP sin datos' }}</small></td>
          <td>{{ e.os || 'Sin datos' }}</td>
          <td>{{ e.ram_mb ? Math.round(e.ram_mb / 1024) + ' GB' : 'Sin datos' }}</td>
          <td>
            <div v-if="e.disk_used_pct != null" class="medidor" :class="{ lleno: e.disk_used_pct >= 90 }"><div class="pista"><i :style="{ width: e.disk_used_pct + '%' }"></i></div><span>{{ e.disk_used_pct }} %</span></div>
            <span v-else class="tenue">Sin datos</span>
          </td>
          <td :class="{ error: sinInformar(e) }">{{ hace(e.last_seen) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
  <div v-if="equipos && !filtrados.length" class="vacio">
    <p>{{ busqueda ? 'Ningún equipo coincide con la búsqueda.' : 'Todavía no hay equipos en el inventario.' }}</p>
    <button v-if="admin && !busqueda" class="principal" @click="creando = true">Añadir el primero</button>
  </div>
  <Modal v-if="creando" :titulo="clave ? `Conecta ${clave.hostname}` : 'Añadir equipo'" @cerrar="cerrar">
    <form v-if="!clave" class="formulario" @submit.prevent="alta">
      <label>Nombre del equipo <input v-model="nombre" required maxlength="190" autofocus placeholder="Por ejemplo, AULA-PC02" /></label>
      <button class="principal">Añadir equipo</button>
    </form>
    <div v-else class="guia">
      <p class="alerta-caja"><Icono nombre="aviso" />Copia este comando ahora: la clave no se volverá a mostrar.</p>
      <h3>En Linux</h3>
      <div class="comando"><code>{{ linux }}</code><button class="icono-boton" aria-label="Copiar comando de Linux" @click="copiar(linux)"><Icono nombre="copiar" /></button></div>
      <h3>En Windows (PowerShell)</h3>
      <div class="comando"><code>{{ windows }}</code><button class="icono-boton" aria-label="Copiar comando de Windows" @click="copiar(windows)"><Icono nombre="copiar" /></button></div>
      <p class="tenue">Ejecútalo en el equipo desde la carpeta del agente y prográmalo con cron o con el Programador de tareas.</p>
    </div>
  </Modal>
</template>
