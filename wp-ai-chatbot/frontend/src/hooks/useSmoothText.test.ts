import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useSmoothText } from './useSmoothText'

// Manual rAF harness: frames only advance when the test drives them, so the
// reveal animation is fully deterministic.
let rafCallbacks: Map<number, FrameRequestCallback>
let rafNextId: number

function flushFrame(now: number) {
  const callbacks = [...rafCallbacks.values()]
  rafCallbacks.clear()
  act(() => {
    callbacks.forEach((callback) => callback(now))
  })
}

/** Drive frames 16ms apart until the animation settles (no pending frames). */
function flushUntilSettled(startAt = 0, maxFrames = 1000): number {
  let now = startAt
  let frames = 0
  while (rafCallbacks.size > 0 && frames < maxFrames) {
    now += 16
    flushFrame(now)
    frames++
  }
  return now
}

beforeEach(() => {
  rafCallbacks = new Map()
  rafNextId = 0
  vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
    rafCallbacks.set(++rafNextId, callback)
    return rafNextId
  })
  vi.stubGlobal('cancelAnimationFrame', (id: number) => {
    rafCallbacks.delete(id)
  })
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('useSmoothText', () => {
  it('is idle for a non-streaming message', () => {
    const { result } = renderHook(() =>
      useSmoothText({ id: 'm1', content: 'Hello there', isStreaming: false })
    )

    expect(result.current.messageId).toBeNull()
    expect(result.current.isComplete).toBe(true)
  })

  it('is idle when there is no message', () => {
    const { result } = renderHook(() => useSmoothText(undefined))

    expect(result.current.messageId).toBeNull()
    expect(result.current.isComplete).toBe(true)
  })

  it('reveals a streaming message gradually instead of all at once', () => {
    const content = 'This is a longer streamed reply that should reveal over time.'
    const { result } = renderHook(() => useSmoothText({ id: 'm1', content, isStreaming: true }))

    expect(result.current.messageId).toBe('m1')
    expect(result.current.displayedText).toBe('')
    expect(result.current.isComplete).toBe(false)

    flushFrame(0)
    flushFrame(16)
    const early = result.current.displayedText
    expect(early.length).toBeGreaterThan(0)
    expect(early.length).toBeLessThan(content.length)
    expect(content.startsWith(early)).toBe(true)
  })

  it('keeps revealing to completion after the stream ends', () => {
    const content = 'Short reply.'
    const { result, rerender } = renderHook(
      ({ isStreaming }: { isStreaming: boolean }) => useSmoothText({ id: 'm1', content, isStreaming }),
      { initialProps: { isStreaming: true } }
    )

    flushFrame(0)
    flushFrame(16)
    expect(result.current.displayedText.length).toBeLessThan(content.length)

    // Stream closes while text is still being revealed.
    rerender({ isStreaming: false })
    expect(result.current.isComplete).toBe(false)

    flushUntilSettled(16)

    expect(result.current.displayedText).toBe(content)
    expect(result.current.isComplete).toBe(true)
  })

  it('grows monotonically while new deltas arrive', () => {
    const { result, rerender } = renderHook(
      ({ content }: { content: string }) => useSmoothText({ id: 'm1', content, isStreaming: true }),
      { initialProps: { content: 'First chunk' } }
    )

    flushFrame(0)
    flushFrame(16)
    const beforeDelta = result.current.displayedText.length

    rerender({ content: 'First chunk and then some more text' })
    flushFrame(32)
    flushFrame(48)

    expect(result.current.displayedText.length).toBeGreaterThanOrEqual(beforeDelta)
    expect('First chunk and then some more text'.startsWith(result.current.displayedText)).toBe(true)
  })

  it('adopts a new streaming message and restarts the reveal', () => {
    const { result, rerender } = renderHook(
      ({ id, content }: { id: string; content: string }) => useSmoothText({ id, content, isStreaming: true }),
      { initialProps: { id: 'm1', content: 'First message reply' } }
    )

    flushUntilSettled()

    rerender({ id: 'm2', content: 'Second message reply' })
    expect(result.current.messageId).toBe('m2')
    expect(result.current.displayedText).toBe('')
    expect(result.current.isComplete).toBe(false)
  })

  it('passes through other messages untouched while one is adopted', () => {
    const { result, rerender } = renderHook(
      ({ id, isStreaming }: { id: string; isStreaming: boolean }) =>
        useSmoothText({ id, content: 'Reply', isStreaming }),
      { initialProps: { id: 'm1', isStreaming: true } }
    )

    // A different, non-streaming message becomes the last one (e.g. user sent
    // a new message): the hook goes idle rather than smoothing it.
    rerender({ id: 'other', isStreaming: false })
    expect(result.current.messageId).toBeNull()
    expect(result.current.isComplete).toBe(true)
  })
})
