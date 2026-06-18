import { useEffect, useRef } from 'react'
import { cn } from '@/lib/utils'
import DialogShell from './DialogShell'

interface ConfirmDialogProps {
  title: string
  description: string
  confirmLabel: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
  destructive?: boolean
}

export default function ConfirmDialog({
  title,
  description,
  confirmLabel,
  cancelLabel = 'Cancel',
  onConfirm,
  onCancel,
  destructive = false,
}: ConfirmDialogProps) {
  const cancelRef = useRef<HTMLButtonElement>(null)
  const confirmRef = useRef<HTMLButtonElement>(null)

  // Focus confirm so Enter confirms; for destructive actions focus the safe
  // cancel instead, so Enter can't trigger the destructive action by accident.
  useEffect(() => {
    if (destructive) {
      cancelRef.current?.focus()
    } else {
      confirmRef.current?.focus()
    }
  }, [destructive])

  return (
    <DialogShell
      onClose={onCancel}
      panelClassName="mx-5 w-full max-w-[360px] p-6"
      ariaLabelledby="wpaic-confirm-title"
    >
      <h2
        id="wpaic-confirm-title"
        className="text-xl font-semibold text-slate-900 leading-tight mb-3"
      >
        {title}
      </h2>
      <p className="text-sm text-slate-600 leading-relaxed mb-6">{description}</p>
      <div className="flex items-center justify-end gap-2">
        <button
          ref={cancelRef}
          type="button"
          onClick={onCancel}
          className="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold tracking-[0.12em] text-slate-700 transition-colors duration-200 hover:border-slate-400 hover:bg-slate-50"
        >
          {cancelLabel.toUpperCase()}
        </button>
        <button
          ref={confirmRef}
          type="button"
          onClick={onConfirm}
          className={cn(
            'rounded-full border-0 px-5 py-2.5 text-xs font-semibold tracking-[0.12em] text-white transition-all duration-200 hover:scale-[1.03] active:scale-95 shadow-sm',
            destructive ? 'bg-red-600 hover:bg-red-700' : 'bg-[var(--wpaic-primary)]'
          )}
        >
          {confirmLabel.toUpperCase()}
        </button>
      </div>
    </DialogShell>
  )
}
