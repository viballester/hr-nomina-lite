<script setup>
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <h1>Hola, {{ auth.user?.name }}</h1>
  <p>Usa el menú superior para moverte por la aplicación.</p>
</template>
