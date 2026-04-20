import { forwardRef, useRef, useEffect, useCallback } from 'react'
import { cn } from '@/lib/utils'

interface ChatInputProps {
  value: string
  onChange: (value: string) => void
  onSubmit: (e: React.FormEvent) => void
  placeholder?: string
}

const MAX_HEIGHT = 130

const ChatInput = forwardRef<HTMLTextAreaElement, ChatInputProps>(function ChatInput({
  value,
  onChange,
  onSubmit,
  placeholder = 'Type a message...',
}, ref) {
  const internalRef = useRef<HTMLTextAreaElement | null>(null)

  const setRefs = useCallback((node: HTMLTextAreaElement | null) => {
    internalRef.current = node
    if (typeof ref === 'function') ref(node)
    else if (ref) Object.assign(ref, { current: node })
  }, [ref])

  useEffect(() => {
    const textarea = internalRef.current
    if (!textarea) return
    textarea.style.height = 'auto'
    const clamped = Math.min(textarea.scrollHeight, MAX_HEIGHT)
    textarea.style.height = `${clamped}px`
    textarea.style.overflowY = textarea.scrollHeight > MAX_HEIGHT ? 'auto' : 'hidden'
  }, [value])

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      if (value.trim()) {
        onSubmit(e as unknown as React.FormEvent)
      }
    }
  }

  const isEmpty = !value.trim()

  return (
    <form
      className="p-4 bg-white border-t border-slate-100 max-[480px]:p-3.5 max-[480px]:pb-[max(14px,env(safe-area-inset-bottom))]"
      onSubmit={onSubmit}
    >
      <div className="relative flex items-end bg-slate-100 rounded-full pl-5 pr-1.5 py-1.5 transition-[background-color,box-shadow] duration-200 focus-within:bg-slate-50 focus-within:shadow-[0_0_0_2px_rgba(45,49,146,0.12)]">
        <textarea
          ref={setRefs}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          rows={1}
          className={cn(
            'flex-1 bg-transparent border-0 outline-none text-sm resize-none py-2 pr-2 m-0',
            'text-slate-800 placeholder:text-slate-500 leading-snug',
            '!min-h-0 !max-h-none',
            'max-[480px]:text-base'
          )}
        />
        <button
          type="submit"
          disabled={isEmpty}
          className={cn(
            'shrink-0 w-9 h-9 rounded-full border-0 flex items-center justify-center cursor-pointer transition-all duration-200 mb-0.5',
            isEmpty
              ? 'bg-[var(--wpaic-primary)]/25 text-white cursor-not-allowed'
              : 'bg-[var(--wpaic-primary)] text-white hover:scale-105 active:scale-95 shadow-sm'
          )}
          aria-label="Send"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.25"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="w-4 h-4"
          >
            <path d="M12 19V5" />
            <path d="m5 12 7-7 7 7" />
          </svg>
        </button>
      </div>
    </form>
  )
})

export default ChatInput
