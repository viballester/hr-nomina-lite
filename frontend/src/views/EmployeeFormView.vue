<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/api/http'

// Con props: true en la ruta, el :id de la URL llega aquí como prop
const props = defineProps({ id: { type: String, default: null } })
const isEdit = computed(() => props.id !== null)

const router = useRouter()

const form = ref({
  name: '',
  email: '',
  department_id: '',
  position_id: '',
  hire_date: '',
  status: 'active',
})
const departments = ref([])
const positions = ref([])
const errors = ref({}) // errores 422 por campo: { email: ['Este email ya está registrado.'] }
const error = ref('') // error general (403, servidor caído…)
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    // Las peticiones son independientes: se lanzan a la vez y se espera a todas
    const requests = [http.get('/departments'), http.get('/positions')]
    if (isEdit.value) requests.push(http.get(`/employees/${props.id}`))
    const [deps, pos, emp] = await Promise.all(requests)

    departments.value = deps.data.data
    positions.value = pos.data.data
    if (emp) {
      const e = emp.data.data
      form.value = {
        name: e.name,
        email: e.email,
        department_id: e.department.id,
        position_id: e.position.id,
        hire_date: e.hire_date,
        status: e.status,
      }
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se han podido cargar los datos.'
  } finally {
    loading.value = false
  }
})

async function save() {
  errors.value = {}
  error.value = ''
  saving.value = true

  try {
    if (isEdit.value) {
      await http.patch(`/employees/${props.id}`, form.value)
    } else {
      // status no se envía en el alta: la BD lo crea como 'active'
      const { status, ...data } = form.value
      await http.post('/employees', data)
    }
    router.push({ name: 'employees' })
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors
    } else {
      error.value = e.response?.data?.message ?? 'No se ha podido conectar con el servidor.'
    }
  } finally {
    saving.value = false
  }
}

async function deactivate() {
  if (!confirm(`¿Dar de baja a ${form.value.name}? Podrás reactivarlo cambiando su estado.`)) return

  try {
    await http.delete(`/employees/${props.id}`)
    router.push({ name: 'employees' })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido dar de baja.'
  }
}
</script>

<template>
  <div class="page-header">
    <h1>{{ isEdit ? 'Editar empleado' : 'Nuevo empleado' }}</h1>
    <RouterLink :to="{ name: 'employees' }">Volver al listado</RouterLink>
  </div>

  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <p v-if="loading">Cargando…</p>

  <!-- novalidate: que valide Laravel y se vean sus mensajes, no los del navegador -->
  <form v-else class="card form" novalidate @submit.prevent="save">
    <div class="field">
      <label for="name">Nombre completo</label>
      <input id="name" v-model="form.name" type="text" :aria-invalid="!!errors.name" aria-describedby="name-error">
      <p v-if="errors.name" id="name-error" class="field-error">{{ errors.name[0] }}</p>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input id="email" v-model="form.email" type="email" :aria-invalid="!!errors.email" aria-describedby="email-error">
      <p v-if="errors.email" id="email-error" class="field-error">{{ errors.email[0] }}</p>
    </div>

    <div class="field">
      <label for="department_id">Departamento</label>
      <select id="department_id" v-model="form.department_id" :aria-invalid="!!errors.department_id" aria-describedby="department_id-error">
        <option value="" disabled>Selecciona un departamento</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <p v-if="errors.department_id" id="department_id-error" class="field-error">{{ errors.department_id[0] }}</p>
    </div>

    <div class="field">
      <label for="position_id">Puesto</label>
      <select id="position_id" v-model="form.position_id" :aria-invalid="!!errors.position_id" aria-describedby="position_id-error">
        <option value="" disabled>Selecciona un puesto</option>
        <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <p v-if="errors.position_id" id="position_id-error" class="field-error">{{ errors.position_id[0] }}</p>
    </div>

    <div class="field">
      <label for="hire_date">Fecha de alta</label>
      <input id="hire_date" v-model="form.hire_date" type="date" :aria-invalid="!!errors.hire_date" aria-describedby="hire_date-error">
      <p v-if="errors.hire_date" id="hire_date-error" class="field-error">{{ errors.hire_date[0] }}</p>
    </div>

    <div v-if="isEdit" class="field">
      <label for="status">Estado</label>
      <select id="status" v-model="form.status" :aria-invalid="!!errors.status" aria-describedby="status-error">
        <option value="active">Activo</option>
        <option value="inactive">Baja</option>
      </select>
      <p v-if="errors.status" id="status-error" class="field-error">{{ errors.status[0] }}</p>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn" :disabled="saving">
        {{ saving ? 'Guardando…' : 'Guardar' }}
      </button>
      <button
        v-if="isEdit && form.status === 'active'"
        type="button"
        class="btn btn-danger"
        @click="deactivate"
      >
        Dar de baja
      </button>
    </div>
  </form>
</template>
