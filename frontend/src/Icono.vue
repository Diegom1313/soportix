<script setup>
const TRAZOS = {
  resumen: 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3ZM14 7h6V4h-6v3Z',
  tickets: 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Zm10-2v14',
  equipos: 'M4 5h16v11H4V5Zm5 15h6m-3-4v4',
  usuarios: 'M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM21 19v-1a4 4 0 0 0-3-3.8M15 4.2a3 3 0 0 1 0 5.6',
  actividad: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
  cuenta: 'M8 11V8a4 4 0 0 1 8 0v3M6 11h12v9H6v-9Z',
  salir: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
  tema: 'M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9Z',
  mas: 'M12 5v14M5 12h14',
  buscar: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm9 2-4-4',
  volver: 'M15 6l-6 6 6 6',
  cerrar: 'M6 6l12 12M18 6 6 18',
  copiar: 'M9 9h11v11H9V9ZM5 15H4V4h11v1',
  aviso: 'M12 8v5m0 3.5v.1M10.3 4 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0Z',
};
defineProps({ nombre: String });
</script>

<template>
  <svg class="icono" viewBox="0 0 24 24" aria-hidden="true"><path :d="TRAZOS[nombre]" /></svg>
</template>
