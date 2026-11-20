---
name: ai-connections
description: One connection per AI provider (CX9a): where its key is kept, the key an assistant calls with, the 11.0.240 data step that moved the keys, its page on Connections and who may change it
module: ai_connections
---

## What is an AI connection?
- One per provider of the registry's AI group that takes a key: OpenAI, Anthropic, Google Gemini, Mistral, DeepSeek, OpenRouter, Azure OpenAI (`pg_ai_connection_services()`, registry key => `ws_model_providers()` key; `azure_openai` => `azure`). A custom address is no service and has none.
- Architect (2026-11-17, message 9391): no table and no column. The key is sealed among the connected accounts' service entries: `pg_connect_service_put('ai_<provider>', name, array('key' => …))`, read by `pg_ai_connection_key()`.
- The registry rows' place is `connections.php` and their state the reader `pg_ai_connection_ready(<provider>)`: connected when a key is stored that can be read. `pg_connections_count()` stays 78.
- Each row's `found_at` keeps the Agents card (`pgset-ws-agents`, where the provider is chosen) among the settings search hits of its name (`pg_connections_place_keys()`).

## Which key does an assistant call with?
- `ws_model_agent_key()`: its own `key_sealed` when it keeps one, else its provider's connection (`pg_ai_connection_key_for()`). `ws_model_http()` and `ws_model_agent_missing()` read it; the agent form has no key field for a connected provider (CX9b).
- Azure OpenAI's address stays on each assistant (`door_url`); the connection holds only the key.

## What did the data step do? (11.0.240, `upgrade_2026_11_0_ai_connections()`)
- Per provider, `pg_ai_connection_move()`: where no connection exists the first key its model assistants hold (by id) becomes it; every assistant key equal to the connection's is set to NULL; a key that differs stays and goes on working. Run again it moves nothing; a site without an encryption key is skipped.
- What a running site calls with does not change: an emptied assistant now reads the same key from the connection.

## What does the provider's page show? (`connections.php?service=<key>`)
- "Connection details" ("Bağlantı bilgileri"): the API key (password field, the provider's data terms under it), "Save" filled, "Save and try" quiet (`pg_ai_connection_try()`: GET /models; Azure OpenAI is tried with Test on an assistant). Status has "Disconnect" (removes the key) when one is stored.
- "Where it is used": each model assistant of the provider, linked to Site Settings › Assistants and agents › Workspace agents, reading "Uses the connection's key" or "Uses its own key" ("Kendi anahtarını kullanıyor") with "Use the connection's key" ("Bağlantıdaki anahtarı kullan"), which sets its key to NULL. With none: "No assistant uses it yet."
- No settings place block: the key is on the page itself.

## Who sees and changes it?
- The rows keep the gate of the card the keys came from: shown only to a user who may open Site Settings › Assistants and agents (`pg_ai_connection_category()`).
- Connection details, Disconnect and "Use the connection's key": that category and the Workspace's administrator (role 0), who alone changes the assistants (`connections_ai_may_manage()`); a manager without it sees the state only. Nobody gains or loses a right.

## Where do the features show it? (CX9b)
- The assistants' form and the Voice message transcripts card take no key for a provider with a connection; voice's key joined it in 11.0.241: `ai-connections-feature-cards.md`.

Tests: `tests/ai_connections_test.php`.
