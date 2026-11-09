<script setup>
import { onMounted, ref } from 'vue';
import Icono from './Icono.vue';

defineProps({ titulo: String });
const emit = defineEmits(['cerrar']);
const caja = ref(null);
onMounted(() => caja.value.showModal());
</script>

<template>
  <dialog ref="caja" class="modal" @close="emit('cerrar')">
    <header>
      <h2>{{ titulo }}</h2>
      <button type="button" class="icono-boton" aria-label="Cerrar" @click="caja.close()"><Icono nombre="cerrar" /></button>
    </header>
    <slot />
  </dialog>
</template>
