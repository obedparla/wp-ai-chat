Bugs to fix. Mark as "Done: true" when fixed.

# "Type a message" chatbot textbox focus

- Done: true
- It should be auto-focused when the chatbot is opened from the button
- It should remain focused after sending a message
- It should not be blocked by sending a message, we want to be able to send multiple messages


Once we're able to send multiple messages, we should implement a "debounce" on sending the messages, so that the user can send multiple messages in quick succession and get a single answer to both. Make the debounce 6 seconds 

There should still be a "Typing..." to show the AI is answering



- Proactive Message: broken
- Responses are very slow
- Tools for product showing not working