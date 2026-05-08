CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(64) NOT NULL DEFAULT 'end_user',
    department_id BIGINT UNSIGNED NULL,
    mfa_secret VARBINARY(255) NULL,
    mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    code VARCHAR(80) NOT NULL UNIQUE,
    default_bot_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    encrypted_api_key VARBINARY(2048) NOT NULL,
    base_url VARCHAR(500) NULL,
    model VARCHAR(120) NOT NULL,
    embedding_model VARCHAR(120) NULL,
    priority_order INT NOT NULL DEFAULT 100,
    timeout_seconds INT NOT NULL DEFAULT 45,
    circuit_open_until DATETIME NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX provider_active_idx (provider, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bot_instances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    persona VARCHAR(120) NOT NULL,
    default_provider VARCHAR(40) NOT NULL,
    fallback_providers JSON NOT NULL,
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.20,
    top_p DECIMAL(3,2) NOT NULL DEFAULT 0.90,
    max_output_tokens INT NOT NULL DEFAULT 1200,
    rag_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    moderation_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    retention_days INT NOT NULL DEFAULT 180,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prompt_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    version INT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    title VARCHAR(180) NOT NULL,
    system_prompt MEDIUMTEXT NOT NULL,
    evaluation_notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY bot_version_unique (bot_id, version),
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branding_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    logo_url VARCHAR(500) NULL,
    primary_color CHAR(7) NOT NULL DEFAULT '#0f766e',
    accent_color CHAR(7) NOT NULL DEFAULT '#2563eb',
    background_color CHAR(7) NOT NULL DEFAULT '#f8fafc',
    text_color CHAR(7) NOT NULL DEFAULT '#111827',
    font_family VARCHAR(180) NOT NULL DEFAULT 'Inter, system-ui, sans-serif',
    support_url VARCHAR(500) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversations (
    id CHAR(36) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    bot_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    total_input_tokens INT NOT NULL DEFAULT 0,
    total_output_tokens INT NOT NULL DEFAULT 0,
    total_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE,
    INDEX conversation_bot_idx (bot_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    role VARCHAR(40) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(120) NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    latency_ms INT NULL,
    safety_flags JSON NULL,
    citations JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX message_conversation_idx (conversation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE knowledge_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'uploaded',
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    indexed_at DATETIME NULL,
    UNIQUE KEY bot_document_hash_unique (bot_id, sha256),
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE knowledge_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT NOT NULL,
    content MEDIUMTEXT NOT NULL,
    token_estimate INT NOT NULL DEFAULT 0,
    embedding_model VARCHAR(120) NOT NULL,
    embedding_vector JSON NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY document_chunk_unique (document_id, chunk_index),
    FOREIGN KEY (document_id) REFERENCES knowledge_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(40) NOT NULL,
    model VARCHAR(120) NOT NULL,
    bot_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    conversation_id CHAR(36) NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
    latency_ms INT NOT NULL DEFAULT 0,
    successful BOOLEAN NOT NULL DEFAULT TRUE,
    error_code VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX usage_provider_date_idx (provider, created_at),
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(160) NOT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id VARCHAR(120) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(500) NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX audit_actor_date_idx (actor_id, created_at),
    INDEX audit_entity_idx (entity_type, entity_id),
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    limit_key VARCHAR(255) NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    UNIQUE KEY rate_limit_window_unique (limit_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_scenarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NULL,
    scenario_key VARCHAR(120) NOT NULL UNIQUE,
    persona VARCHAR(120) NOT NULL,
    input_prompt TEXT NOT NULL,
    expected_behavior TEXT NOT NULL,
    risk_tags JSON NOT NULL,
    minimum_evidence JSON NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bot_id BIGINT UNSIGNED NOT NULL,
    prompt_template_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    model VARCHAR(120) NOT NULL,
    score DECIMAL(5,4) NOT NULL DEFAULT 0,
    passed INT NOT NULL DEFAULT 0,
    failed INT NOT NULL DEFAULT 0,
    report_json JSON NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bot_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (prompt_template_id) REFERENCES prompt_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    rating TINYINT NOT NULL,
    feedback_type VARCHAR(80) NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments (name, code) VALUES ('Default Department', 'default');

INSERT INTO bot_instances (
    department_id,
    name,
    slug,
    persona,
    default_provider,
    fallback_providers,
    temperature,
    top_p
) VALUES (
    1,
    'Institutional Assistant',
    'institutional-assistant',
    'Academic and IT support assistant',
    'openai',
    JSON_ARRAY('openai', 'gemini', 'deepseek'),
    0.20,
    0.90
);

INSERT INTO branding_profiles (bot_id) VALUES (1);
