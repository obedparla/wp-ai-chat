import { useState, useRef, useEffect } from 'react'
import type { Message } from '../hooks/useChat'

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
    <div className="absolute inset-0 z-10 flex items-center justify-center bg-black/30 rounded-2xl">
      <div className="bg-white rounded-xl shadow-lg mx-5 w-full max-w-[320px] p-5" onKeyDown={handleKeyDown}>
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
            <h3 className="text-sm font-semibold text-slate-800 mb-3">Send transcript</h3>
            <input
              ref={inputRef}
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="your@email.com"
              disabled={dialogState === 'sending'}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--wpaic-primary)] focus:border-transparent disabled:opacity-50"
            />
            {error && <p className="text-xs text-red-500 mt-1">{error}</p>}
            <div className="flex gap-2 mt-3">
              <button
                onClick={onClose}
                disabled={dialogState === 'sending'}
                className="flex-1 px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                onClick={handleSend}
                disabled={dialogState === 'sending'}
                className="flex-1 px-3 py-2 text-sm text-white bg-[var(--wpaic-primary)] rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50"
              >
                {dialogState === 'sending' ? 'Sending...' : 'Send transcript'}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
