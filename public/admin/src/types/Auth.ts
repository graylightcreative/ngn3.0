export interface JWTToken {
  iss: string
  sub: string
  iat: number
  exp: number
  role: 'admin' | 'user'
  user_id?: string
  email?: string
}

export interface AuthContext {
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  error: string | null
}

export interface LoginResponse {
  token: string
  user: {
    id: string
    email: string
    role: string
  }
}
