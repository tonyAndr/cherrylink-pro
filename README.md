# CherryLink Pro AI

WordPress plugin for AI-powered internal linking. Uses semantic vector search (BAAI/bge-m3 via Cherry Vector API) to find relevant articles and suggest anchor phrases — replacing the SQL fulltext search used in CherryLink Pro.

## How it works

1. When a post is saved, the plugin sends its content to the Cherry Vector API, which embeds it and stores it in Qdrant.
2. In the editor, the plugin queries the API with the current article's text to find semantically similar posts.
3. For each result, the API returns suggested anchor phrases extracted from the current article's HTML — ready to insert as internal links.

## Differences from CherryLink Pro

| Feature | CherryLink Pro | CherryLink Pro AI |
|---|---|---|
| Search method | SQL fulltext + stemming | Vector embeddings (semantic) |
| Russian support | Custom stemmer | BAAI/bge-m3 (multilingual) |
| Anchor suggestions | Manual | AI-extracted from article HTML |
| Infrastructure | Self-contained | Requires Cherry Vector API |

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Cherry Vector API instance (self-hosted)
