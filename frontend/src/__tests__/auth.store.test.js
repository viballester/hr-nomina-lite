import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

// Simulamos el módulo http: el test no hace peticiones reales.
vi.mock('@/api/http', () => ({ default: { post: vi.fn(), get: vi.fn() } }))
import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'

// Node no tiene localStorage: un sustituto mínimo en memoria.
function fakeStorage() {
  const data = new Map()
  return {
    getItem: (k) => (data.has(k) ? data.get(k) : null),
    setItem: (k, v) => data.set(k, String(v)),
    removeItem: (k) => data.delete(k),
  }
}

describe('useAuthStore', () => {
  beforeEach(() => {
    vi.stubGlobal('localStorage', fakeStorage())
    vi.clearAllMocks()
    setActivePinia(createPinia())
  })

  it('login guarda el token en el store y en localStorage', async () => {
    http.post.mockResolvedValue({ data: { token: 'abc', user: { id: 1 } } })
    const auth = useAuthStore()

    await auth.login('admin@demo.test', 'password')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.user).toEqual({ id: 1 })
    expect(localStorage.getItem('token')).toBe('abc')
  })

  it('login fallido no deja sesión', async () => {
    http.post.mockRejectedValue({ response: { status: 422 } })
    const auth = useAuthStore()

    await expect(auth.login('x@demo.test', 'mal')).rejects.toBeTruthy()
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })

  it('logout limpia la sesión local aunque falle la petición', async () => {
    localStorage.setItem('token', 'abc')
    http.post.mockRejectedValue(new Error('sin red'))
    const auth = useAuthStore()

    await expect(auth.logout()).rejects.toThrow('sin red')
    expect(auth.isAuthenticated).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })
})
