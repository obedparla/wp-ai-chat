import { useEffect, useState } from 'react'

/**
 * ChatGPT-style smooth text reveal for the streaming assistant message.
 *
 * Network deltas arrive in uneven bursts (proxies and hosting stacks between
 * the store and the shopper buffer SSE unpredictably), so rendering raw
 * arrivals reads as text "jumping" in. This hook decouples display from
 * arrival: revealed text catches up to the streamed target at a rate
 * proportional to the backlog, so bursts spread into a steady per-frame
 * flow that tracks real generation speed with ~a third of a second of lag.
 *
 * Only one message streams at a time, so a single hook instance follows the
 * active streaming message (adopted by id) and keeps revealing until caught
 * up, even after the network stream has already finished.
 */

interface SmoothTarget {
  id?: string
  content: string
  isStreaming?: boolean
}

export interface SmoothTextState {
  /** Id of the message currently being revealed, or null when idle. */
  messageId: string | null
  /** The portion of the target text revealed so far. */
  displayedText: string
  /** True once the stream ended and every character is revealed. */
  isComplete: boolean
}

interface RevealState {
  id: string | null
  /** Revealed character count; fractional so per-frame advances accumulate. */
  count: number
}

// Backlog catch-up time constant: a burst of text fully reveals in roughly
// this long, so reveal speed naturally tracks generation speed.
const CATCH_UP_MS = 300
// Floor speed so the tail of a message never crawls.
const MIN_CHARS_PER_SECOND = 40
// Clamp frame gaps (background tab throttling) so dt spikes cannot dump the
// whole backlog in one frame.
const MAX_FRAME_MS = 100

export function useSmoothText(target: SmoothTarget | undefined): SmoothTextState {
  const [reveal, setReveal] = useState<RevealState>({ id: null, count: 0 })

  const targetId = target?.id ?? null

  // Adopt a newly streaming message mid-render (the sanctioned
  // adjust-state-when-props-change pattern); its reveal starts from zero.
  const isAdopting = target?.isStreaming === true && targetId !== null && reveal.id !== targetId
  if (isAdopting) {
    setReveal({ id: targetId, count: 0 })
  }
  const currentReveal: RevealState = isAdopting ? { id: targetId, count: 0 } : reveal

  const isActive = currentReveal.id !== null && currentReveal.id === targetId
  const targetText = isActive ? (target?.content ?? '') : ''
  const targetLength = targetText.length
  const hasBacklog = isActive && currentReveal.count < targetLength

  useEffect(() => {
    if (!hasBacklog) return

    let frame: number | null = null
    let lastFrameAt: number | null = null

    const tick = (now: number) => {
      const dt = Math.min(MAX_FRAME_MS, now - (lastFrameAt ?? now))
      lastFrameAt = now

      setReveal((previous) => {
        if (previous.id !== targetId) return previous
        const backlog = targetLength - previous.count
        if (backlog <= 0) return previous
        const proportional = backlog * (dt / CATCH_UP_MS)
        const floor = (MIN_CHARS_PER_SECOND * dt) / 1000
        return {
          id: previous.id,
          count: Math.min(targetLength, previous.count + Math.max(proportional, floor)),
        }
      })

      frame = requestAnimationFrame(tick)
    }

    frame = requestAnimationFrame(tick)
    return () => {
      if (frame !== null) cancelAnimationFrame(frame)
    }
  }, [hasBacklog, targetId, targetLength])

  if (!isActive) {
    return { messageId: null, displayedText: '', isComplete: true }
  }

  const revealedCount = Math.floor(currentReveal.count)
  return {
    messageId: currentReveal.id,
    displayedText: targetText.slice(0, revealedCount),
    isComplete: target?.isStreaming !== true && revealedCount >= targetLength,
  }
}
