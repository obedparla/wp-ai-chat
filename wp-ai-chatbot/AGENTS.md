# WP AI Chatbot

An AI chatbot for WordPress/WooCommerce

# Knowledge
Frontend must be built after any component/style changes:
```bash
cd frontend && npm run build
```

Tool schemas for no-arg OpenAI tools must still include an empty object `properties` definition:
```php
'parameters' => array(
    'type'       => 'object',
    'properties' => new \stdClass(),
),
```
Do not omit `properties` for no-arg object schemas, or OpenAI will reject the tool definition.
