import { useState, type FormEvent, type ReactNode } from 'react'
import type { ApiError } from '../../lib/auth-api'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { Button, Icon, Input } from '../../components/ui'
import { getApiErrorMessage, getCurrentUser, login, register, type UserRole } from '../../lib/auth-api'
import tungkuLogo from '../../assets/tungku-primary.svg'
import './LoginScreen.css'

function AuthLayout({ children, variant = 'client' }: { children: ReactNode; variant?: 'client' | 'staff' }) {
  if (variant === 'staff') {
    return (
      <main className="staff-login-screen">
        <section aria-label="Login Tungku Studio" className="staff-login">
          <Link aria-label="Tungku Studio" className="staff-login__brand" to="/staff/login">
            <img alt="Tungku Studio" src={tungkuLogo} />
          </Link>
          <div className="staff-login__card">{children}</div>
          <footer className="staff-login__footer">© Tungku Recording Studio 2026</footer>
        </section>
      </main>
    )
  }

  return (
    <main className="login-screen">
      <section aria-label="Tungku Studio" className="login-layout">
        <aside aria-label="Tungku Studio" className="login-story">
          <Link aria-label="Tungku Studio, halaman masuk" className="login-story__brand" to="/login">
            <img alt="Tungku Studio" className="login-story__logo" src={tungkuLogo} />
          </Link>
        </aside>
        <section className="login-panel">
          <div className="login-panel__content">{children}</div>
          <footer className="login-panel__footer">© Tungku Recording Studio 2026</footer>
        </section>
      </section>
    </main>
  )
}

function AuthFeedback({ message, success = false }: { message: string; success?: boolean }) {
  return (
    <p aria-live="polite" className={`auth-feedback${success ? ' auth-feedback--success' : ''}`} role={success ? 'status' : 'alert'}>
      {message}
    </p>
  )
}

function destinationForRole(role: UserRole) {
  if (role === 'manager') return '/dashboard'
  if (role === 'operator') return '/operator'
  return '/client'
}

function roleMatchesEntry(role: UserRole, entry: 'client' | 'staff') {
  return entry === 'client' ? role === 'client' : role === 'manager' || role === 'operator'
}

function LoginForm({ entry }: { entry: 'client' | 'staff' }) {
  const location = useLocation()
  const navigate = useNavigate()
  const [passwordVisible, setPasswordVisible] = useState(false)
  const [feedback, setFeedback] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFeedback('')
    setFieldErrors({})
    setIsSubmitting(true)
    const form = new FormData(event.currentTarget)

    try {
      await login(String(form.get('email')), String(form.get('password')))
      const user = await getCurrentUser()
      if (!roleMatchesEntry(user.role, entry)) {
        setFeedback(entry === 'client'
          ? 'Akun ini adalah akun Manager/Operator. Silakan masuk melalui portal Studio.'
          : 'Akun ini adalah akun Klien. Silakan masuk melalui portal Klien.')
        return
      }
      navigate(destinationForRole(user.role), { replace: true })
    } catch (error) {
      setFeedback(getApiErrorMessage(error))
      if (error instanceof Error && 'errors' in error) {
        const errors = (error as ApiError).errors
        if (errors) setFieldErrors(Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? 'Periksa kembali isian ini.'])))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <>
      {entry === 'client' && (
        <header className="login-panel__intro">
          <h1>Login</h1>
        </header>
      )}

      {location.state?.registered && <AuthFeedback message="Akun berhasil dibuat. Silakan masuk dengan email dan password-mu." success />}

      <form className="login-form" id="login-form" onSubmit={handleSubmit}>
        <div className="login-form__fields">
          <Input autoComplete="username" className="login-form__input" error={fieldErrors.email} label="Email" name="email" placeholder="nama@email.com" required type="email" />
          <Input
            autoComplete="current-password"
            className="login-form__input"
            error={fieldErrors.password}
            label="Password"
            name="password"
            placeholder="Masukkan password"
            required
            trailingAction={(
              <button
                aria-label={passwordVisible ? 'Sembunyikan password' : 'Tampilkan password'}
                aria-pressed={passwordVisible}
                className="login-form__password-toggle"
                onClick={() => setPasswordVisible(!passwordVisible)}
                type="button"
              >
                <Icon name={passwordVisible ? 'eye-off' : 'eye'} size={20} />
              </button>
            )}
            type={passwordVisible ? 'text' : 'password'}
          />
        </div>
        {feedback && <AuthFeedback message={feedback} />}
      </form>
      {entry === 'client' && <div className="login-form__options"><Link className="auth-link" to="/forgot-password">Lupa password?</Link></div>}
      <div className="login-panel__actions">
        <Button className="login-form__submit" disabled={isSubmitting} form="login-form" type="submit">
          {isSubmitting ? 'Memproses…' : 'Masuk'}
        </Button>
      </div>
      {entry === 'client' && <p className="login-panel__note">Belum jadi Kawan Bara? <Link className="auth-link" to="/register">Buat Akun</Link></p>}
    </>
  )
}

