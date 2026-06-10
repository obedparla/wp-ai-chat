import { AddToCartIntent } from '../hooks/useChat'
import { useAddToCart } from '../hooks/useAddToCart'

interface AddToCartTriggerProps {
  intent: AddToCartIntent
}

export default function AddToCartTrigger({ intent }: AddToCartTriggerProps) {
  const status = useAddToCart(intent)

  const label =
    status === 'added'
      ? 'Added to cart'
      : status === 'error'
        ? 'Could not add to cart'
        : 'Adding to cart…'

  return (
    <div
      className="inline-flex items-center gap-1.5 self-start rounded-full bg-slate-100 py-1 px-2.5 text-[11px] font-medium tracking-wide text-slate-600"
      role="status"
      aria-live="polite"
    >
      {status === 'adding' && (
        <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-slate-500" />
      )}
      {status === 'added' && (
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="3"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="h-3 w-3 text-emerald-600"
        >
          <path d="M20 6 9 17l-5-5" />
        </svg>
      )}
      <span className={status === 'error' ? 'text-red-600' : status === 'added' ? 'text-emerald-700' : ''}>
        {label}
      </span>
    </div>
  )
}
