Convert my feature requirements into structured PRD items.


Each item should have: category, description, steps to verify, and passes:       
false.                                                                           

Format as JSON. Be specific about acceptance criteria. Your task is to add them to @prd.json, and not to write code.

Each PRD is a thin vertical slice that cuts through ALL integration layers end-to-end, NOT a horizontal slice of one layer.

Slices may be 'HITL' or 'AFK'. HITL slices require human interaction, such as an architectural decision or a design review. AFK slices can be implemented and merged without human interaction. Prefer AFK over HITL where possible.

Each slice delivers a narrow but COMPLETE path through every layer (schema, API, UI, tests) - A completed slice is demoable or verifiable on its own - Prefer many thin slices over few thick ones

Example: 

``` 
{
"project": "chatbot"|"provider"|"chatbot & provider" // this one explains which project the feature is on
"category": "functional/bug/feature/ui/etc etc",
"description": "New chat button creates a fresh conversation",
"steps": [
"Navigate to main interface",
"Click the 'New Chat' button",
"Verify a new conversation is created",
"Check that chat area shows welcome state",
"Verify conversation appears in sidebar"
],
"passes": false
}
```

Once done, reassess them and ask yourself if the descriptions are clear enough for another AI to implement them correctly.


Features/fixes to write PRD for:

- Implement a "Send transcript" button in the chatbot frontend. the button should be next to the "clat chat" as an email icon. Once clicked it should open a little dialog asking for the email, and a "Send transcript" button below it. Once clicked it should update the dialog to a "We've sent you the email transcript over email" which shows for 5 seconds. Use shadcn/ui component for this if possible. The dialog should remain WITHIN the chatbot, not go outside its boundaries. The email should be sent right away with a very basic text transcript.
- 