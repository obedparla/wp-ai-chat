import { useEffect, useRef, useCallback, Fragment } from 'react'
import { Message } from '../hooks/useChat'
import ProductGrid from './ProductGrid'
import ComparisonTable from './ComparisonTable'
import MarkdownContent from './MarkdownContent'
import { cn } from '@/lib/utils'

interface MessageListProps {
  messages: Message[]
  onRetry?: () => void
}

export default function MessageList({ messages, onRetry }: MessageListProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const isUserAtBottomRef = useRef(true)
  const lastMessageContent = messages[messages.length - 1]?.content

  const checkIfAtBottom = useCallback(() => {
    const container = containerRef.current
    if (!container) return true
    const threshold = 50
    return container.scrollHeight - container.scrollTop - container.clientHeight < threshold
  }, [])

  const handleScroll = useCallback(() => {
    isUserAtBottomRef.current = checkIfAtBottom()
  }, [checkIfAtBottom])

  useEffect(() => {
    if (containerRef.current && isUserAtBottomRef.current) {
      containerRef.current.scrollTop = containerRef.current.scrollHeight
    }
  }, [messages.length, lastMessageContent])

  return (
    <div
      className="flex-1 overflow-y-auto p-5 flex flex-col gap-4 bg-slate-50 scroll-smooth overscroll-contain max-[480px]:p-4 max-[480px]:gap-3.5 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-slate-200 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-slate-300"
      ref={containerRef}
      onScroll={handleScroll}
    >
      {messages.map((msg, i) => {
        const isLastMessage = i === messages.length - 1
        const showRetry = msg.isError && isLastMessage && onRetry

        const hasProducts = msg.products && msg.products.length > 0
        const hasComparison = msg.comparison
        const hasToolUI = hasProducts || hasComparison
        const hasTextContent = msg.content && msg.content.trim().length > 0

        // User messages: always show bubble
        if (msg.role === 'user') {
          return (
            <div
              key={msg.id ?? i}
              className={cn(
                'max-w-[85%] py-3 px-4 rounded-2xl leading-relaxed break-words text-sm tracking-tight animate-wpaic-fadeIn relative rounded-br-sm',
                'self-end bg-[var(--wpaic-primary)] text-white shadow-sm',
                'max-[480px]:max-w-[90%] max-[480px]:py-2.5 max-[480px]:px-3.5 max-[480px]:text-[15px]',
                msg.isError && 'bg-red-50 text-red-800 border border-red-200 shadow-none'
              )}
            >
              <span className="whitespace-pre-wrap">{msg.content}</span>
            </div>
          )
        }

        // Assistant messages: text in bubble, tool UI outside
        return (
          <Fragment key={msg.id ?? i}>
            {hasTextContent && (
              <div
                className={cn(
                  'max-w-[85%] py-3 px-4 rounded-2xl leading-relaxed break-words text-sm tracking-tight animate-wpaic-fadeIn relative rounded-bl-sm',
                  'self-start bg-white text-slate-800 shadow-md border border-slate-200',
                  'max-[480px]:max-w-[90%] max-[480px]:py-2.5 max-[480px]:px-3.5 max-[480px]:text-[15px]',
                  msg.isError && 'bg-red-50 text-red-800 border border-red-200 shadow-none'
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
                <ProductGrid products={msg.products!} />
              </div>
            )}
            {hasComparison && (
              <div className="w-full animate-wpaic-fadeIn">
                <ComparisonTable data={msg.comparison!} />
              </div>
            )}
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
    </div>
  )
}
