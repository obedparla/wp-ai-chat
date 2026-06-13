import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import AnalyticsApp from './components/analytics/AnalyticsApp'
import './admin-analytics.css'

const root = document.getElementById('wpaic-analytics-root')
const data = window.wpaicAnalytics

if (root && data) {
  createRoot(root).render(
    <StrictMode>
      <AnalyticsApp data={data} />
    </StrictMode>
  )
}
