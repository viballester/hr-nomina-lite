<script setup>
// Recibe el "meta" de un listado paginado de Laravel y avisa al padre de la página pedida
defineProps({
  meta: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['change'])
</script>

<template>
  <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Paginación">
    <button
      type="button"
      class="btn btn-secondary"
      :disabled="loading || meta.current_page === 1"
      @click="emit('change', meta.current_page - 1)"
    >
      Anterior
    </button>
    <span>Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
    <button
      type="button"
      class="btn btn-secondary"
      :disabled="loading || meta.current_page === meta.last_page"
      @click="emit('change', meta.current_page + 1)"
    >
      Siguiente
    </button>
  </nav>
</template>
