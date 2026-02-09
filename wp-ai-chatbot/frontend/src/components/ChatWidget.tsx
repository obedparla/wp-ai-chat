import { useState, useEffect, useCallback } from 'react'
import type { Message, ActiveTool } from '../hooks/useChat'
import MessageList from './MessageList'
import ChatInput from './ChatInput'

interface ChatWidgetProps {
  onClose: () => void
  chat: {
    messages: Message[]
    sendMessage: (content: string) => void
    isLoading: boolean
    stopGeneration: () => void
    clearChat: () => void
    activeTools: ActiveTool[]
    retry: () => void
  }
  chatbotName?: string
  chatbotLogo?: string
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

export default function ChatWidget({ onClose, chat, chatbotName, chatbotLogo }: ChatWidgetProps) {
  const { messages, sendMessage, isLoading, stopGeneration, clearChat, activeTools, retry } = chat
  const [input, setInput] = useState('')

  const hasName = chatbotName && chatbotName.trim().length > 0
  const hasLogo = chatbotLogo && chatbotLogo.trim().length > 0
  const displayTitle = hasName ? chatbotName : 'AI Assistant'
  const showSubtitle = hasName

  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose()
      }
    },
    [onClose]
  )

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [handleKeyDown])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!input.trim() || isLoading) return
    sendMessage(input.trim())
    setInput('')
  }

  return (
    <div className="fixed bottom-[100px] right-6 w-[380px] max-w-[calc(100vw-48px)] h-[600px] max-h-[calc(100vh-140px)] bg-white rounded-2xl shadow-xl flex flex-col z-[9998] overflow-hidden animate-wpaic-slideUp border border-slate-200 max-[480px]:bottom-0 max-[480px]:right-0 max-[480px]:left-0 max-[480px]:w-full max-[480px]:max-w-full max-[480px]:h-[calc(100vh-60px)] max-[480px]:max-h-[calc(100vh-60px)] max-[480px]:rounded-t-2xl max-[480px]:rounded-b-none max-[480px]:border-b-0 max-[480px]:animate-wpaic-slideUpMobile">
      <div className="bg-[var(--wpaic-primary)] text-white py-[14px] px-5 flex justify-between items-center shrink-0">
        <div className="flex items-center gap-2.5">
          {hasLogo && (
            <img
              src={chatbotLogo}
              alt=""
              className="h-8 max-h-8 w-auto object-contain"
            />
          )}
          <div className="flex flex-col">
            <span className="font-semibold text-[15px] tracking-tight leading-tight">{displayTitle}</span>
            {showSubtitle && (
              <span className="text-[11px] opacity-80 leading-tight">AI Assistant</span>
            )}
          </div>
        </div>
        <div className="flex gap-1 items-center">
          <button
            onClick={clearChat}
            className="bg-white/15 border-0 text-white cursor-pointer leading-none p-2 rounded-lg opacity-90 transition-all duration-200 flex items-center justify-center text-base hover:opacity-100 hover:bg-white/25 hover:scale-105"
            aria-label="Clear chat"
            title="Clear chat"
          >
            ↺
          </button>
          <button
            onClick={onClose}
            className="bg-white/15 border-0 text-white cursor-pointer leading-none p-2 rounded-lg opacity-90 transition-all duration-200 flex items-center justify-center text-xl hover:opacity-100 hover:bg-white/25 hover:scale-105"
            aria-label="Close"
          >
            ×
          </button>
        </div>
      </div>
      <MessageList messages={messages} onRetry={retry} />
      {isLoading && activeTools.length > 0 && (
        <div className="py-3 px-5 flex flex-col gap-2 bg-white border-t border-slate-200">
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
        <div className="py-3 px-5 text-slate-500 text-[13px] flex items-center gap-2 bg-white border-t border-slate-200 before:content-[''] before:w-2 before:h-2 before:bg-[var(--wpaic-primary)] before:rounded-full before:animate-bounce">
          Typing...
        </div>
      )}
      <ChatInput
        value={input}
        onChange={setInput}
        onSubmit={handleSubmit}
        isLoading={isLoading}
        onStop={stopGeneration}
      />
    </div>
  )
}
