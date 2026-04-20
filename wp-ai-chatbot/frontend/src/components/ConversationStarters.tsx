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
    <div className="w-full animate-wpaic-fadeIn pt-1 -mt-1">
      <div className="flex flex-wrap gap-2">
        {starters.map((starter) => (
          <button
            key={starter}
            type="button"
            onClick={() => onSelect(starter)}
            disabled={disabled}
            className="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 transition-all duration-200 hover:border-[var(--wpaic-primary)] hover:text-[var(--wpaic-primary)] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {starter}
          </button>
        ))}
      </div>
    </div>
  )
}
