import { useState, useEffect, useCallback, useRef } from 'react'
import type { Message, ActiveTool } from '../hooks/useChat'
import { useClearCart } from '../hooks/useClearCart'
import ChatWidgetUI from './ChatWidgetUI'
import SendTranscriptDialog from './SendTranscriptDialog'
import ConfirmDialog from './ConfirmDialog'
import ConversationStarters from './ConversationStarters'
import { TOOL_PROGRESS_LABELS, showsProductSkeletons } from '../hooks/tools'

interface ChatWidgetProps {
  onClose: () => void
  chat: {
    messages: Message[]
    sendMessage: (content: string) => void
    isLoading: boolean
    startNewConversation: () => void
    activeTools: ActiveTool[]
    retry: () => void
  }
  chatbotName?: string
  chatbotLogo?: string
  chatbotRole?: string
  conversationStarters?: string[]
  autoFocusInput?: boolean
}

function getToolProgressMessage(tool: ActiveTool): string {
  return TOOL_PROGRESS_LABELS[tool.toolName] ?? 'Working on it…'
}

// Matches the max-[480px] fullscreen breakpoint in the widget container classes.
const FULLSCREEN_MEDIA_QUERY = '(max-width: 480px)'

export default function ChatWidget({
  onClose,
  chat,
  chatbotName,
  chatbotLogo,
  chatbotRole,
  conversationStarters = [],
  autoFocusInput = false,
}: ChatWidgetProps) {
  const { messages, sendMessage, isLoading, startNewConversation, activeTools, retry } = chat
  const [input, setInput] = useState('')
  const [showTranscriptDialog, setShowTranscriptDialog] = useState(false)
  const [showNewConversationDialog, setShowNewConversationDialog] = useState(false)
  const clearCart = useClearCart(messages)
  const inputRef = useRef<HTMLTextAreaElement>(null)
  const [adminBarOffset, setAdminBarOffset] = useState(0)

  // Fullscreen mobile: the WP admin bar (z-index 99999) overlays the widget
  // header for logged-in users — offset the widget below its visible part.
  useEffect(() => {
    const adminBar = document.getElementById('wpadminbar')
    if (!adminBar) return

    const updateAdminBarOffset = () =>
      setAdminBarOffset(Math.max(0, adminBar.getBoundingClientRect().bottom))

    updateAdminBarOffset()
    window.addEventListener('resize', updateAdminBarOffset)
    return () => window.removeEventListener('resize', updateAdminBarOffset)
  }, [])

  // Lock body scroll while the widget is fullscreen so the page behind
  // does not scroll; restore the previous overflow on close.
  useEffect(() => {
    const mediaQuery = window.matchMedia(FULLSCREEN_MEDIA_QUERY)
    let savedOverflow: { html: string; body: string } | null = null

    const lockBodyScroll = () => {
      if (savedOverflow !== null) return
      savedOverflow = {
        html: document.documentElement.style.overflow,
        body: document.body.style.overflow,
      }
      document.documentElement.style.overflow = 'hidden'
      document.body.style.overflow = 'hidden'
    }

    const unlockBodyScroll = () => {
      if (savedOverflow === null) return
      document.documentElement.style.overflow = savedOverflow.html
      document.body.style.overflow = savedOverflow.body
      savedOverflow = null
    }

    const syncBodyScrollLock = () => {
      if (mediaQuery.matches) {
        lockBodyScroll()
      } else {
        unlockBodyScroll()
      }
    }

    syncBodyScrollLock()
    mediaQuery.addEventListener('change', syncBodyScrollLock)
    return () => {
      mediaQuery.removeEventListener('change', syncBodyScrollLock)
      unlockBodyScroll()
    }
  }, [])

  const showProductSkeletons =
    isLoading && activeTools.some((tool) => showsProductSkeletons(tool.toolName))

  const isGreetingOnly =
    messages.length === 0 ||
    (messages.length === 1 && messages[0].role === 'assistant' && messages[0].id === 'greeting')
  const showConversationStarters =
    !isLoading && conversationStarters.length > 0 && isGreetingOnly

  // Escape closes the widget. Open dialogs are not special-cased here: every
  // dialog renders through DialogShell, whose capture-phase document listener
  // consumes Escape (stopPropagation) before this bubble listener runs.
  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return
      onClose()
    },
    [onClose]
  )

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [handleKeyDown])

  useEffect(() => {
    if (!autoFocusInput) return

    const focusInput = () => inputRef.current?.focus()
    focusInput()
    const timeoutId = window.setTimeout(focusInput, 0)

    return () => window.clearTimeout(timeoutId)
  }, [autoFocusInput])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!input.trim()) return
    sendMessage(input.trim())
    setInput('')
    requestAnimationFrame(() => inputRef.current?.focus())
  }

  const handleStarterSelect = useCallback(
    (starter: string) => {
      sendMessage(starter)
      requestAnimationFrame(() => inputRef.current?.focus())
    },
    [sendMessage]
  )

  const iconButtonClass =
    'bg-transparent border-0 text-white cursor-pointer leading-none p-2 rounded-lg opacity-85 transition-all duration-200 flex items-center justify-center hover:opacity-100 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white focus-visible:opacity-100'

  const headerActions = (
    <>
      <button
        onClick={() => {
          if (isGreetingOnly) {
            startNewConversation()
          } else {
            setShowNewConversationDialog(true)
          }
        }}
        className={iconButtonClass}
        aria-label="New conversation"
        title="New conversation"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-[18px] h-[18px]">
          <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
          <path d="M21 3v5h-5" />
          <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
          <path d="M3 21v-5h5" />
        </svg>
      </button>
      <button
        onClick={() => setShowTranscriptDialog(true)}
        className={iconButtonClass}
        aria-label="Send transcript"
        title="Send transcript"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-[18px] h-[18px]">
          <rect width="20" height="16" x="2" y="4" rx="2" />
          <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
        </svg>
      </button>
      <button
        onClick={onClose}
        className={iconButtonClass}
        aria-label="Close"
      >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="w-[18px] h-[18px]">
          <path d="M18 6 6 18" />
          <path d="m6 6 12 12" />
        </svg>
      </button>
    </>
  )

  return (
    <div
      className="fixed bottom-6 right-6 w-[428px] max-w-[calc(100vw-48px)] h-[680px] max-h-[calc(100vh-48px)] z-[9998] animate-wpaic-slideUp max-[480px]:bottom-0 max-[480px]:right-0 max-[480px]:left-0 max-[480px]:w-full max-[480px]:max-w-full max-[480px]:h-[calc(100dvh-var(--wpaic-mobile-top-offset,0px))] max-[480px]:max-h-[calc(100dvh-var(--wpaic-mobile-top-offset,0px))] max-[480px]:rounded-none max-[480px]:animate-wpaic-slideUpMobile"
      style={{ '--wpaic-mobile-top-offset': `calc(${adminBarOffset}px + env(safe-area-inset-top, 0px))` } as React.CSSProperties}
    >
      {showTranscriptDialog && (
        <SendTranscriptDialog
          messages={messages}
          onClose={() => setShowTranscriptDialog(false)}
        />
      )}
      {showNewConversationDialog && (
        <ConfirmDialog
          title="Start new conversation?"
          description="I'll clear everything we've talked about and start fresh."
          confirmLabel="Start new"
          onConfirm={() => {
            setShowNewConversationDialog(false)
            startNewConversation()
          }}
          onCancel={() => setShowNewConversationDialog(false)}
        />
      )}
      {clearCart.pendingDialog && (
        <ConfirmDialog
          title={clearCart.pendingDialog.title}
          description={clearCart.pendingDialog.description}
          confirmLabel={clearCart.pendingDialog.confirmLabel}
          onConfirm={clearCart.confirm}
          onCancel={clearCart.cancel}
          destructive
        />
      )}
      <ChatWidgetUI
        messages={messages}
        chatbotName={chatbotName}
        chatbotLogo={chatbotLogo}
        chatbotRole={chatbotRole}
        input={input}
        onInputChange={setInput}
        onSubmit={handleSubmit}
        onRetry={retry}
        clearCartStatuses={clearCart.statuses}
        showProductSkeletons={showProductSkeletons}
        inputRef={inputRef}
        headerActions={headerActions}
        loadingIndicator={
          isLoading && activeTools.length > 0 ? (
            <div className="py-3 px-5 flex flex-col gap-2 bg-white border-t border-slate-100">
              {activeTools.map((tool, i) => (
                <div
                  key={`${tool.toolName}-${i}`}
                  className="flex items-center gap-2.5 text-[var(--wpaic-primary)] text-[13px] font-medium"
                >
                  <span className="w-4 h-4 border-2 border-slate-200 border-t-[var(--wpaic-primary)] rounded-full animate-spin" />
                  {getToolProgressMessage(tool)}
                </div>
              ))}
            </div>
          ) : isLoading ? (
            <div className="py-3 px-5 text-slate-500 text-[13px] flex items-center gap-2 bg-white border-t border-slate-100 before:content-[''] before:w-2 before:h-2 before:bg-[var(--wpaic-primary)] before:rounded-full before:animate-bounce">
              Typing...
            </div>
          ) : null
        }
      >
        {showConversationStarters && (
          <ConversationStarters
            starters={conversationStarters}
            onSelect={handleStarterSelect}
          />
        )}
      </ChatWidgetUI>
    </div>
  )
}
