export interface CheckoutAction {
  checkout_url: string
  cart_url: string
  has_cart: boolean
  item_count: number
}

interface CheckoutButtonProps {
  action: CheckoutAction
}

export default function CheckoutButton({ action }: CheckoutButtonProps) {
  const checkoutUrl = action.checkout_url
  const cartUrl = action.cart_url

  if (!checkoutUrl && !cartUrl) return null

  const primaryHref = checkoutUrl || cartUrl
  const primaryLabel = checkoutUrl ? 'CHECKOUT' : 'VIEW CART'

  return (
    <div className="self-start flex flex-col items-start gap-2 animate-wpaic-fadeIn">
      <a
        href={primaryHref}
        className="wpaic-no-underline inline-flex items-center gap-1.5 rounded-full border-0 cursor-pointer font-semibold text-sm transition-all duration-200 px-5 py-2.5 shrink-0 bg-[var(--wpaic-primary)] text-white hover:scale-[1.03] active:scale-95 shadow-sm tracking-wider"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
          className="w-4 h-4"
        >
          <circle cx="9" cy="21" r="1" />
          <circle cx="20" cy="21" r="1" />
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
        </svg>
        <span>{primaryLabel}</span>
      </a>
      {checkoutUrl && cartUrl && (
        <a
          href={cartUrl}
          className="wpaic-no-underline text-xs font-medium text-slate-500 hover:text-[var(--wpaic-primary)] tracking-wide"
        >
          or view cart
        </a>
      )}
    </div>
  )
}
