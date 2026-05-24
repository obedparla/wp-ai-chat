import { useEffect, useRef, useCallback, useState, Fragment, type ReactNode } from 'react'
import { Message } from '../hooks/useChat'
import ProductGrid from './ProductGrid'
import ComparisonTable from './ComparisonTable'
import CheckoutButton from './CheckoutButton'
import MarkdownContent from './MarkdownContent'
import { cn } from '@/lib/utils'

const JUMP_BUTTON_THRESHOLD = 100

interface MessageListProps {
  messages: Message[]
  onRetry?: () => void
  children?: ReactNode
}

const CLUSTER_GAP_MS = 5 * 60 * 1000

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

function formatTime(timestamp: number): string {
  return new Date(timestamp).toLocaleTimeString([], {
    hour: 'numeric',
    minute: '2-digit',
  })
}

function formatDayLabel(timestamp: number): string {
  const date = new Date(timestamp)
  const now = new Date()
  if (isSameDay(date, now)) return 'TODAY'
  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  if (isSameDay(date, yesterday)) return 'YESTERDAY'
  return date.toLocaleDateString([], { weekday: 'long' }).toUpperCase()
}

function shouldShowSeparator(current: Message, previous: Message | undefined): boolean {
  if (!current.createdAt) return false
  if (!previous) return true
  if (!previous.createdAt) return true
  const prev = new Date(previous.createdAt)
  const now = new Date(current.createdAt)
  if (!isSameDay(prev, now)) return true
  return current.createdAt - previous.createdAt > CLUSTER_GAP_MS
}

