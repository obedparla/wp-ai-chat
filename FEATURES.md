# WP AI Chatbot Features

## Chat Widget
- Floating chat widget on frontend
- Real-time streaming responses (SSE)
- Debounced multi-message sending with persistent input focus
- Markdown support with prose styling
- Product cards display search results
- Carousel for 3+ products

## Admin Configuration
- Provider connection and licensing status
- Greeting message customization
- System prompt customization
- Theme color picker
- Language selection (auto-detect or fixed)
- Enable/disable toggle

## Licensing & Billing
- Freemius-powered trials, licensing, billing, and premium updates
- Signed provider validation hides chat when the trial or license is inactive
- Licensing tab includes a direct activation link into the default Freemius flow
- Local `.local` and `.test` installs work against the provider in local development
- Provider rejections surface the exact reason in the chat stream

## Chatbot Branding
- Custom chatbot name
- Custom logo URL (circular avatar with online indicator)
- Custom role/subtitle text (e.g. "Personal stylist")
- Configurable header display

## Widget Redesign (2026-04)
- Refreshed header: circular avatar, green online dot, role subtitle
- White message area with flat gray assistant bubbles
- Cluster-based time separators (TODAY · HH:MM) on >5min gaps
- Pill input with internal circular up-arrow send button
- Inline-wrap outlined conversation starter pills
- Product cards: SALE badge, uppercase category caption, compact + ADD pill
- Product carousel: "N PICKS" header, hover-revealed desktop arrows
- Floating open button hides while widget is open, reclaiming vertical space
- Indigo (#2545B8) as new default theme color

## Proactive Engagement
- Timed popup message
- Configurable delay and message
- Page targeting (all/specific pages)

## WooCommerce Integration
- Product search tool (keyword, category, price)
- Product details tool
- Categories listing tool
- Guided shopping flow for broad queries (top categories first + one clarifying question)
- Product cards with images, prices, descriptions

## Handoff to Human Support
- Toggle to enable/disable handoff feature
- Bot collects customer name and email
- Creates support request in database
- Sends email notification to admin
- Support admin page lists all requests
- Status tracking (new/contacted/resolved)
- View full conversation transcript

## Train Bot - CSV Data
- Upload CSV files as data sources
- Define name, label, description per source
- Bot queries custom data via tool calling
- Supports multiple data sources
- Delete/replace existing sources

## Train Bot - FAQ Responses
- Enter Q&A pairs in textarea
- Format: "Q: question" / "A: answer"
- Separate pairs with blank line
- FAQ knowledge injected into system prompt
- Bot answers using FAQ content naturally
- Partial matches supported

## Chat Logs
- View all conversations
- Expandable message history
- Delete individual conversations

## Get page content tool
- Get any page when the user asks about relevant info
- E.g "refund policy" it gets the refunds page

## Current page context
- The bot knows which page we're on

## Cart awareness tool
- Knows about products in the cart and total

## Conversation starters
- Customizable options that show up at the start of a conversation
- Shows sensible defaults

## Admin Live Chat Preview
- Appearance tab renders real ChatWidget UI (reuses production component)
- Live updates from form inputs: name, role, logo, theme color
- Sample conversation with greeting, customer message, and product carousel
- Inert links/buttons but scrollable message list and carousel
