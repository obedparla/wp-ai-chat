import type { ClearCartIntent } from '../hooks/useChat'
import type { ClearCartStatus } from '../hooks/useClearCart'

interface ClearCartTriggerProps {
  intent: ClearCartIntent
  status?: ClearCartStatus
}

// Inline result badge for a clear_cart action. The confirmation popup and the cart
// mutation are handled by useClearCart at the widget level; this only reflects the
// outcome. While awaiting confirmation (no status / 'pending') it renders nothing —
// the popup is on screen instead.
export default function ClearCartTrigger({ intent, status }: ClearCartTriggerProps) {
  if (!status || status === 'pending') return null

  const singleItem = intent.items.length === 1 ? intent.items[0] : null
  const removedLabel = intent.clearAll
    ? 'Cart cleared'
    : singleItem
      ? singleItem.removeAll
        ? `Removed ${singleItem.name || 'item'}`
        : `Removed ${singleItem.removeQuantity} × ${singleItem.name || 'item'}`
      : 'Items removed'

  const label =
    status === 'clearing'
      ? 'Updating cart…'
      : status === 'cleared'
        ? removedLabel
        : status === 'cancelled'
          ? 'Kept your cart'
          : 'Could not update cart'

  return (
    <div
      className="inline-flex items-center gap-1.5 self-start rounded-full bg-slate-100 py-1 px-2.5 text-[11px] font-medium tracking-wide text-slate-600"
      role="status"
      aria-live="polite"
    >
      {status === 'clearing' && (
        <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-500" />
      )}
      {status === 'cleared' && (
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="h-3 w-3 text-slate-500"
        >
          <path d="M3 6h18" />
          <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
        </svg>
      )}
      <span className={status === 'error' ? 'text-red-600' : status === 'cleared' ? 'text-slate-700' : ''}>
        {label}
      </span>
    </div>
  )
}
