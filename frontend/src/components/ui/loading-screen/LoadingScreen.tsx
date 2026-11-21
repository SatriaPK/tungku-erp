import tungkuIcon from '../../../assets/tungku-icon.svg'
import './LoadingScreen.css'

export interface LoadingScreenProps {
  label?: string
}

export function LoadingScreen({ label = 'Memuat halaman' }: LoadingScreenProps) {
  return (
    <div aria-busy="true" aria-label={label} className="ts-loading-screen" role="status">
      <div aria-hidden="true" className="ts-loading-mark">
        <img alt="" className="ts-loading-mark__base" src={tungkuIcon} />
        <img alt="" className="ts-loading-mark__fill" src={tungkuIcon} />
      </div>
      <span className="ts-loading-screen__label">{label}</span>
    </div>
  )
}