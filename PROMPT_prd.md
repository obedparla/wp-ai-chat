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


Features/fixes to write PRD for:

- Create a Claude file explaining the structure of this project:

The wp-AI-chatbot contains all the chatbot logic for the plugin that is
going to be used to be installed by users. Investigate how this plugin
works, read its own claude.md. A short version is that it connects to       
OpenAI via API to power the chatbot.

The wp-AI--provider should contain all the logic to be a "provider" server.

---

I want to refactor the logic of the wp-ai-chatbot plugin to not connect to   
OpenAI directly, but to our wp-ai-provider instead. This way we can  
offer a much better user experience. Once the user connects the chatbot      
plugin is enabled, it uses the provider as the API to get the responses. This way the user doesn't have to specify any OpenAI key, and we can tweak the AI provider at any point to improve the UX.

In order to do this implement:

- Implement a base WordPress template for the provider folder
- The provider logic is to behave like a server that receives the chatbot query, sends it to OpenAI, and streams the response back to the client (the chatbot).
    - It's crucial that we stream back the response from OpenAI instead of waiting for it before sending it. Streaming is a much better user experience
    - We can probably copy some of the existing logic the chatbot plugin uses as it already supports conencting to OpenAI and streaming the response to the chatbot. But now we need the chatbot server to have a middleman to connect to the provider