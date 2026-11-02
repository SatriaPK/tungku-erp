import { useEffect, useRef, useState, type FormEvent } from 'react'
import { updateProfile, type AuthUser, type ApiError } from '../../lib/auth-api'
import './profile-dialog.css'

export default function ProfileDialog({ user, onClose, onSaved }: { user: AuthUser; onClose: () => void; onSaved: (user: AuthUser) => void }) {
  const dialog = useRef<HTMLDialogElement>(null)
  const [name, setName] = useState(user.name)
  const [email, setEmail] = useState(user.email)
  const [phone, setPhone] = useState(user.phone ?? '')
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  useEffect(() => { const element = dialog.current; element?.showModal(); return () => element?.close() }, [])
  async function submit(event: FormEvent) {
    event.preventDefault()
    setErrors({})
    if (password && password !== confirmation) { setErrors({ password_confirmation: 'Konfirmasi kata sandi tidak cocok.' }); return }
    setBusy(true)
    try {
      const saved = await updateProfile({ name, email, phone, ...(password ? { current_password: currentPassword, password, password_confirmation: confirmation } : {}) })
      onSaved(saved)
    } catch (cause) {
      const failure = cause as ApiError
      setErrors({ ...Object.fromEntries(Object.entries(failure.errors ?? {}).map(([field, messages]) => [field, messages[0]])), form: failure.message })
    } finally { setBusy(false) }
  }
  return <dialog className="profile-dialog" ref={dialog} aria-labelledby="profile-dialog-title" onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}><header><div><span>AKUN SAYA</span><h2 id="profile-dialog-title">Edit profil</h2><p>Perbarui data yang dipakai untuk masuk dan dihubungi studio.</p></div><button type="button" aria-label="Tutup" onClick={onClose} disabled={busy}>&times;</button></header><form onSubmit={(event) => void submit(event)}><label>Nama lengkap<input autoFocus required maxLength={255} value={name} onChange={(event) => setName(event.target.value)} />{errors.name && <small role="alert">{errors.name}</small>}</label><label>Email<input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} />{errors.email && <small role="alert">{errors.email}</small>}</label><label>Nomor telepon<input type="tel" inputMode="tel" required placeholder="081234567890" value={phone} onChange={(event) => setPhone(event.target.value)} />{errors.phone && <small role="alert">{errors.phone}</small>}</label><div className="profile-password"><h3>Ubah kata sandi <small>(opsional)</small></h3><label>Kata sandi saat ini<input type="password" autoComplete="current-password" value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} required={Boolean(password)} />{errors.current_password && <small role="alert">{errors.current_password}</small>}</label><label>Kata sandi baru<input type="password" autoComplete="new-password" minLength={8} value={password} onChange={(event) => setPassword(event.target.value)} />{errors.password && <small role="alert">{errors.password}</small>}</label><label>Ulangi kata sandi baru<input type="password" autoComplete="new-password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required={Boolean(password)} />{errors.password_confirmation && <small role="alert">{errors.password_confirmation}</small>}</label></div>{errors.form && <p className="profile-form-error" role="alert">{errors.form}</p>}<footer><button type="button" onClick={onClose} disabled={busy}>Batal</button><button type="submit" disabled={busy}>{busy ? 'Menyimpan...' : 'Simpan Perubahan'}</button></footer></form></dialog>
}
