Convert my feature requirements into structured PRD items.


Each item should have: category, description, steps to verify, and passes:       
false.                                                                           

Format as JSON. Be specific about acceptance criteria. Your task is to add them to @prd.json, and not to write code.

Example: 

``` 
{
"category": "functional",
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


Features/fixes to write PRD for, check if these already exist in the PRD first: