import type { ReactNode } from 'react'
import type { Message } from '../hooks/useChat'
import MessageList from './MessageList'
import ChatInput from './ChatInput'

interface ChatWidgetUIProps {
  messages: Message[]
  chatbotName?: string
  chatbotLogo?: string
  chatbotRole?: string
  input: string
  onInputChange: (value: string) => void
  onSubmit: (e: React.FormEvent) => void
  onRetry?: () => void
  inputRef?: React.Ref<HTMLTextAreaElement>
  placeholder?: string
  headerActions?: ReactNode
  children?: ReactNode
}

export default function ChatWidgetUI({
  messages,
  chatbotName,
  chatbotLogo,
  chatbotRole,
  input,
  onInputChange,
  onSubmit,
  onRetry,
  inputRef,
  placeholder,
  headerActions,
  children,
}: ChatWidgetUIProps) {
  const hasName = chatbotName && chatbotName.trim().length > 0
  const hasLogo = chatbotLogo && chatbotLogo.trim().length > 0
  const displayTitle = hasName ? chatbotName : 'AI Assistant'
  const avatarLetter = (displayTitle || 'A').trim().charAt(0).toUpperCase()
  const subtitleRole = chatbotRole && chatbotRole.trim().length > 0 ? chatbotRole : 'AI Assistant'

  return (
    <div className="w-full h-full bg-white rounded-2xl shadow-xl flex flex-col overflow-hidden border border-slate-200">
      <div className="bg-[var(--wpaic-primary)] text-white py-4 px-5 flex justify-between items-center shrink-0 gap-3">
        <div className="flex items-center gap-3 min-w-0">
          <div className="relative shrink-0">
            <div className="w-11 h-11 rounded-full bg-white/15 flex items-center justify-center overflow-hidden">
              {hasLogo ? (
                <img
                  src={chatbotLogo}
                  alt=""
                  className="w-full h-full object-cover"
                />
              ) : (
                <span className="font-serif italic text-xl text-white/90 leading-none">
                  {avatarLetter}
                </span>
              )}
            </div>
            <span
              className="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-400 border-2 border-[var(--wpaic-primary)]"
              aria-hidden
            />
          </div>
          <div className="flex flex-col min-w-0">
            <span className="font-semibold text-base tracking-tight leading-tight truncate">{displayTitle}</span>
            <span className="text-xs opacity-80 leading-tight truncate">
              {subtitleRole} <span className="opacity-60">·</span> online
            </span>
          </div>
        </div>
        {headerActions && (
          <div className="flex gap-0.5 items-center shrink-0">
            {headerActions}
          </div>
        )}
      </div>
      <MessageList messages={messages} onRetry={onRetry}>
        {children}
      </MessageList>
      <ChatInput
        ref={inputRef}
        value={input}
        onChange={onInputChange}
        onSubmit={onSubmit}
        placeholder={placeholder ?? (hasName ? `Ask ${chatbotName} anything...` : 'Ask anything...')}
      />
    </div>
  )
}
