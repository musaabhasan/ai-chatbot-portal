<?php

declare(strict_types=1);

use ChatbotPortal\Admin\BrandingService;
use ChatbotPortal\AI\ChatOrchestrator;
use ChatbotPortal\AI\DeepSeekClient;
use ChatbotPortal\AI\DemoClient;
use ChatbotPortal\AI\GeminiClient;
use ChatbotPortal\AI\IntentClassifier;
use ChatbotPortal\AI\OpenAIClient;
use ChatbotPortal\AI\ProviderRouter;
use ChatbotPortal\Analytics\UsageRecorder;
use ChatbotPortal\Evaluation\EvaluationRunner;
use ChatbotPortal\Http\Router;
use ChatbotPortal\Http\SecurityHeaders;
use ChatbotPortal\Infrastructure\Connection;
use ChatbotPortal\Rag\MySqlVectorStore;
use ChatbotPortal\Rag\Retriever;
use ChatbotPortal\Security\PromptFirewall;
use ChatbotPortal\Support\Env;
use ChatbotPortal\Support\JsonResponse;
use ChatbotPortal\Support\Uuid;

require dirname(__DIR__) . '/src/bootstrap.php';

session_name(Env::get('SESSION_NAME', 'ai_chatbot_portal') ?? 'ai_chatbot_portal');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

SecurityHeaders::apply();

$router = new Router();

$router->get('/', static function (): void {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/portal.html');
});

$router->get('/branding.css', static function (): void {
    header('Content-Type: text/css; charset=utf-8');
    try {
        $db = Connection::make();
        echo (new BrandingService($db))->cssForBot($_GET['bot'] ?? 'institutional-assistant');
    } catch (Throwable) {
        echo ":root{--brand-primary:#0f766e;--brand-accent:#2563eb;--brand-background:#f8fafc;--brand-text:#111827;--brand-font:Inter,system-ui,sans-serif;}";
    }
});

$router->get('/api/health', static function (): void {
    JsonResponse::send([
        'status' => 'ok',
        'environment' => Env::get('APP_ENV', 'local'),
        'region' => Env::get('DATA_RESIDENCY_REGION', 'not_configured'),
        'timestamp' => gmdate(DATE_ATOM),
    ]);
});

$router->get('/api/admin/metrics', static function (): void {
    if (!adminAuthorized()) {
        JsonResponse::send(['error' => ['code' => 'forbidden', 'message' => 'Admin authorization is required.']], 403);
        return;
    }

    try {
        $db = Connection::make();
        JsonResponse::send((new UsageRecorder($db))->dashboard());
    } catch (Throwable $exception) {
        JsonResponse::send(['error' => ['code' => 'metrics_unavailable', 'message' => $exception->getMessage()]], 503);
    }
});

$router->post('/api/chat', static function (): void {
    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload) || empty($payload['message'])) {
        JsonResponse::send(['error' => ['code' => 'invalid_request', 'message' => 'Message is required.']], 422);
        return;
    }

    try {
        $db = Connection::make();
        $providers = providerRouter();
        $retriever = new Retriever($providers, new MySqlVectorStore($db));
        $orchestrator = new ChatOrchestrator($db, $providers, $retriever, new UsageRecorder($db), new IntentClassifier(), new PromptFirewall());
        JsonResponse::send($orchestrator->answer(
            (string) ($payload['bot'] ?? 'institutional-assistant'),
            isset($payload['conversation_id']) ? (string) $payload['conversation_id'] : null,
            (string) $payload['message'],
            isset($payload['provider']) && $payload['provider'] !== '' ? (string) $payload['provider'] : null
        ));
    } catch (Throwable $exception) {
        JsonResponse::send([
            'error' => [
                'code' => 'chat_failed',
                'message' => $exception->getMessage(),
                'request_id' => Uuid::v4(),
            ],
        ], 500);
    }
});

$router->get('/api/evaluation/sample', static function (): void {
    try {
        $path = dirname(__DIR__) . '/examples/evaluation-pack.json';
        JsonResponse::send((new EvaluationRunner())->runFile($path));
    } catch (Throwable $exception) {
        JsonResponse::send(['error' => ['code' => 'evaluation_failed', 'message' => $exception->getMessage()]], 500);
    }
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

function providerRouter(): ProviderRouter
{
    $timeout = Env::int('PROVIDER_TIMEOUT_SECONDS', 45);
    $clients = ['demo' => new DemoClient()];

    $openAiKey = Env::get('OPENAI_API_KEY', '');
    if ($openAiKey !== '') {
        $clients['openai'] = new OpenAIClient($openAiKey, Env::get('OPENAI_MODEL', 'gpt-4.1-mini') ?? 'gpt-4.1-mini', $timeout);
    }

    $geminiKey = Env::get('GEMINI_API_KEY', '');
    if ($geminiKey !== '') {
        $clients['gemini'] = new GeminiClient($geminiKey, Env::get('GEMINI_MODEL', 'gemini-1.5-flash') ?? 'gemini-1.5-flash', $timeout);
    }

    $deepSeekKey = Env::get('DEEPSEEK_API_KEY', '');
    if ($deepSeekKey !== '') {
        $clients['deepseek'] = new DeepSeekClient($deepSeekKey, Env::get('DEEPSEEK_MODEL', 'deepseek-chat') ?? 'deepseek-chat', $timeout);
    }

    return new ProviderRouter($clients);
}

function adminAuthorized(): bool
{
    if (Env::get('APP_ENV', 'local') !== 'production') {
        return true;
    }

    $expected = Env::get('ADMIN_API_TOKEN', '');
    $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';

    return $expected !== '' && is_string($provided) && hash_equals($expected, $provided);
}
