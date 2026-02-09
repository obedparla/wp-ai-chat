# WP AI Chatbot - Implementation Plan

## Phase 1: Plugin Foundation ✅
- [x] Main plugin file with activation/deactivation hooks
- [x] Loader class to bootstrap components
- [x] Admin settings page (API key, model, greeting, enabled toggle)
- [x] REST API endpoints structure
- [x] CLAUDE.md documentation

## Phase 2: OpenAI Integration ✅
- [x] Composer setup with openai-php/client
- [x] WPAIC_Chat class with tool calling
- [x] SSE streaming endpoint `/chat/stream`
- [x] Tool definitions (search_products, get_product_details, get_categories)
- [x] Tool execution and response handling

## Phase 3: React Frontend Setup
- [ ] Initialize Vite + React + TypeScript in `/frontend`
- [ ] Configure Vite for WP integration (manifest, base path)
- [ ] Create chatbot widget component (floating button + chat panel)
- [ ] Message list component with user/bot styling
- [ ] Input component with send button
- [ ] State management for messages and open/closed state

## Phase 4: Frontend-Backend Integration
- [ ] SSE streaming support in useChat hook
- [ ] Handle message sending/receiving with real-time updates
- [ ] Loading states and error handling
- [ ] Auto-scroll to latest message
- [ ] Greeting message on first open
- [ ] Stop generation button

## Phase 5: WooCommerce Tools Testing
- [ ] Test search_products tool with sample queries
- [ ] Test get_product_details tool
- [ ] Test get_categories tool
- [ ] Handle multi-turn tool calling conversations
- [ ] Format product results nicely in responses

## Phase 6: Polish & UX
- [ ] Responsive design (mobile-friendly)
- [ ] Keyboard shortcuts (Enter to send, Escape to close)
- [ ] Typing indicator during AI response
- [ ] Persist conversation in localStorage
- [ ] Smooth animations for open/close

## Phase 7: Production Ready
- [ ] Build optimization (tree shaking, minification)
- [ ] Error logging
- [ ] Security review (sanitization, escaping)
- [ ] Test with real WooCommerce products

---

## Technical Decisions

### OpenAI Integration
Using `openai-php/client` via Composer - typed responses, streaming support, well-maintained.

### Streaming
SSE (Server-Sent Events) over WebSockets - simpler, matches OpenAI's API, works with standard PHP.

### Scope
WooCommerce products only (no generic WP posts).

### Tool Calling Flow
1. User sends message
2. PHP formats messages + tools → OpenAI (streaming)
3. If OpenAI returns tool_calls:
   - Execute each tool (search products, etc.)
   - Append tool results to conversation
   - Stream final response from OpenAI
4. SSE sends chunks to frontend in real-time
