<script setup>
import { ref, watch, onMounted } from 'vue'
import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const isAdmin = auth.user?.role === 'admin' // solo el admin crea y edita (EmployeePolicy)

const employees = ref([])
const meta = ref(null) // datos de paginación que devuelve Laravel
const search = ref('')
const loading = ref(false)
const error = ref('')

let lastRequest = 0 // para descartar respuestas que llegan tarde y desordenadas

async function load(page = 1) {
  const requestId = ++lastRequest
  loading.value = true
  error.value = ''

  try {
    const response = await http.get('/employees', {
      params: { search: search.value || undefined, page }, // undefined = no se envía
    })
    if (requestId !== lastRequest) return // ya hay una búsqueda más reciente en curso
    employees.value = response.data.data
    meta.value = response.data.meta
  } catch (e) {
    if (requestId !== lastRequest) return
    error.value = e.response?.data?.message ?? 'No se ha podido cargar el listado.'
  } finally {
    if (requestId === lastRequest) loading.value = false
  }
}

// Debounce: espera a que el usuario deje de escribir 300 ms antes de buscar
let timer
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => load(1), 300)
})

onMounted(() => load())

// La API devuelve "2019-11-25"; se formatea sin pasar por Date para evitar desfases de zona horaria
const formatDate = (iso) => iso.split('-').reverse().join('/')
</script>

<template>
  <div class="page-header">
    <h1>Empleados</h1>
    <input
      v-model="search"
      type="search"
      class="search"
      placeholder="Buscar por nombre o email"
      aria-label="Buscar empleados por nombre o email"
    >
    <RouterLink v-if="isAdmin" :to="{ name: 'employee-create' }" class="btn">Nuevo empleado</RouterLink>
  </div>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <div v-else class="card table-wrapper" :aria-busy="loading">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Departamento</th>
          <th>Puesto</th>
          <th>Alta</th>
          <th>Estado</th>
          <th v-if="isAdmin"><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <!-- :key identifica cada fila para que Vue actualice solo las que cambian -->
        <tr v-for="employee in employees" :key="employee.id">
          <td>{{ employee.name }}</td>
          <td>{{ employee.email }}</td>
          <td>{{ employee.department.name }}</td>
          <td>{{ employee.position.name }}</td>
          <td>{{ formatDate(employee.hire_date) }}</td>
          <td>
            <span class="badge" :class="`badge-${employee.status}`">
              {{ employee.status === 'active' ? 'Activo' : 'Baja' }}
            </span>
          </td>
          <td v-if="isAdmin">
            <RouterLink :to="{ name: 'employee-edit', params: { id: employee.id } }">Editar</RouterLink>
          </td>
        </tr>
        <tr v-if="!loading && employees.length === 0">
          <td :colspan="isAdmin ? 7 : 6" class="empty">No hay empleados que coincidan con la búsqueda.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Paginación">
    <button
      type="button"
      class="btn btn-secondary"
      :disabled="loading || meta.current_page === 1"
      @click="load(meta.current_page - 1)"
    >
      Anterior
    </button>
    <span>Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}</span>
    <button
      type="button"
      class="btn btn-secondary"
      :disabled="loading || meta.current_page === meta.last_page"
      @click="load(meta.current_page + 1)"
    >
      Siguiente
    </button>
  </nav>
</template>