export default function MessageList({ messages, onRetry, children }: MessageListProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const isUserAtBottomRef = useRef(true)
  const [showJumpButton, setShowJumpButton] = useState(false)
  const lastMessageContent = messages[messages.length - 1]?.content

  const checkIfAtBottom = useCallback(() => {
    const container = containerRef.current
    if (!container) return true
    const threshold = 50
    return container.scrollHeight - container.scrollTop - container.clientHeight < threshold
  }, [])

  const handleScroll = useCallback(() => {
    const container = containerRef.current
    if (!container) return
    isUserAtBottomRef.current = checkIfAtBottom()
    const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight
    setShowJumpButton(distanceFromBottom > JUMP_BUTTON_THRESHOLD)
  }, [checkIfAtBottom])

  const scrollToLatest = useCallback(() => {
    const container = containerRef.current
    if (!container) return
    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' })
  }, [])

  useEffect(() => {
    if (containerRef.current && isUserAtBottomRef.current) {
      containerRef.current.scrollTop = containerRef.current.scrollHeight
      setShowJumpButton(false)
    }
  }, [messages.length, lastMessageContent])

  return (
    <div className="flex-1 relative flex min-h-0">
    <div
      className="flex-1 overflow-y-auto p-5 flex flex-col gap-3 bg-white scroll-smooth overscroll-contain max-[480px]:p-4 max-[480px]:gap-2.5 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-slate-200 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-300"
      ref={containerRef}
      onScroll={handleScroll}
    >
      {messages.map((msg, i) => {
        const isLastMessage = i === messages.length - 1
        const showRetry = msg.isError && isLastMessage && onRetry

        const products = msg.products ?? []
        const comparison = msg.comparison
        const checkoutAction = msg.checkoutAction
        const hasProducts = products.length > 0
        const hasComparison = comparison !== undefined
        const hasCheckoutAction = checkoutAction !== undefined
        const hasToolUI = hasProducts || hasComparison || hasCheckoutAction
        const hasTextContent = msg.content && msg.content.trim().length > 0
        const showSeparator = shouldShowSeparator(msg, messages[i - 1])

        const separator = showSeparator && msg.createdAt ? (
          <div className="flex items-center gap-3 my-2 px-2 self-stretch" aria-hidden>
            <div className="flex-1 h-px bg-slate-200" />
            <span className="text-[10px] font-mono tracking-[0.18em] text-slate-500">
              {formatDayLabel(msg.createdAt)} <span className="opacity-50">·</span> {formatTime(msg.createdAt).toUpperCase()}
            </span>
            <div className="flex-1 h-px bg-slate-200" />
          </div>
        ) : null

        // User messages: always show bubble
        if (msg.role === 'user') {
          return (
            <Fragment key={msg.id ?? i}>
              {separator}
              <div
                className={cn(
                  'max-w-[85%] py-2.5 px-4 rounded-2xl leading-relaxed break-words text-sm tracking-tight animate-wpaic-fadeIn relative rounded-br-md',
                  'self-end bg-[var(--wpaic-primary)] text-white',
                  'max-[480px]:max-w-[90%] max-[480px]:py-2.5 max-[480px]:px-3.5 max-[480px]:text-[15px]',
                  msg.isError && 'bg-red-50 text-red-800 border border-red-200'
                )}
              >
                <span className="whitespace-pre-wrap">{msg.content}</span>
              </div>
            </Fragment>
          )
        }

        // Assistant messages: text in bubble, tool UI outside
        return (
          <Fragment key={msg.id ?? i}>
            {separator}
            {hasTextContent && (
              <div
                className={cn(
                  'max-w-[85%] py-3 px-4 rounded-2xl leading-relaxed break-words text-sm tracking-tight animate-wpaic-fadeIn relative rounded-bl-md',
                  'self-start bg-slate-100 text-slate-800',
                  'max-[480px]:max-w-[90%] max-[480px]:py-2.5 max-[480px]:px-3.5 max-[480px]:text-[15px]',
                  msg.isError && 'bg-red-50 text-red-800 border border-red-200'
                )}
              >
                <div className="prose prose-sm prose-slate max-w-none prose-p:my-2 prose-p:last:mb-0 prose-a:text-[var(--wpaic-primary)] prose-a:no-underline hover:prose-a:underline">
                  <MarkdownContent content={msg.content} />
                </div>
                {showRetry && !hasToolUI && (
                  <button
                    className="inline-flex items-center justify-center ml-2.5 w-7 h-7 p-0 bg-red-50 border border-red-200 rounded-full text-red-600 text-sm cursor-pointer transition-all duration-200 align-middle hover:bg-red-600 hover:border-red-600 hover:text-white hover:rotate-180"
                    onClick={onRetry}
                    aria-label="Retry"
                    title="Retry"
                  >
                    ↻
                  </button>
                )}
              </div>
            )}
            {hasProducts && (
              <div className="w-full animate-wpaic-fadeIn">
                <ProductGrid products={products} />
              </div>
            )}
            {comparison && (
              <div className="w-full animate-wpaic-fadeIn">
                <ComparisonTable data={comparison} />
              </div>
            )}
            {checkoutAction && <CheckoutButton action={checkoutAction} />}
            {showRetry && hasToolUI && (
              <button
                className="inline-flex items-center justify-center w-7 h-7 p-0 bg-red-50 border border-red-200 rounded-full text-red-600 text-sm cursor-pointer transition-all duration-200 self-start -mt-2 hover:bg-red-600 hover:border-red-600 hover:text-white hover:rotate-180"
                onClick={onRetry}
                aria-label="Retry"
                title="Retry"
              >
                ↻
              </button>
            )}
          </Fragment>
        )
      })}
      {children}
    </div>
    {showJumpButton && (
      <button
        type="button"
        onClick={scrollToLatest}
        aria-label="Jump to latest message"
        className="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 inline-flex items-center gap-1.5 py-1.5 px-3.5 rounded-full bg-[var(--wpaic-primary)] text-white text-xs font-medium shadow-md shadow-slate-900/15 hover:shadow-lg hover:brightness-110 transition-[box-shadow,filter] duration-200 animate-wpaic-fadeIn focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--wpaic-primary)]"
      >
        <span aria-hidden>↓</span>
        <span>Jump to latest</span>
      </button>
    )}
    </div>
  )
}
