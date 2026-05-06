import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import AdminPreview from './AdminPreview'
import './styles.css'

const root = document.getElementById('wpaic-admin-preview')
if (root) {
  createRoot(root).render(
    <StrictMode>
      <AdminPreview />
    </StrictMode>
  )
}
