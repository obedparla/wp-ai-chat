import { cn } from '@/lib/utils'

interface ProactiveTeaserProps {
  message: string
  onOpen: () => void
  onDismiss: () => void
  className?: string
}

// Dismissible message-preview bubble shown next to the launcher instead of
// auto-opening the full widget. Clicking the bubble expands the real chat.
export default function ProactiveTeaser({ message, onOpen, onDismiss, className }: ProactiveTeaserProps) {
  return (
    <div
      className={cn(
        'fixed bottom-[96px] right-6 z-[9998] max-w-[300px] animate-wpaic-slideUp',
        'max-[480px]:bottom-[88px] max-[480px]:right-5 max-[480px]:max-w-[calc(100vw-80px)]',
        className
      )}
    >
      <button
        type="button"
        onClick={onOpen}
        className="block w-full text-left bg-white border border-slate-200 rounded-2xl rounded-br-md shadow-xl py-3 px-4 text-sm leading-relaxed tracking-tight text-slate-800 cursor-pointer transition-shadow duration-200 hover:shadow-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--wpaic-primary)]"
      >
        {message}
      </button>
      <button
        type="button"
        onClick={onDismiss}
        aria-label="Dismiss message"
        title="Dismiss"
        className="absolute -top-2.5 -left-2.5 w-6 h-6 rounded-full bg-white border border-slate-200 shadow-md text-slate-500 flex items-center justify-center cursor-pointer transition-colors duration-200 hover:text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--wpaic-primary)]"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <path d="M18 6 6 18" />
          <path d="m6 6 12 12" />
        </svg>
      </button>
    </div>
  )
}
