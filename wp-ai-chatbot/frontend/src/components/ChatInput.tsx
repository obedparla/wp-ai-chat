import { forwardRef, useRef, useEffect, useCallback } from 'react'
import { cn } from '@/lib/utils'

interface ChatInputProps {
  value: string
  onChange: (value: string) => void
  onSubmit: (e: React.FormEvent) => void
  isLoading: boolean
  onStop: () => void
}

const MAX_HEIGHT = 130

const ChatInput = forwardRef<HTMLTextAreaElement, ChatInputProps>(function ChatInput({
  value,
  onChange,
  onSubmit,
  isLoading,
  onStop,
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
      if (value.trim() && !isLoading) {
        onSubmit(e as unknown as React.FormEvent)
      }
    }
  }

  return (
    <form
      className="flex p-4 bg-white border-t border-slate-200 gap-2.5 items-end max-[480px]:p-3.5 max-[480px]:pb-[max(14px,env(safe-area-inset-bottom))]"
      onSubmit={onSubmit}
    >
      <textarea
        ref={setRefs}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder="Type a message..."
        disabled={isLoading}
        rows={1}
        className={cn(
          'flex-1 py-3 px-[18px] border-2 border-slate-200 rounded-2xl outline-none text-sm resize-none',
          'bg-slate-50 text-slate-800 transition-[border-color,background-color,box-shadow] duration-200',
          'placeholder:text-slate-500',
          'focus:border-[var(--wpaic-primary)] focus:bg-white focus:shadow-[0_0_0_4px_rgba(0,115,170,0.1)]',
          'disabled:opacity-60 disabled:cursor-not-allowed',
          'max-[480px]:py-3.5 max-[480px]:px-4 max-[480px]:text-base'
        )}
      />
      {isLoading ? (
        <button
          type="button"
          onClick={onStop}
          className="py-3 px-5 bg-gradient-to-br from-red-500 to-red-600 text-white border-0 rounded-full cursor-pointer font-semibold text-sm transition-all duration-200 shadow-sm hover:scale-105 hover:shadow-md active:scale-95 max-[480px]:py-3.5 max-[480px]:px-[18px]"
          aria-label="Stop"
        >
          Stop
        </button>
      ) : (
        <button
          type="submit"
          disabled={!value.trim()}
          className={cn(
            'py-3 px-5 bg-[var(--wpaic-primary)] text-white border-0 rounded-full cursor-pointer font-semibold text-sm transition-all duration-200 shadow-sm',
            'max-[480px]:py-3.5 max-[480px]:px-[18px]',
            'disabled:bg-slate-200 disabled:text-slate-500 disabled:cursor-not-allowed disabled:shadow-none',
            'enabled:hover:scale-105 enabled:hover:shadow-md enabled:active:scale-95'
          )}
        >
          Send
        </button>
      )}
    </form>
  )
})

export default ChatInput