function RegisterForm() {
  const navigate = useNavigate()
  const [feedback, setFeedback] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFeedback('')
    setFieldErrors({})
    setIsSubmitting(true)
    const form = new FormData(event.currentTarget)

    try {
      await register({
        name: String(form.get('name')),
        email: String(form.get('email')),
        phone: String(form.get('phone')),
        password: String(form.get('password')),
        password_confirmation: String(form.get('password_confirmation')),
        crm_consent: form.get('crm_consent') === 'on',
      })
      navigate('/login', { state: { registered: true } })
    } catch (error) {
      setFeedback(getApiErrorMessage(error))
      if (error instanceof Error && 'errors' in error) {
        const errors = (error as ApiError).errors
        if (errors) setFieldErrors(Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? 'Periksa kembali isian ini.'])))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <>
      <header className="login-panel__intro">
        <h1>Daftar akun</h1>
      </header>
      <form className="login-form login-form--register" onSubmit={handleSubmit}>
        <Input autoComplete="name" error={fieldErrors.name} label="Nama lengkap" name="name" placeholder="Nama lengkapmu" required />
        <Input autoComplete="email" error={fieldErrors.email} label="Email" name="email" placeholder="nama@email.com" required type="email" />
        <Input autoComplete="tel" error={fieldErrors.phone} label="Nomor telepon" name="phone" placeholder="08xxxxxxxxxx" required type="tel" />
        <Input autoComplete="new-password" error={fieldErrors.password} label="Password" minLength={8} name="password" placeholder="Minimal 8 karakter" required type="password" />
        <Input autoComplete="new-password" error={fieldErrors.password_confirmation} label="Ulangi password" minLength={8} name="password_confirmation" placeholder="Masukkan kembali password" required type="password" />
        <label className="auth-consent">
          <input name="crm_consent" type="checkbox" />
          <span>Ya, saya bersedia menerima kabar dan promosi Tungku melalui email. (Opsional)</span>
        </label>
        <Button className="login-form__submit" disabled={isSubmitting} type="submit">
          {isSubmitting ? 'Memproses…' : 'Buat Akun'}
        </Button>
      </form>
      {feedback && <AuthFeedback message={feedback} />}
      <p className="login-panel__note">Sudah menjadi Kawan Bara? <Link className="auth-link" to="/login">Masuk</Link></p>
    </>
  )
}

function ForgotPassword() {
  return (
    <>
      <p className="login-panel__eyebrow">BANTUAN AKUN</p>
      <h1>Lupa password?</h1>
      <p className="login-panel__description">Fitur pengiriman tautan untuk mengatur ulang password belum tersedia. Hubungi tim Tungku untuk mendapatkan bantuan akses akun.</p>
      <a className="login-form__submit login-form__contact" href="mailto:hello@tungkustudio.com">Hubungi tim Tungku</a>
      <p className="login-panel__note"><Link className="auth-link" to="/login">Kembali ke halaman masuk</Link></p>
    </>
  )
}

export default function AuthScreen() {
  return (
    <Routes>
      <Route element={<AuthLayout><LoginForm entry="client" /></AuthLayout>} path="/login" />
      <Route element={<AuthLayout variant="staff"><LoginForm entry="staff" /></AuthLayout>} path="/staff/login" />
      <Route element={<AuthLayout><RegisterForm /></AuthLayout>} path="/register" />
      <Route element={<AuthLayout><ForgotPassword /></AuthLayout>} path="/forgot-password" />
      <Route element={<Navigate replace to="/login" />} path="*" />
    </Routes>
  )
}