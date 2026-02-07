import axios, { AxiosError, AxiosResponse } from 'axios'

export const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json'
  }
})

// Add JWT to all requests
api.interceptors.request.use(config => {
  // Try to get token from window (passed by PHP), then localStorage
  const token = (window as any).NGN_ADMIN_TOKEN || localStorage.getItem('ngn_admin_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 responses by redirecting to login
api.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      // Clear stored token and redirect to login
      localStorage.removeItem('ngn_admin_token')
      window.location.href = '/login.php?next=/admin-v2'
    }
    return Promise.reject(error)
  }
)

export default api
