import { useState, useRef, useEffect } from 'react'
import type { Message } from '../hooks/useChat'
import { useFocusTrap } from '../hooks/useFocusTrap'

interface SendTranscriptDialogProps {
  messages: Message[]
  onClose: () => void
}

function formatTranscript(messages: Message[]): string {
  return messages
    .filter((message) => message.content.trim().length > 0)
    .map((message) => {
      const label = message.role === 'user' ? 'You' : 'Assistant'
      return `${label}: ${message.content}`
    })
    .join('\n\n')
}

type DialogState = 'input' | 'sending' | 'success'

export default function SendTranscriptDialog({ messages, onClose }: SendTranscriptDialogProps) {
  const [email, setEmail] = useState('')
  const [dialogState, setDialogState] = useState<DialogState>('input')
  const [error, setError] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)
  const dialogRef = useFocusTrap<HTMLDivElement>()
  const config = window.wpaicConfig

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    if (dialogState !== 'success') return
    const timer = setTimeout(onClose, 5000)
    return () => clearTimeout(timer)
  }, [dialogState, onClose])

  const handleSend = async () => {
    const trimmed = email.trim()
    if (!trimmed) {
      setError('Please enter an email address.')
      return
    }

    setError('')
    setDialogState('sending')

    try {
      const response = await fetch(`${config?.apiUrl}/send-transcript`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config?.nonce || '',
        },
        body: JSON.stringify({
          email: trimmed,
          transcript: formatTranscript(messages),
        }),
      })

      if (!response.ok) {
        const data = await response.json()
        throw new Error(data?.message || 'Failed to send email.')
      }

      setDialogState('success')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send email.')
      setDialogState('input')
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && dialogState === 'input') {
      e.preventDefault()
      handleSend()
    }
    if (e.key === 'Escape') {
      e.stopPropagation()
      onClose()
    }
  }

  return (
    <div
      ref={dialogRef}
      className="absolute inset-0 z-10 flex items-center justify-center bg-slate-900/40 rounded-2xl animate-wpaic-fadeIn"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="wpaic-transcript-title"
    >
      <div
        className="bg-white rounded-2xl shadow-2xl mx-5 w-full max-w-[360px] p-6"
        onClick={(e) => e.stopPropagation()}
        onKeyDown={handleKeyDown}
      >
        {dialogState === 'success' ? (
          <div className="text-center py-2">
            <div className="text-[var(--wpaic-primary)] mb-2">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-8 h-8 mx-auto">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
            </div>
            <p className="text-sm text-slate-700 font-medium">We've sent you the transcript over email.</p>
          </div>
        ) : (
          <>
            <h2
              id="wpaic-transcript-title"
              className="text-xl font-semibold text-slate-900 leading-tight mb-3"
            >
              Email me this chat
            </h2>
            <p className="text-sm text-slate-600 leading-relaxed mb-5">
              We'll send you an email of the conversation.
            </p>
            <input
              ref={inputRef}
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              disabled={dialogState === 'sending'}
              className="w-full px-4 py-3 text-sm bg-slate-50 border border-[var(--wpaic-primary)] rounded-xl focus:outline-none focus:ring-2 focus:ring-[var(--wpaic-primary)] focus:border-transparent disabled:opacity-50"
            />
            {error && <p className="text-xs text-red-500 mt-2">{error}</p>}
            <div className="flex items-center justify-end gap-2 mt-5">
              <button
                type="button"
                onClick={onClose}
                disabled={dialogState === 'sending'}
                className="rounded-full border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold tracking-[0.12em] text-slate-700 transition-colors duration-200 hover:border-slate-400 hover:bg-slate-50 disabled:opacity-50"
              >
                CANCEL
              </button>
              <button
                type="button"
                onClick={handleSend}
                disabled={dialogState === 'sending'}
                className="rounded-full border-0 bg-[var(--wpaic-primary)] px-5 py-2.5 text-xs font-semibold tracking-[0.12em] text-white transition-all duration-200 hover:scale-[1.03] active:scale-95 shadow-sm disabled:opacity-50 disabled:hover:scale-100"
              >
                {dialogState === 'sending' ? 'SENDING...' : 'SEND'}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
