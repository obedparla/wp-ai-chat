import { cn } from '@/lib/utils'

interface ChatButtonProps {
  onClick: () => void
  isOpen: boolean
}

export default function ChatButton({ onClick, isOpen }: ChatButtonProps) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'fixed bottom-6 right-6 w-[60px] h-[60px] rounded-full border-0',
        'bg-[var(--wpaic-primary)] text-white cursor-pointer',
        'shadow-lg flex items-center justify-center z-[9999]',
        'transition-all duration-200 ease-out',
        'hover:scale-[1.08] hover:shadow-xl',
        'active:scale-95',
        'animate-wpaic-pulse hover:animate-none',
        'max-[480px]:bottom-5 max-[480px]:right-5 max-[480px]:w-14 max-[480px]:h-14',
        isOpen && 'max-[480px]:hidden'
      )}
      aria-label={isOpen ? 'Close chat' : 'Open chat'}
    >
      {isOpen ? (
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          className="transition-transform duration-200 hover:rotate-[10deg]"
        >
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      ) : (
        <svg
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          className="transition-transform duration-200 hover:rotate-[10deg]"
        >
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
      )}
    </button>
  )
}
