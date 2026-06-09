import { useCallback, useEffect, useState } from 'react'
import type { ClearCartIntent, Message } from './useChat'
import { applyCartUpdate, requestClearCart } from '../lib/cart'

export type ClearCartStatus = 'pending' | 'clearing' | 'cleared' | 'cancelled' | 'error'

const STATUS_KEY = 'wpaic_clear_cart_status'
const TERMINAL_STATUSES: ClearCartStatus[] = ['cleared', 'cancelled', 'error']

function loadStatuses(): Record<string, ClearCartStatus> {
  try {
    const stored = sessionStorage.getItem(STATUS_KEY)
    if (!stored) return {}
    const parsed = JSON.parse(stored) as Record<string, ClearCartStatus>
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

// Persist only terminal outcomes so restoring a past conversation never re-opens
// the confirmation popup, while still showing the right result badge.
function persistStatuses(statuses: Record<string, ClearCartStatus>): void {
  try {
    const terminal: Record<string, ClearCartStatus> = {}
    for (const [id, status] of Object.entries(statuses)) {
      if (TERMINAL_STATUSES.includes(status)) terminal[id] = status
    }
    sessionStorage.setItem(STATUS_KEY, JSON.stringify(terminal))
  } catch {
    // Storage full or unavailable, ignore
  }
}

export interface ClearCartController {
  pending: ClearCartIntent | null
  statuses: Record<string, ClearCartStatus>
  confirm: () => void
  cancel: () => void
}

/**
 * Drives the clear-cart confirmation flow. The chatbot emits a clear_cart intent;
 * this exposes the first unhandled intent (to render the widget-level popup) plus a
 * per-intent status map (to render the inline result badge). The cart mutation runs
 * only after the shopper confirms.
 */
export function useClearCart(messages: Message[]): ClearCartController {
  const [statuses, setStatuses] = useState<Record<string, ClearCartStatus>>(loadStatuses)

  useEffect(() => {
    persistStatuses(statuses)
  }, [statuses])

  const intents: ClearCartIntent[] = []
  for (const message of messages) {
    if (message.clearCartIntents) intents.push(...message.clearCartIntents)
  }

  const pending = intents.find((intent) => statuses[intent.toolCallId] === undefined) ?? null

  const confirm = useCallback(() => {
    if (!pending) return
    const intent = pending

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
      setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'error' }))
      return
    }

    setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'clearing' }))

    const items = intent.clearAll
      ? undefined
      : intent.items.map((item) => ({ productId: item.productId, quantity: item.removeQuantity }))
    requestClearCart(items, wcAjaxUrl)
      .then((data) => {
        applyCartUpdate(data)
        setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'cleared' }))
      })
      .catch(() => {
        setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'error' }))
      })
  }, [pending])

  const cancel = useCallback(() => {
    if (!pending) return
    setStatuses((prev) => ({ ...prev, [pending.toolCallId]: 'cancelled' }))
  }, [pending])

  return { pending, statuses, confirm, cancel }
}
