interface ConversationStartersProps {
  starters: string[]
  onSelect: (starter: string) => void
  disabled?: boolean
}

export default function ConversationStarters({
  starters,
  onSelect,
  disabled = false,
}: ConversationStartersProps) {
  if (starters.length === 0) {
    return null
  }

  return (
    <div className="mt-auto w-full animate-wpaic-fadeIn pt-2">
      <p className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
        Try asking
      </p>
      <div className="flex max-h-48 flex-col gap-2.5 overflow-y-auto pr-1">
        {starters.map((starter) => (
          <button
            key={starter}
            type="button"
            onClick={() => onSelect(starter)}
            disabled={disabled}
            className="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-700 shadow-sm transition-all duration-200 hover:border-[var(--wpaic-primary)] hover:text-[var(--wpaic-primary)] hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 max-[480px]:px-4 max-[480px]:py-3.5 max-[480px]:text-[15px]"
          >
            {starter}
          </button>
        ))}
      </div>
    </div>
  )
}
