<?php

declare(strict_types=1);

// this_file: paragra-php/config/providers/catalog.php

return array (
  'generated_at' => '2025-11-16T02:05:15+00:00',
  'source' => '/Users/adam/Developer/vcs/github.vexyart/vexy-co-model-catalog',
  'providers' => 
  array (
    0 => 
    array (
      'slug' => 'aihorde',
      'display_name' => 'Aihorde',
      'description' => '',
      'api_key_env' => 'AIHORDE_KEY',
      'base_url' => 'https://oai.aihorde.net/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    1 => 
    array (
      'slug' => 'anthropic',
      'display_name' => 'Anthropic',
      'description' => '',
      'api_key_env' => 'ANTHROPIC_API_KEY',
      'base_url' => 'https://api.anthropic.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    2 => 
    array (
      'slug' => 'arliai',
      'display_name' => 'Arliai',
      'description' => '',
      'api_key_env' => 'ARLIAI_TEXT_API_KEY',
      'base_url' => 'https://api.arliai.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    3 => 
    array (
      'slug' => 'askyoda',
      'display_name' => 'EdenAI AskYoda',
      'description' => 'Full retrieval + answer pipeline via EdenAI AskYoda.',
      'api_key_env' => 'EDENAI_API_KEY',
      'base_url' => 'https://api.edenai.run/v2',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'askyoda',
      'default_models' => 
      array (
        'generation' => 'askyoda:gemini-2.5-flash-lite',
      ),
      'default_solution' => 
      array (
        'type' => 'askyoda',
        'defaults' => 
        array (
          'k' => 10,
          'min_score' => 0.3,
        ),
      ),
      'metadata' => 
      array (
        'tier' => 'hosted',
        'latency' => 'medium',
        'latency_tier' => 'hosted',
        'insights' => 
        array (
          'eden-askyoda' => 
          array (
            'slug' => 'eden-askyoda',
            'name' => 'Eden AI AskYoda Hosted Workflow',
            'category' => 'hosted_rag',
            'reset_window' => 'per_minute_plan_tiers',
            'commercial_use' => 'Starter tier is free for testing; Personal/Professional plans unlock higher renewable RPM and production support.',
            'notes' => 'AskYoda runs on Eden AI\'s hosted workflow engine, routes to multiple LLMs, exposes fallback_providers, and surfaces latency/response-time telemetry in the monitoring dashboard so operators can keep responses in the sub-3-second tier.',
            'modalities' => 
            array (
              0 => 'rag',
              1 => 'llm_router',
              2 => 'workflow',
            ),
            'recommended_roles' => 
            array (
              0 => 'hosted_fallback_rag',
              1 => 'edenai_pool',
              2 => 'latency_guardrail',
            ),
            'free_tier' => 
            array (
              'starter_requests_per_minute' => 60,
              'personal_requests_per_minute' => 300,
              'professional_requests_per_minute' => 1000,
              'http_429_response' => 'Returns HTTP 429 when tier limit exceeded',
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-rag/ragres-14.md',
                'sha256' => '73dff8f43d637610bb2b1c4a94825771c6d8e4422809f4ea554962b17913ba26',
                'start_line' => 10,
                'end_line' => 16,
              ),
              1 => 
              array (
                'path' => 'reference/research-rag/ragres-14.md',
                'sha256' => '73dff8f43d637610bb2b1c4a94825771c6d8e4422809f4ea554962b17913ba26',
                'start_line' => 18,
                'end_line' => 19,
              ),
            ),
          ),
        ),
      ),
    ),
    4 => 
    array (
      'slug' => 'atlascloud',
      'display_name' => 'Atlascloud',
      'description' => '',
      'api_key_env' => 'ATLASCLOUD_API_KEY',
      'base_url' => NULL,
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    5 => 
    array (
      'slug' => 'avian',
      'display_name' => 'Avian',
      'description' => '',
      'api_key_env' => 'AVIAN_API_KEY',
      'base_url' => 'https://api.avian.io/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    6 => 
    array (
      'slug' => 'baseten',
      'display_name' => 'Baseten',
      'description' => '',
      'api_key_env' => 'BASETEN_API_KEY',
      'base_url' => 'https://inference.baseten.co/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    7 => 
    array (
      'slug' => 'bedrock-kb',
      'display_name' => 'AWS Bedrock Knowledge Bases',
      'description' => 'Managed retrieval layer that pairs OpenSearch Serverless with AWS-hosted LLMs.',
      'api_key_env' => NULL,
      'base_url' => NULL,
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => true,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'managed',
        'latency' => 'dependent',
        'insights' => 
        array (
          'aws-bedrock-knowledge-bases' => 
          array (
            'slug' => 'aws-bedrock-knowledge-bases',
            'name' => 'AWS Bedrock Knowledge Bases',
            'category' => 'managed_rag',
            'reset_window' => 'monthly',
            'commercial_use' => 'AWS enterprise workloads with IAM + audit trails; billed monthly alongside OpenSearch/Neptune usage.',
            'notes' => 'Bedrock Knowledge Bases couple OpenSearch Serverless with optional Neptune GraphRAG pipelines so enterprises can load content, pick Anthropic/Meta/Amazon models, and ship retrieval flows without bespoke infrastructure.',
            'modalities' => 
            array (
              0 => 'rag',
              1 => 'vector_store',
              2 => 'graph',
            ),
            'recommended_roles' => 
            array (
              0 => 'managed_graph_rag',
              1 => 'enterprise_handoff',
            ),
            'free_tier' => 
            array (
              'monthly_cost_usd' => 500,
              'setup_days' => 5,
              'includes_vector_db' => false,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-rag/ragres-05.md',
                'sha256' => 'b359d75121ad8ea1562fc2b49282fe480f80674c89e80ec38b946de503de7489',
                'start_line' => 62,
                'end_line' => 70,
              ),
              1 => 
              array (
                'path' => 'reference/research-rag/ragres-05.md',
                'sha256' => 'b359d75121ad8ea1562fc2b49282fe480f80674c89e80ec38b946de503de7489',
                'start_line' => 96,
                'end_line' => 132,
              ),
            ),
          ),
        ),
      ),
    ),
    8 => 
    array (
      'slug' => 'cerebras',
      'display_name' => 'Cerebras',
      'description' => 'Fast, low-cost Llama-3.3 hosting and generous free tier.',
      'api_key_env' => 'CEREBRAS_API_KEY',
      'base_url' => 'https://api.cerebras.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'ragie',
      'default_models' => 
      array (
        'generation' => 'llama-3.3-70b',
        'fast_generation' => 'llama-3.1-8b',
      ),
      'default_solution' => 
      array (
        'type' => 'ragie',
        'ragie_partition' => 'default',
        'metadata' => 
        array (
          'tier' => 'free',
        ),
      ),
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
      ),
    ),
    9 => 
    array (
      'slug' => 'chutes',
      'display_name' => 'Chutes',
      'description' => '',
      'api_key_env' => 'CHUTES_API_KEY',
      'base_url' => 'https://llm.chutes.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    10 => 
    array (
      'slug' => 'cloudflare',
      'display_name' => 'Cloudflare Workers AI',
      'description' => 'Edge-hosted inference with EmbeddingGemma and 10k neuron daily renewals.',
      'api_key_env' => 'CLOUDFLARE_API_TOKEN',
      'base_url' => 'https://api.cloudflare.com/client/v4',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => true,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'workers-ai',
      'default_models' => 
      array (
        'generation' => '@cf/meta/llama-3.1-8b-instruct',
        'embedding' => '@cf/google/gemma-embedding-002',
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'low',
        'insights' => 
        array (
          'cloudflare-workers-ai' => 
          array (
            'slug' => 'cloudflare-workers-ai',
            'name' => 'Cloudflare Workers AI – EmbeddingGemma',
            'category' => 'edge_ai',
            'reset_window' => 'daily',
            'commercial_use' => 'Covered by Workers AI free plan; commercial usage allowed within neuron allocation.',
            'notes' => 'Workers AI hosts EmbeddingGemma-300m plus BGE family models with 10,000 free neurons per day (~5–10M tokens). Paid overages cost $0.011 per 1,000 neurons and run at Cloudflare\'s global edge.',
            'modalities' => 
            array (
              0 => 'embedding',
              1 => 'llm',
              2 => 'vector',
            ),
            'recommended_roles' => 
            array (
              0 => 'edge_embeddings',
              1 => 'latency_sensitive_rag',
            ),
            'free_tier' => 
            array (
              'neurons_per_day' => 10000,
              'approx_tokens_per_day' => 5000000,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-embedding/emb-cla.md',
                'sha256' => 'be957503605ddc97af43a28adde1a95240ef549297d783e2240049239a4a59bf',
                'start_line' => 205,
                'end_line' => 217,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/02-gem.md',
                'sha256' => '77c5d13b3146293f609cb47811f22848b814e83d2d8ad83e46e5831a1831d4e4',
                'start_line' => 22,
                'end_line' => 24,
              ),
            ),
          ),
        ),
      ),
    ),
    11 => 
    array (
      'slug' => 'deepinfra',
      'display_name' => 'Deepinfra',
      'description' => '',
      'api_key_env' => 'DEEPINFRA_API_KEY',
      'base_url' => 'https://api.deepinfra.com/v1/openai',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    12 => 
    array (
      'slug' => 'deepseek',
      'display_name' => 'Deepseek',
      'description' => '',
      'api_key_env' => 'DEEPSEEK_API_KEY',
      'base_url' => 'https://api.deepseek.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    13 => 
    array (
      'slug' => 'dify',
      'display_name' => 'Dify Orchestrator',
      'description' => 'Self-hostable RAG + automation builder with visual flows.',
      'api_key_env' => NULL,
      'base_url' => NULL,
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => true,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => 
      array (
        'type' => 'dify',
      ),
      'metadata' => 
      array (
        'tier' => 'self_hosted',
        'latency' => 'dependent',
        'insights' => 
        array (
          'dify-platform' => 
          array (
            'slug' => 'dify-platform',
            'name' => 'Dify Open-Source Orchestrator',
            'category' => 'rag_platform',
            'reset_window' => 'n/a',
            'commercial_use' => 'MIT-licensed self-host; infra costs ($50-200/month) govern production deployments.',
            'notes' => 'Dify ships a Docker-deployable UI with 90k+ GitHub stars, drag-and-drop pipelines, and connectors for 100+ LLM providers. Recommended for teams needing a no-code/low-code RAG builder they can self-host.',
            'modalities' => 
            array (
              0 => 'workflow_builder',
              1 => 'rag',
            ),
            'recommended_roles' => 
            array (
              0 => 'hosted_rag_builder',
              1 => 'visual_pipeline',
            ),
            'free_tier' => 
            array (
              'notes' => 'No vendor-enforced API quota; cost is the underlying Docker/Railway/Render infrastructure.',
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-rag/ragres-07.md',
                'sha256' => '7a3dfd6b76169a6ac833c2005bbd818cd8c29b9739f78a1a815ecb0239b297cd',
                'start_line' => 70,
                'end_line' => 79,
              ),
              1 => 
              array (
                'path' => 'reference/research-rag/ragres-02.md',
                'sha256' => 'fca0ce92dab93bc939ce7301a281a03a1fa023152c50182ccbeea31dd331b105',
                'start_line' => 70,
                'end_line' => 116,
              ),
            ),
          ),
        ),
      ),
    ),
    14 => 
    array (
      'slug' => 'enfer',
      'display_name' => 'Enfer',
      'description' => '',
      'api_key_env' => 'ENFER_API_KEY',
      'base_url' => 'https://api.enfer.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    15 => 
    array (
      'slug' => 'featherless',
      'display_name' => 'Featherless',
      'description' => '',
      'api_key_env' => 'FEATHERLESS_API_KEY',
      'base_url' => 'https://api.featherless.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    16 => 
    array (
      'slug' => 'fireworks',
      'display_name' => 'Fireworks',
      'description' => '',
      'api_key_env' => 'FIREWORKS_API_KEY',
      'base_url' => 'https://api.fireworks.ai/inference/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    17 => 
    array (
      'slug' => 'friendli',
      'display_name' => 'Friendli',
      'description' => '',
      'api_key_env' => 'FRIENDLI_TOKEN',
      'base_url' => 'https://api.friendli.ai/serverless/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    18 => 
    array (
      'slug' => 'gemini',
      'display_name' => 'Google Gemini',
      'description' => 'Gemini API with File Search and text-embedding-004.',
      'api_key_env' => 'GOOGLE_API_KEY',
      'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => true,
        'byok' => true,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
        'text-embedding-004' => 3072,
      ),
      'preferred_vector_store' => 'gemini-file-search',
      'default_models' => 
      array (
        'generation' => 'gemini-2.0-flash-exp',
        'embedding' => 'text-embedding-004',
      ),
      'default_solution' => 
      array (
        'type' => 'gemini-file-search',
        'vector_store' => 
        array (
          'corpus' => 'default',
        ),
      ),
      'metadata' => 
      array (
        'tier' => 'paid',
        'latency' => 'medium',
        'insights' => 
        array (
          'google-gemini-flash' => 
          array (
            'slug' => 'google-gemini-flash',
            'name' => 'Google AI Studio – Gemini 2.5 Flash',
            'category' => 'llm',
            'reset_window' => 'daily_midnight_pt',
            'commercial_use' => 'Permits production prototyping without a credit card; governed by Gemini API ToS.',
            'notes' => 'Gemini 2.5 Flash leads the renewable free tier list with 250 RPD, 10 RPM, and a multimodal 2M-token context. Gemini 2.5 Pro and Gemma models stay available under the same API key for heavier jobs.',
            'modalities' => 
            array (
              0 => 'text',
              1 => 'vision',
              2 => 'audio',
            ),
            'recommended_roles' => 
            array (
              0 => 'frontline_free_llm',
              1 => 'fallback_multimodal',
            ),
            'free_tier' => 
            array (
              'requests_per_day' => 250,
              'requests_per_minute' => 10,
              'tokens_per_minute' => 250000,
              'context_tokens' => 2000000,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/01-gem.md',
                'sha256' => '861c4f4e4f5fef6d088fa1a1987b0084c3e6f0b1aa28e7a6d0d03e87a7559832',
                'start_line' => 110,
                'end_line' => 125,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/01-cla.md',
                'sha256' => 'cb1512796cd191d08bd422eeb56dd112139c6f691eefd988d57f09d3cd675a03',
                'start_line' => 13,
                'end_line' => 16,
              ),
            ),
          ),
          'google-gemini-embedding' => 
          array (
            'slug' => 'google-gemini-embedding',
            'name' => 'Google Gemini Embedding API (gemini-embedding-001)',
            'category' => 'embedding',
            'reset_window' => 'daily',
            'commercial_use' => 'Allowed under Gemini API policies with renewable limits for production workloads.',
            'notes' => 'Google advertises completely free embedding throughput; practical rate limits are 1,000 RPD and 100 RPM, easily covering 20k+ daily embeddings.',
            'modalities' => 
            array (
              0 => 'embedding',
            ),
            'recommended_roles' => 
            array (
              0 => 'frontline_embeddings',
              1 => 'zero_cost_embeddings',
            ),
            'free_tier' => 
            array (
              'requests_per_day' => 1000,
              'requests_per_minute' => 100,
              'tokens_per_minute' => 30000,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/02-gpt.md',
                'sha256' => '590f026b4ff8e485756a5f03d5df1535369a1c9860f72f8ce13f7280921a71fa',
                'start_line' => 158,
                'end_line' => 165,
              ),
              1 => 
              array (
                'path' => 'reference/research-embedding/emb-cla.md',
                'sha256' => 'be957503605ddc97af43a28adde1a95240ef549297d783e2240049239a4a59bf',
                'start_line' => 205,
                'end_line' => 227,
              ),
            ),
          ),
          'google-gemini-file-search' => 
          array (
            'slug' => 'google-gemini-file-search',
            'name' => 'Google Gemini API – File Search Tool',
            'category' => 'rag_tool',
            'reset_window' => 'project_storage_limits',
            'commercial_use' => 'Available to Gemini API projects without additional contracts; follows Gemini API ToS.',
            'notes' => 'File Search is a fully managed RAG pipeline that handles chunking, Gemini Embedding generation, retrieval, and inline citations. Storage plus query-time embeddings are free; only the initial indexing embeddings incur costs, and stores keep latency low when under ~20 GB.',
            'modalities' => 
            array (
              0 => 'rag',
              1 => 'vector_store',
              2 => 'tool',
            ),
            'recommended_roles' => 
            array (
              0 => 'managed_file_rag',
              1 => 'gemini_pool_seed',
              2 => 'citation_first',
            ),
            'free_tier' => 
            array (
              'max_file_mb' => 100,
              'store_quota_gb_free' => 1,
              'store_quota_gb_tier1' => 10,
              'store_quota_gb_tier2' => 100,
              'store_quota_gb_tier3' => 1000,
              'embedding_index_cost_per_million_tokens_usd' => '$0.15',
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-rag/ragres-13.md',
                'sha256' => 'bc9b8d398315e7aecff0cef10d6a39498848d03d2473d394f52c34293ed20005',
                'start_line' => 9,
                'end_line' => 16,
              ),
            ),
          ),
        ),
      ),
    ),
    19 => 
    array (
      'slug' => 'groq',
      'display_name' => 'Groq',
      'description' => 'Ultra-low latency OpenAI-compatible endpoint for Llama/Mixtral.',
      'api_key_env' => 'GROQ_API_KEY',
      'base_url' => 'https://api.groq.com/openai/v1',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => true,
      ),
      'model_count' => 21,
      'models' => 
      array (
        0 => 'allam-2-7b',
        1 => 'deepseek-r1-distill-llama-70b',
        2 => 'gemma2-9b-it',
        3 => 'groq/compound',
        4 => 'groq/compound-mini',
        5 => 'llama-3.1-8b-instant',
        6 => 'llama-3.3-70b-versatile',
        7 => 'meta-llama/llama-4-maverick-17b-128e-instruct',
        8 => 'meta-llama/llama-4-scout-17b-16e-instruct',
        9 => 'meta-llama/llama-guard-4-12b',
        10 => 'meta-llama/llama-prompt-guard-2-22m',
        11 => 'meta-llama/llama-prompt-guard-2-86m',
        12 => 'moonshotai/kimi-k2-instruct',
        13 => 'moonshotai/kimi-k2-instruct-0905',
        14 => 'openai/gpt-oss-20b',
        15 => 'openai/gpt-oss-120b',
        16 => 'playai-tts',
        17 => 'playai-tts-arabic',
        18 => 'qwen/qwen3-32b',
        19 => 'whisper-large-v3',
        20 => 'whisper-large-v3-turbo',
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'ragie',
      'default_models' => 
      array (
        'generation' => 'llama-3.1-70b-versatile',
        'fast_generation' => 'llama-3.1-8b-instant',
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'low',
        'insights' => 
        array (
          'groq-llama' => 
          array (
            'slug' => 'groq-llama',
            'name' => 'Groq Cloud – Llama family',
            'category' => 'llm',
            'reset_window' => 'daily',
            'commercial_use' => 'Explicitly allowed for production once workloads fit inside free tiers; higher paid tiers available.',
            'notes' => 'Groq\'s LPU-backed inference prioritizes speed with 14,400 RPD for Llama 3.1 8B, 1,000 RPD for Llama 3.3 70B, and 2,000 RPD for Whisper v3 transcription, all resetting daily.',
            'modalities' => 
            array (
              0 => 'text',
              1 => 'audio',
            ),
            'recommended_roles' => 
            array (
              0 => 'latency_critical_llm',
              1 => 'fallback_llm',
            ),
            'free_tier' => 
            array (
              'requests_per_minute' => 40,
              'models' => 
              array (
                0 => 
                array (
                  'model' => 'llama-3.1-8b',
                  'requests_per_day' => 14400,
                  'tokens_per_minute' => 6000,
                ),
                1 => 
                array (
                  'model' => 'llama-3.3-70b',
                  'requests_per_day' => 1000,
                  'tokens_per_minute' => 12000,
                ),
              ),
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/01-gem.md',
                'sha256' => '861c4f4e4f5fef6d088fa1a1987b0084c3e6f0b1aa28e7a6d0d03e87a7559832',
                'start_line' => 152,
                'end_line' => 160,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/01-cla.md',
                'sha256' => 'cb1512796cd191d08bd422eeb56dd112139c6f691eefd988d57f09d3cd675a03',
                'start_line' => 15,
                'end_line' => 19,
              ),
            ),
          ),
        ),
      ),
    ),
    20 => 
    array (
      'slug' => 'huggingface',
      'display_name' => 'Huggingface',
      'description' => '',
      'api_key_env' => 'HUGGINGFACEHUB_API_TOKEN',
      'base_url' => 'https://router.huggingface.co/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    21 => 
    array (
      'slug' => 'hyperbolic',
      'display_name' => 'Hyperbolic',
      'description' => '',
      'api_key_env' => 'HYPERBOLIC_API_KEY',
      'base_url' => 'https://api.hyperbolic.xyz/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    22 => 
    array (
      'slug' => 'inference',
      'display_name' => 'Inference',
      'description' => '',
      'api_key_env' => 'INFERENCENET_API_KEY',
      'base_url' => 'https://api.inference.net/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    23 => 
    array (
      'slug' => 'infermatic',
      'display_name' => 'Infermatic',
      'description' => '',
      'api_key_env' => 'INFERMATIC_API_KEY',
      'base_url' => 'https://api.totalgpt.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    24 => 
    array (
      'slug' => 'litellm',
      'display_name' => 'Litellm',
      'description' => '',
      'api_key_env' => NULL,
      'base_url' => 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    25 => 
    array (
      'slug' => 'llm7',
      'display_name' => 'Llm7',
      'description' => '',
      'api_key_env' => 'LLM7_API_KEY',
      'base_url' => 'https://api.llm7.io/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    26 => 
    array (
      'slug' => 'lmstudio',
      'display_name' => 'Lmstudio',
      'description' => '',
      'api_key_env' => 'LMSTUDIO_API_KEY',
      'base_url' => 'http://othello.local:1234/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    27 => 
    array (
      'slug' => 'mancer',
      'display_name' => 'Mancer',
      'description' => '',
      'api_key_env' => 'MANCER_API_KEY',
      'base_url' => 'https://neuro.mancer.tech/oai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    28 => 
    array (
      'slug' => 'mistral',
      'display_name' => 'Mistral AI',
      'description' => 'La Plateforme access to Mixtral + Large models under the experiment tier.',
      'api_key_env' => 'MISTRAL_API_KEY',
      'base_url' => 'https://api.mistral.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'ragie',
      'default_models' => 
      array (
        'generation' => 'mistral-large-latest',
        'fast_generation' => 'mistral-small-latest',
        'embedding' => 'mistral-embed',
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
        'insights' => 
        array (
          'mistral-la-plateforme' => 
          array (
            'slug' => 'mistral-la-plateforme',
            'name' => 'Mistral La Plateforme – Experiment tier',
            'category' => 'llm',
            'reset_window' => 'hybrid_daily_monthly',
            'commercial_use' => 'Free tier restricted to prototyping with opt-in data training; production traffic requires upgrade.',
            'notes' => 'Mistral confirms a renewable but restrictive experiment tier (~1B tokens/day, 500K TPM, 1 RPS) plus a Codestral-specific cap of 2,000 RPD. Actual dashboards hide precise per-model numbers until signup.',
            'modalities' => 
            array (
              0 => 'text',
              1 => 'code',
              2 => 'vision',
            ),
            'recommended_roles' => 
            array (
              0 => 'eu_resident_llm',
              1 => 'evaluation_only',
            ),
            'free_tier' => 
            array (
              'tokens_per_day' => 1000000000,
              'tokens_per_minute' => 500000,
              'requests_per_second' => 1,
              'codestral_requests_per_day' => 2000,
              'codestral_requests_per_minute' => 30,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/01-gem.md',
                'sha256' => '861c4f4e4f5fef6d088fa1a1987b0084c3e6f0b1aa28e7a6d0d03e87a7559832',
                'start_line' => 134,
                'end_line' => 140,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/02-gem.md',
                'sha256' => '77c5d13b3146293f609cb47811f22848b814e83d2d8ad83e46e5831a1831d4e4',
                'start_line' => 15,
                'end_line' => 21,
              ),
            ),
          ),
        ),
      ),
    ),
    29 => 
    array (
      'slug' => 'moonshot',
      'display_name' => 'Moonshot',
      'description' => '',
      'api_key_env' => 'MOONSHOT_API_KEY',
      'base_url' => 'https://api.moonshot.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    30 => 
    array (
      'slug' => 'morphllm',
      'display_name' => 'Morphllm',
      'description' => '',
      'api_key_env' => 'MORPHLLM_API_KEY',
      'base_url' => 'https://api.morphllm.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    31 => 
    array (
      'slug' => 'nebius',
      'display_name' => 'Nebius',
      'description' => '',
      'api_key_env' => 'NEBIUS_API_KEY',
      'base_url' => 'https://api.studio.nebius.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    32 => 
    array (
      'slug' => 'nineteenai',
      'display_name' => 'Nineteenai',
      'description' => '',
      'api_key_env' => 'NINETEENAI_API_KEY',
      'base_url' => 'https://api.nineteen.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    33 => 
    array (
      'slug' => 'novita',
      'display_name' => 'Novita',
      'description' => '',
      'api_key_env' => 'NOVITA_API_KEY',
      'base_url' => 'https://api.novita.ai/v3/openai',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    34 => 
    array (
      'slug' => 'openai',
      'display_name' => 'OpenAI',
      'description' => 'Flagship GPT-4o/4.1 models plus moderation + embeddings.',
      'api_key_env' => 'OPENAI_API_KEY',
      'base_url' => 'https://api.openai.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => false,
        'moderation' => true,
        'image_generation' => true,
        'byok' => false,
      ),
      'model_count' => 83,
      'models' => 
      array (
        0 => 'babbage-002',
        1 => 'chatgpt-4o-latest',
        2 => 'dall-e-2',
        3 => 'dall-e-3',
        4 => 'davinci-002',
        5 => 'gpt-3.5-turbo',
        6 => 'gpt-3.5-turbo-0125',
        7 => 'gpt-3.5-turbo-16k',
        8 => 'gpt-3.5-turbo-1106',
        9 => 'gpt-3.5-turbo-instruct',
        10 => 'gpt-3.5-turbo-instruct-0914',
        11 => 'gpt-4',
        12 => 'gpt-4-0125-preview',
        13 => 'gpt-4-0613',
        14 => 'gpt-4-1106-preview',
        15 => 'gpt-4-turbo',
        16 => 'gpt-4-turbo-2024-04-09',
        17 => 'gpt-4-turbo-preview',
        18 => 'gpt-4.1',
        19 => 'gpt-4.1-2025-04-14',
        20 => 'gpt-4.1-mini',
        21 => 'gpt-4.1-mini-2025-04-14',
        22 => 'gpt-4.1-nano',
        23 => 'gpt-4.1-nano-2025-04-14',
        24 => 'gpt-4o',
        25 => 'gpt-4o-2024-05-13',
        26 => 'gpt-4o-2024-08-06',
        27 => 'gpt-4o-2024-11-20',
        28 => 'gpt-4o-audio-preview',
        29 => 'gpt-4o-audio-preview-2024-10-01',
        30 => 'gpt-4o-audio-preview-2024-12-17',
        31 => 'gpt-4o-audio-preview-2025-06-03',
        32 => 'gpt-4o-mini',
        33 => 'gpt-4o-mini-2024-07-18',
        34 => 'gpt-4o-mini-audio-preview',
        35 => 'gpt-4o-mini-audio-preview-2024-12-17',
        36 => 'gpt-4o-mini-realtime-preview',
        37 => 'gpt-4o-mini-realtime-preview-2024-12-17',
        38 => 'gpt-4o-mini-search-preview',
        39 => 'gpt-4o-mini-search-preview-2025-03-11',
        40 => 'gpt-4o-mini-transcribe',
        41 => 'gpt-4o-mini-tts',
        42 => 'gpt-4o-realtime-preview',
        43 => 'gpt-4o-realtime-preview-2024-10-01',
        44 => 'gpt-4o-realtime-preview-2024-12-17',
        45 => 'gpt-4o-realtime-preview-2025-06-03',
        46 => 'gpt-4o-search-preview',
        47 => 'gpt-4o-search-preview-2025-03-11',
        48 => 'gpt-4o-transcribe',
        49 => 'gpt-5',
        50 => 'gpt-5-2025-08-07',
        51 => 'gpt-5-chat-latest',
        52 => 'gpt-5-mini',
        53 => 'gpt-5-mini-2025-08-07',
        54 => 'gpt-5-nano',
        55 => 'gpt-5-nano-2025-08-07',
        56 => 'gpt-audio',
        57 => 'gpt-audio-2025-08-28',
        58 => 'gpt-image-1',
        59 => 'gpt-realtime',
        60 => 'gpt-realtime-2025-08-28',
        61 => 'o1',
        62 => 'o1-2024-12-17',
        63 => 'o1-mini',
        64 => 'o1-mini-2024-09-12',
        65 => 'o1-pro',
        66 => 'o1-pro-2025-03-19',
        67 => 'o3',
        68 => 'o3-2025-04-16',
        69 => 'o3-mini',
        70 => 'o3-mini-2025-01-31',
        71 => 'o4-mini',
        72 => 'o4-mini-2025-04-16',
        73 => 'omni-moderation-2024-09-26',
        74 => 'omni-moderation-latest',
        75 => 'text-embedding-3-large',
        76 => 'text-embedding-3-small',
        77 => 'text-embedding-ada-002',
        78 => 'tts-1',
        79 => 'tts-1-1106',
        80 => 'tts-1-hd',
        81 => 'tts-1-hd-1106',
        82 => 'whisper-1',
      ),
      'embedding_dimensions' => 
      array (
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
      ),
      'preferred_vector_store' => 'ragie',
      'default_models' => 
      array (
        'generation' => 'gpt-4o-mini',
        'fast_generation' => 'gpt-4o-mini',
        'embedding' => 'text-embedding-3-small',
        'moderation' => 'omni-moderation-latest',
      ),
      'default_solution' => 
      array (
        'type' => 'ragie',
        'ragie_partition' => 'default',
        'default_options' => 
        array (
          'top_k' => 8,
          'rerank' => true,
        ),
      ),
      'metadata' => 
      array (
        'tier' => 'paid',
        'latency' => 'medium',
      ),
    ),
    35 => 
    array (
      'slug' => 'openrouter',
      'display_name' => 'OpenRouter',
      'description' => 'Router for 60+ hosted LLMs with renewable communal credits.',
      'api_key_env' => 'OPENROUTER_API_KEY',
      'base_url' => 'https://openrouter.ai/api/v1',
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => true,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
        'generation' => 'meta-llama/llama-3.1-70b-instruct',
        'fast_generation' => 'qwen/qwen-2.5-14b-instruct',
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
        'insights' => 
        array (
          'openrouter' => 
          array (
            'slug' => 'openrouter',
            'name' => 'OpenRouter Free Aggregator',
            'category' => 'llm_router',
            'reset_window' => 'daily_utc',
            'commercial_use' => 'Allowed but subject to each upstream model license; aggregator enforces data-usage disclosures.',
            'notes' => 'OpenRouter routes to 50+ sponsor-backed models (DeepSeek, Llama, Mistral, Qwen, Gemma). Rate limits apply across the shared :free pool, making it ideal for comparative testing.',
            'modalities' => 
            array (
              0 => 'text',
              1 => 'vision',
              2 => 'audio',
            ),
            'recommended_roles' => 
            array (
              0 => 'multi_model_experiments',
              1 => 'fallback_llm',
            ),
            'free_tier' => 
            array (
              'requests_per_day' => 50,
              'requests_per_minute' => 20,
              'upgrade_requests_per_day' => 1000,
              'upgrade_condition' => '$10 lifetime top-up unlocks 1,000 RPD across :free endpoints',
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/01-cla.md',
                'sha256' => 'cb1512796cd191d08bd422eeb56dd112139c6f691eefd988d57f09d3cd675a03',
                'start_line' => 18,
                'end_line' => 21,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/02-gem.md',
                'sha256' => '77c5d13b3146293f609cb47811f22848b814e83d2d8ad83e46e5831a1831d4e4',
                'start_line' => 13,
                'end_line' => 20,
              ),
            ),
          ),
        ),
      ),
    ),
    36 => 
    array (
      'slug' => 'parasail',
      'display_name' => 'Parasail',
      'description' => '',
      'api_key_env' => 'PARASAIL_API_KEY',
      'base_url' => 'https://api.parasail.io/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    37 => 
    array (
      'slug' => 'perplexity',
      'display_name' => 'Perplexity',
      'description' => '',
      'api_key_env' => 'PERPLEXITYAI_API_KEY',
      'base_url' => 'https://api.perplexity.ai',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    38 => 
    array (
      'slug' => 'pinecone',
      'display_name' => 'Pinecone',
      'description' => 'Managed vector store with starter pods for prototypes.',
      'api_key_env' => 'PINECONE_API_KEY',
      'base_url' => 'https://controller.us-east1-gcp.pinecone.io',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'pinecone',
      'default_models' => 
      array (
      ),
      'default_solution' => 
      array (
        'type' => 'pinecone',
      ),
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
        'insights' => 
        array (
          'pinecone-starter' => 
          array (
            'slug' => 'pinecone-starter',
            'name' => 'Pinecone Starter Vector DB',
            'category' => 'vector_database',
            'reset_window' => 'monthly',
            'commercial_use' => 'Starter tier supports production prototypes; additional throughput available via paid serverless tiers.',
            'notes' => 'Pinecone\'s Starter tier offers 2 GB storage, around a million 768-d vectors, and millions of monthly read/write units focused on AWS us-east-1. Ideal for validating retrieval pipelines before upgrading.',
            'modalities' => 
            array (
              0 => 'vector_store',
            ),
            'recommended_roles' => 
            array (
              0 => 'primary_vector_store',
              1 => 'starter_rag_stack',
            ),
            'free_tier' => 
            array (
              'storage_gb' => 2,
              'indexes' => 1,
              'write_units_per_month' => 2000000,
              'read_units_per_month' => 1000000,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/02-gpt.md',
                'sha256' => '590f026b4ff8e485756a5f03d5df1535369a1c9860f72f8ce13f7280921a71fa',
                'start_line' => 166,
                'end_line' => 174,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/02-gem.md',
                'sha256' => '77c5d13b3146293f609cb47811f22848b814e83d2d8ad83e46e5831a1831d4e4',
                'start_line' => 73,
                'end_line' => 78,
              ),
            ),
          ),
        ),
      ),
    ),
    39 => 
    array (
      'slug' => 'poe',
      'display_name' => 'Poe',
      'description' => '',
      'api_key_env' => 'POE_API_KEY',
      'base_url' => 'https://api.poe.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    40 => 
    array (
      'slug' => 'pollinations',
      'display_name' => 'Pollinations',
      'description' => '',
      'api_key_env' => 'POLLINATIONS_API_KEY',
      'base_url' => 'https://text.pollinations.ai/openai',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    41 => 
    array (
      'slug' => 'qdrant',
      'display_name' => 'Qdrant Serverless',
      'description' => 'Fully-managed vector store with forever-free tier.',
      'api_key_env' => 'QDRANT_API_KEY',
      'base_url' => 'https://api.qdrant.tech',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'qdrant',
      'default_models' => 
      array (
      ),
      'default_solution' => 
      array (
        'type' => 'qdrant',
      ),
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
        'insights' => 
        array (
          'qdrant-cloud-free' => 
          array (
            'slug' => 'qdrant-cloud-free',
            'name' => 'Qdrant Cloud Free Cluster',
            'category' => 'vector_database',
            'reset_window' => 'permanent',
            'commercial_use' => 'Free forever for active clusters; production allowed while usage stays within limits.',
            'notes' => 'Qdrant Cloud\'s 1 GB permanent tier covers roughly one million 768-d vectors and needs periodic traffic to avoid suspension, making it a perfect safety net for budget RAG deployments.',
            'modalities' => 
            array (
              0 => 'vector_store',
            ),
            'recommended_roles' => 
            array (
              0 => 'forever_free_vector',
              1 => 'fallback_vector_store',
            ),
            'free_tier' => 
            array (
              'storage_gb' => 1,
              'vector_capacity' => 1000000,
              'suspension_policy' => 'Clusters pause after ~7 idle days and purge after ~4 weeks of inactivity',
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-ai-api/02-gpt.md',
                'sha256' => '590f026b4ff8e485756a5f03d5df1535369a1c9860f72f8ce13f7280921a71fa',
                'start_line' => 171,
                'end_line' => 175,
              ),
              1 => 
              array (
                'path' => 'reference/research-ai-api/02-gem.md',
                'sha256' => '77c5d13b3146293f609cb47811f22848b814e83d2d8ad83e46e5831a1831d4e4',
                'start_line' => 75,
                'end_line' => 78,
              ),
            ),
          ),
        ),
      ),
    ),
    42 => 
    array (
      'slug' => 'ragie',
      'display_name' => 'Ragie',
      'description' => 'Primary retrieval layer powering ParaGra.',
      'api_key_env' => 'RAGIE_API_KEY',
      'base_url' => 'https://api.ragie.ai',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => 'ragie',
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'paid',
        'latency' => 'medium',
      ),
    ),
    43 => 
    array (
      'slug' => 'redpill',
      'display_name' => 'Redpill',
      'description' => '',
      'api_key_env' => 'REDPILL_API_KEY',
      'base_url' => 'https://api.redpill.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    44 => 
    array (
      'slug' => 'sambanova',
      'display_name' => 'Sambanova',
      'description' => '',
      'api_key_env' => 'SAMBANOVA_API_KEY',
      'base_url' => 'https://api.sambanova.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    45 => 
    array (
      'slug' => 'siliconflow',
      'display_name' => 'Siliconflow',
      'description' => '',
      'api_key_env' => 'SILICONFLOW_API_KEY',
      'base_url' => 'https://api.siliconflow.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    46 => 
    array (
      'slug' => 'targon',
      'display_name' => 'Targon',
      'description' => '',
      'api_key_env' => 'TARGON_API_KEY',
      'base_url' => 'https://api.targon.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    47 => 
    array (
      'slug' => 'togetherai',
      'display_name' => 'Togetherai',
      'description' => '',
      'api_key_env' => 'TOGETHERAI_API_KEY',
      'base_url' => 'https://api.together.xyz/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    48 => 
    array (
      'slug' => 'vectara',
      'display_name' => 'Vectara',
      'description' => 'Managed RAG stack with ingestion, hallucination defense, and Cerebras/OpenAI routing.',
      'api_key_env' => NULL,
      'base_url' => NULL,
      'capabilities' => 
      array (
        'llm_chat' => true,
        'embeddings' => true,
        'vector_store' => true,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'hosted',
        'latency' => 'medium',
        'insights' => 
        array (
          'vectara-platform' => 
          array (
            'slug' => 'vectara-platform',
            'name' => 'Vectara Managed RAG Platform',
            'category' => 'hosted_rag',
            'reset_window' => 'subscription',
            'commercial_use' => 'SOC 2/ISO-ready managed service with 30-day evaluation followed by paid contracts.',
            'notes' => 'Vectara bundles ingestion, hallucination defenses, and routing into Cerebras/OpenAI stacks so teams can deploy full RAG chatbots in hours. The managed backend handles chunking, citation UX, and autoscaling once the paid tier activates.',
            'modalities' => 
            array (
              0 => 'rag',
              1 => 'llm_router',
            ),
            'recommended_roles' => 
            array (
              0 => 'hosted_enterprise_rag',
              1 => 'hallucination_guardrails',
            ),
            'free_tier' => 
            array (
              'trial_days' => 30,
              'estimated_monthly_cost_usd_min' => 500,
              'estimated_monthly_cost_usd_max' => 2000,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-rag/ragres-07.md',
                'sha256' => '7a3dfd6b76169a6ac833c2005bbd818cd8c29b9739f78a1a815ecb0239b297cd',
                'start_line' => 70,
                'end_line' => 80,
              ),
              1 => 
              array (
                'path' => 'reference/research-rag/ragres-07.md',
                'sha256' => '7a3dfd6b76169a6ac833c2005bbd818cd8c29b9739f78a1a815ecb0239b297cd',
                'start_line' => 185,
                'end_line' => 210,
              ),
            ),
          ),
        ),
      ),
    ),
    49 => 
    array (
      'slug' => 'voyage',
      'display_name' => 'Voyage AI',
      'description' => 'High-quality embeddings + rerankers tuned for reasoning tasks.',
      'api_key_env' => 'VOYAGE_API_KEY',
      'base_url' => 'https://api.voyageai.com/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => true,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
        'voyage-3-large' => 3072,
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
        'embedding' => 'voyage-3-large',
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
        'tier' => 'free',
        'latency' => 'medium',
        'insights' => 
        array (
          'voyage-embeddings' => 
          array (
            'slug' => 'voyage-embeddings',
            'name' => 'Voyage AI Embedding API',
            'category' => 'embedding',
            'reset_window' => 'one_time_credit_then_payg',
            'commercial_use' => 'Allowed; generous starter credit encourages production migrations after testing.',
            'notes' => 'Voyage grants 200M free tokens on signup, then charges roughly $0.10–$0.40 per million tokens for voyage-3-large, making it ideal for massive initial indexing jobs before pay-as-you-go kicks in.',
            'modalities' => 
            array (
              0 => 'embedding',
              1 => 'rerank',
            ),
            'recommended_roles' => 
            array (
              0 => 'high_volume_embeddings',
              1 => 'code_search',
            ),
            'free_tier' => 
            array (
              'one_time_tokens' => 200000000,
              'paid_rate_per_million_tokens_usd_min' => 0,
              'paid_rate_per_million_tokens_usd_max' => 0,
            ),
            'sources' => 
            array (
              0 => 
              array (
                'path' => 'reference/research-embedding/emb-gro.md',
                'sha256' => '79fbcaffbd639353efd330596d8d5c1421df7df2d2b2724f69770816b3effa3b',
                'start_line' => 55,
                'end_line' => 70,
              ),
            ),
          ),
        ),
      ),
    ),
    50 => 
    array (
      'slug' => 'xai',
      'display_name' => 'Xai',
      'description' => '',
      'api_key_env' => 'XAI_API_KEY',
      'base_url' => 'https://api.x.ai/v1',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
    51 => 
    array (
      'slug' => 'zai',
      'display_name' => 'Zai',
      'description' => '',
      'api_key_env' => 'ZAI_API_KEY',
      'base_url' => 'https://api.z.ai/api/paas/v4',
      'capabilities' => 
      array (
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
      ),
      'model_count' => 0,
      'models' => 
      array (
      ),
      'embedding_dimensions' => 
      array (
      ),
      'preferred_vector_store' => NULL,
      'default_models' => 
      array (
      ),
      'default_solution' => NULL,
      'metadata' => 
      array (
      ),
    ),
  ),
);
