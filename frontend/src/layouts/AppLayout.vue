<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const roleLabels = { admin: 'Administrador', manager: 'Responsable', employee: 'Empleado' }

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="layout">
    <header class="topbar">
      <RouterLink to="/" class="brand">HR Nómina Lite</RouterLink>

      <nav class="nav" aria-label="Principal">
        <RouterLink to="/">Inicio</RouterLink>
      </nav>

      <div class="session">
        <span v-if="auth.user">
          {{ auth.user.name }}
          <span class="badge">{{ roleLabels[auth.user.role] }}</span>
        </span>
        <button type="button" class="btn btn-secondary" @click="logout">Salir</button>
      </div>
    </header>

    <main class="content">
      <!-- Aquí el router pinta la vista hija de la ruta actual -->
      <RouterView />
    </main>

    <footer class="footer">
      Datos ficticios de demostración. Los cálculos de nómina son una simulación didáctica sin validez legal.
    </footer>
  </div>
</template>
