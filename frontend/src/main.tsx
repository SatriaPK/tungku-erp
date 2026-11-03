import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './app/App'
import './app/App.css'
import './styles/global.css'
import './components/ui/styles.css'
import './components/ui/loading-screen/LoadingScreen.css'

if (import.meta.env.DEV && window.location.hostname === '127.0.0.1' && window.location.port === '5175') {
  const url = new URL(window.location.href)
  url.hostname = 'localhost'
  window.location.replace(url.href)
} else {
  const rootElement = document.getElementById('root')

  if (!rootElement) {
    throw new Error('Root element #root was not found')
  }

  createRoot(rootElement).render(
    <StrictMode>
      <App />
    </StrictMode>,
  )
}
