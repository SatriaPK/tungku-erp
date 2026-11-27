import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Icon, type IconName } from '../../components/ui'
import tungkuLogo from '../../assets/tungku-primary.svg'
import { contact, customerJourney, portfolio, services, studioProfile } from './landing-content'
import './LandingPage.css'

type CatalogPackage = {
  id: number
  name: string
  description: string | null
  track_count: number
  recording_hours: number
  include_recording: boolean
  include_editing: boolean
  include_mixing: boolean
  include_mastering: boolean
  price: number
}

const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://localhost:8000'
const rupiah = (amount: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount)

function stagesOf(pkg: CatalogPackage) {
  return [
    pkg.include_recording && 'Recording',
    pkg.include_editing && 'Editing',
    pkg.include_mixing && 'Mixing',
    pkg.include_mastering && 'Mastering',
  ].filter(Boolean) as string[]
}

function PackagesSection() {
  const [state, setState] = useState<{ status: 'loading' | 'ready' | 'error'; packages: CatalogPackage[] }>({ status: 'loading', packages: [] })

  useEffect(() => {
    let active = true
    void fetch(`${apiOrigin}/api/v1/public/packages`, { headers: { Accept: 'application/json' } })
      .then(async (response) => {
        if (!response.ok) throw new Error()
        const result = await response.json() as { data: CatalogPackage[] }
        if (active) setState({ status: 'ready', packages: result.data ?? [] })
      })
      .catch(() => { if (active) setState({ status: 'error', packages: [] }) })
    return () => { active = false }
  }, [])

  return (
    <section aria-labelledby="paket" className="landing-section" id="paket-section">
      <p className="landing-eyebrow">Paket</p>
      <h2 id="paket">Paket yang tersedia</h2>
      <p className="landing-lead">Setiap paket sudah termasuk tahap pengerjaan di bawah ini. Butuh kombinasi lain? Ajukan penawaran khusus setelah masuk.</p>
      {state.status === 'loading' && <p role="status">Memuat paket…</p>}
      {state.status === 'error' && <p role="alert">Daftar paket belum dapat dimuat. Silakan coba lagi nanti atau hubungi kami.</p>}
      {state.status === 'ready' && state.packages.length === 0 && <p>Belum ada paket yang ditawarkan saat ini. Hubungi kami untuk penawaran khusus.</p>}
      {state.packages.length > 0 && (
        <div className="landing-grid landing-grid--packages">
          {state.packages.map((pkg) => (
            <article className="landing-card landing-package" key={pkg.id}>
              <h3>{pkg.name}</h3>
              <strong className="landing-package__price">{rupiah(pkg.price)}</strong>
              {pkg.description && <p>{pkg.description}</p>}
              <ul>
                <li><Icon name="music" size={16} /> {pkg.track_count} lagu</li>
                {pkg.include_recording && <li><Icon name="clock" size={16} /> {pkg.recording_hours} jam rekaman</li>}
                {stagesOf(pkg).map((stage) => <li key={stage}><Icon name="check" size={16} /> {stage}</li>)}
              </ul>
              <Link className="landing-button landing-button--outlined" to="/register">Pilih paket ini</Link>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}

export default function LandingPage() {
  const contactItems = [
    contact.email && { label: 'Email', value: contact.email, href: `mailto:${contact.email}` },
    contact.whatsapp && { label: 'WhatsApp', value: contact.whatsapp, href: `https://wa.me/${contact.whatsapp.replace(/\D/g, '')}` },
    contact.instagram && { label: 'Instagram', value: contact.instagram, href: `https://instagram.com/${contact.instagram.replace(/^@/, '')}` },
    contact.address && { label: 'Alamat', value: contact.address },
    contact.hours && { label: 'Jam operasional', value: contact.hours },
  ].filter(Boolean) as { label: string; value: string; href?: string }[]

  return (
    <div className="landing">
      <header className="landing-nav">
        <Link aria-label="Tungku Studio, beranda" to="/"><img alt="Tungku Studio" src={tungkuLogo} /></Link>
        <nav aria-label="Navigasi halaman">
          <a href="#layanan-section">Layanan</a>
          <a href="#paket-section">Paket</a>
          <a href="#portofolio-section">Portofolio</a>
          <a href="#kontak-section">Kontak</a>
        </nav>
        <Link className="landing-button" to="/login">Masuk</Link>
      </header>

      <main>
        <section className="landing-hero" aria-labelledby="profil">
          <p className="landing-eyebrow">{studioProfile.name}</p>
          <h1 id="profil">{studioProfile.tagline}</h1>
          <p className="landing-lead">{studioProfile.about}</p>
          <div className="landing-hero__actions">
            <Link className="landing-button" to="/register">Buat akun</Link>
            <a className="landing-button landing-button--outlined" href="#paket-section">Lihat paket</a>
          </div>
        </section>

        <section aria-labelledby="layanan" className="landing-section" id="layanan-section">
          <p className="landing-eyebrow">Layanan</p>
          <h2 id="layanan">Layanan yang kami tawarkan</h2>
          <div className="landing-grid">
            {services.map((service) => (
              <article className="landing-card" key={service.title}>
                <Icon className="landing-card__icon" name={service.icon as IconName} size={24} />
                <h3>{service.title}</h3>
                <p>{service.description}</p>
              </article>
            ))}
          </div>
        </section>

        <PackagesSection />

        <section aria-labelledby="alur" className="landing-section">
          <p className="landing-eyebrow">Cara kerja</p>
          <h2 id="alur">Apa yang kamu dapatkan di Tungku</h2>
          <ol className="landing-steps">
            {customerJourney.map((step, index) => (
              <li className="landing-card" key={step.title}>
                <span className="landing-steps__number">{index + 1}</span>
                <h3>{step.title}</h3>
                <p>{step.description}</p>
              </li>
            ))}
          </ol>
        </section>

        <section aria-labelledby="portofolio" className="landing-section" id="portofolio-section">
          <p className="landing-eyebrow">Portofolio</p>
          <h2 id="portofolio">Karya yang pernah kami kerjakan</h2>
          <div className="landing-grid">
            {portfolio.map((item) => (
              <article className="landing-card" key={item.title}>
                <span className="landing-card__meta">{item.year}</span>
                <h3>{item.title}</h3>
                <p>{item.client}</p>
                <small>{item.services}</small>
              </article>
            ))}
          </div>
        </section>

        <section aria-labelledby="kontak" className="landing-section landing-contact" id="kontak-section">
          <p className="landing-eyebrow">Kontak</p>
          <h2 id="kontak">Hubungi Studio Tungku</h2>
          <dl>
            {contactItems.map((item) => (
              <div key={item.label}>
                <dt>{item.label}</dt>
                <dd>{item.href ? <a href={item.href} rel="noreferrer" target={item.href.startsWith('http') ? '_blank' : undefined}>{item.value}</a> : item.value}</dd>
              </div>
            ))}
          </dl>
          <Link className="landing-button" to="/register">Mulai project</Link>
        </section>
      </main>

      <footer className="landing-footer">© Tungku Recording Studio 2026</footer>
    </div>
  )
}
