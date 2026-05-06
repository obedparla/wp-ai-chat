import { useState, useEffect, useCallback, useRef } from 'react'
import type { Message, ActiveTool } from '../hooks/useChat'
import ChatWidgetUI from './ChatWidgetUI'
import SendTranscriptDialog from './SendTranscriptDialog'
import ConfirmDialog from './ConfirmDialog'
import ConversationStarters from './ConversationStarters'

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
  switch (tool.toolName) {
    case 'search_products':
      return 'Searching products...'
    case 'get_product_details':
      return 'Loading product details...'
    case 'get_categories':
      return 'Loading categories...'
    default:
      return `Running ${tool.toolName}...`
  }
}

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
  const inputRef = useRef<HTMLTextAreaElement>(null)

  const isGreetingOnly =
    messages.length === 0 ||
    (messages.length === 1 && messages[0].role === 'assistant' && messages[0].id === 'greeting')
  const showConversationStarters =
    !isLoading && conversationStarters.length > 0 && isGreetingOnly

  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (e.key !== 'Escape') return
      if (showTranscriptDialog) {
        setShowTranscriptDialog(false)
        return
      }
      if (showNewConversationDialog) {
        setShowNewConversationDialog(false)
        return
      }
      onClose()
    },
    [onClose, showTranscriptDialog, showNewConversationDialog]
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
    'bg-transparent border-0 text-white cursor-pointer leading-none p-2 rounded-lg opacity-85 transition-all duration-200 flex items-center justify-center hover:opacity-100 hover:bg-white/10'

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
    <div className="fixed bottom-6 right-6 w-[428px] max-w-[calc(100vw-48px)] h-[680px] max-h-[calc(100vh-48px)] z-[9998] animate-wpaic-slideUp max-[480px]:bottom-0 max-[480px]:right-0 max-[480px]:left-0 max-[480px]:w-full max-[480px]:max-w-full max-[480px]:h-[100vh] max-[480px]:max-h-[100vh] max-[480px]:rounded-none max-[480px]:animate-wpaic-slideUpMobile">
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
      <ChatWidgetUI
        messages={messages}
        chatbotName={chatbotName}
        chatbotLogo={chatbotLogo}
        chatbotRole={chatbotRole}
        input={input}
        onInputChange={setInput}
        onSubmit={handleSubmit}
        onRetry={retry}
        inputRef={inputRef}
        headerActions={headerActions}
      >
        {showConversationStarters && (
          <ConversationStarters
            starters={conversationStarters}
            onSelect={handleStarterSelect}
          />
        )}
      </ChatWidgetUI>
      {isLoading && activeTools.length > 0 && (
        <div className="absolute bottom-[72px] left-0 right-0 py-3 px-5 flex flex-col gap-2 bg-white border-t border-slate-100">
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
      )}
      {isLoading && activeTools.length === 0 && (
        <div className="absolute bottom-[72px] left-0 right-0 py-3 px-5 text-slate-500 text-[13px] flex items-center gap-2 bg-white border-t border-slate-100 before:content-[''] before:w-2 before:h-2 before:bg-[var(--wpaic-primary)] before:rounded-full before:animate-bounce">
          Typing...
        </div>
      )}
    </div>
  )
}
