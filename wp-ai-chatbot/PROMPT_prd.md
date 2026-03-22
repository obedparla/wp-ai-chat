Convert my feature requirements into structured PRD items.

Each item should have: category, description, steps to verify, and passes: false.

Format as JSON. Be specific about acceptance criteria. Your task is to add them to @prd.json, and not to write code.

Each PRD is a thin vertical slice that cuts through ALL integration layers end-to-end, NOT a horizontal slice of one layer.

Each slice delivers a narrow but COMPLETE path through every layer (API, UI, types, etc) - A completed slice is demoable or verifiable on its own - Prefer many thin slices over few thick ones.

Make sure to investigate each entry in order to create the best possible PRD entry. Use 20 subagents for this. If the task seems simple we mostly need the location of where the changes need to be made.

Example:

``` 
{
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
