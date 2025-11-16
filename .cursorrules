# ParaGra PHP: Development Guidelines

This document provides development guidelines for the `paragra-php` project.

## Project Overview

`paragra-php` is a provider-agnostic PHP toolkit for orchestrating Retrieval-Augmented Generation (RAG) and Large Language Model (LLM) calls. It acts as an orchestration layer that sits above provider-specific libraries like `ragie-php`.

Its core responsibilities include:

-   Managing pools of AI providers (for retrieval, generation, embedding).
-   Handling automatic fallback and key rotation (`PriorityPool`).
-   Providing a unified interface for interacting with different vector stores (Pinecone, Weaviate, Qdrant, Chroma, Gemini File Search).
-   Offering optional moderation and external search enrichment.
-   Combining retrieval and generation into a single `answer()` method.

## Development Workflows

### Initial Setup

1.  **Install Dependencies**:
    ```bash
    composer install
    ```
    This will install dependencies, including a local path-based version of `ragie-php`.

2.  **Create Configuration**:
    ```bash
    cp config/paragra.example.php config/paragra.php
    ```
    This file defines the `priority_pools` for provider rotation and fallback.

3.  **Set Environment Variables**: API keys for the various services (Ragie, OpenAI, Gemini, etc.) are typically managed via a `.env` file loaded by the consuming application (e.g., `ask.vexy.art`). Refer to `config/paragra.example.php` for the required variables.

### Testing and Quality Assurance

The project uses a consistent set of QA tools.

-   **Run all checks**:
    ```bash
    composer qa
    ```
    This script runs linting, static analysis, and unit tests sequentially.

-   **Run individual checks**:
    ```bash
    # Run unit tests (PHPUnit)
    composer test

    # Fix coding standards (PHP-CS-Fixer)
    composer lint

    # Run PHPStan static analysis
    composer stan

    # Run Psalm static analysis
    composer psalm
    ```

### Managing the Provider Catalog

The provider catalog (`config/providers/catalog.php`) contains metadata about different AI models and providers.

-   **To refresh the catalog** from the `vexy-co-model-catalog` source:
    ```bash
    php tools/sync_provider_catalog.php
    ```

-   **To build pre-configured provider pools** using the catalog and environment variables:
    ```bash
    php tools/pool_builder.php --preset=free-tier
    ```

## Key Architectural Concepts

-   **`ParaGra` Class**: The main entry point for the library.
-   **ProviderSpec**: A standardized definition for a provider's configuration and capabilities.
-   **PriorityPool**: An ordered list of provider specs that dictates rotation and fallback logic.
-   **Vector Store Adapters**: A set of classes in `src/VectorStore/` that implement a common `VectorStoreInterface` for different database providers.
-   **Embedding Providers**: Classes in `src/Embedding/` that provide a unified interface for generating vector embeddings from different services.

## Relationship to Other Projects

-   **`ragie-php`**: `paragra-php` depends on `ragie-php` for its core keyword-based retrieval capabilities. The `composer.json` points to the local `../ragie-php` directory, so changes there are immediately reflected.
-   **`ask.vexy.art`**: This web application is the primary consumer of `paragra-php`. It uses `paragra-php` to power its RAG and text-generation endpoints. Development is often done in tandem, using a local path repository in `ask.vexy.art/private/composer.json`.
