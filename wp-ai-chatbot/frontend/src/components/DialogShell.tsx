import { useEffect, type ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { useFocusTrap } from '../hooks/useFocusTrap'

interface DialogShellProps {
  onClose: () => void
  /** 'widget' overlays the chat panel; 'screen' overlays the whole page. */
  variant?: 'widget' | 'screen'
  panelClassName: string
  ariaLabelledby: string
  children: ReactNode
}

const OVERLAY_VARIANTS = {
  widget: 'absolute inset-0 z-10 bg-slate-900/40 rounded-2xl',
  screen: 'fixed inset-0 z-[9999] bg-slate-900/50 p-4',
}

/**
 * The modal chrome every widget dialog shares: backdrop with click-outside
 * close, panel click containment, focus trap, and Escape-to-close. The Escape
 * listener is document-level in the CAPTURE phase so it runs before the
 * widget's document-level (bubble) Escape handler — an open dialog always
 * consumes Escape instead of closing the whole chat.
 */
export default function DialogShell({
  onClose,
  variant = 'widget',
  panelClassName,
  ariaLabelledby,
  children,
}: DialogShellProps) {
  const dialogRef = useFocusTrap<HTMLDivElement>()

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return
      e.stopPropagation()
      onClose()
    }
    document.addEventListener('keydown', handleKeyDown, true)
    return () => document.removeEventListener('keydown', handleKeyDown, true)
  }, [onClose])

  return (
    <div
      ref={dialogRef}
      className={cn('flex items-center justify-center animate-wpaic-fadeIn', OVERLAY_VARIANTS[variant])}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby={ariaLabelledby}
    >
      <div
        className={cn('bg-white rounded-2xl shadow-2xl', panelClassName)}
        onClick={(e) => e.stopPropagation()}
      >
        {children}
      </div>
    </div>
  )
}
