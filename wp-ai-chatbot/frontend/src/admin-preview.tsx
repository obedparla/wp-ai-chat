import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import AdminPreview from './AdminPreview'
import './styles.css'

const root = document.getElementById('wpaic-admin-preview')
if (root) {
  const variant = root.dataset.previewVariant === 'teaser' ? 'teaser' : 'widget'
  createRoot(root).render(
    <StrictMode>
      <AdminPreview variant={variant} />
    </StrictMode>
  )
}
