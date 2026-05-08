const form = document.querySelector('#chatForm');
const input = document.querySelector('#message');
const messages = document.querySelector('#messages');
const statusText = document.querySelector('#status');
const provider = document.querySelector('#provider');
const intentSignal = document.querySelector('#intentSignal');
const firewallSignal = document.querySelector('#firewallSignal');
const routeSignal = document.querySelector('#routeSignal');
const evaluationScore = document.querySelector('#evaluationScore');
let conversationId = null;

function addMessage(kind, text, meta = '', chips = []) {
  const item = document.createElement('article');
  item.className = kind === 'user' ? 'user-message' : 'assistant-message';
  const body = document.createElement('p');
  body.textContent = text;
  item.appendChild(body);
  if (meta) {
    const small = document.createElement('p');
    small.className = 'mt-3 text-xs opacity-70';
    small.textContent = meta;
    item.appendChild(small);
  }
  if (chips.length > 0) {
    const strip = document.createElement('div');
    strip.className = 'message-meta';
    chips.forEach((chip) => {
      const pill = document.createElement('span');
      pill.textContent = chip;
      strip.appendChild(pill);
    });
    item.appendChild(strip);
  }
  messages.appendChild(item);
  messages.scrollTop = messages.scrollHeight;
}

async function loadEvaluationScore() {
  try {
    const response = await fetch('/api/evaluation/sample');
    const payload = await response.json();
    if (response.ok) {
      evaluationScore.textContent = `${payload.passed}/${payload.total} checks`;
    }
  } catch (_) {
    evaluationScore.textContent = 'Offline';
  }
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  addMessage('user', text);
  input.value = '';
  statusText.textContent = 'Thinking...';

  try {
    const response = await fetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        bot: 'institutional-assistant',
        conversation_id: conversationId,
        message: text,
        provider: provider.value || null,
      }),
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error?.message || 'Request failed');
    }

    conversationId = payload.conversation_id;
    const citations = (payload.citations || []).map((item) => `${item.document} chunk ${item.chunk}`).join('; ');
    const intelligence = payload.intelligence || {};
    const intent = intelligence.intent || {};
    const firewall = intelligence.firewall || {};
    const route = intelligence.route || [];
    intentSignal.textContent = intent.intent || 'General';
    firewallSignal.textContent = firewall.risk_level || 'Normal';
    routeSignal.textContent = Array.isArray(route) ? route.join(' > ') : 'Auto';
    const chips = [
      intent.intent ? `Intent: ${intent.intent}` : null,
      firewall.risk_level ? `Risk: ${firewall.risk_level}` : null,
      Array.isArray(route) && route.length ? `Route: ${route.join(' > ')}` : null,
      citations ? `Citations: ${payload.citations.length}` : null,
    ].filter(Boolean);
    addMessage('assistant', payload.answer, `${payload.provider} / ${payload.model} / ${payload.usage.latency_ms} ms${citations ? ' / ' + citations : ''}`, chips);
    statusText.textContent = 'Ready';
  } catch (error) {
    addMessage('assistant', `The request could not be completed: ${error.message}`);
    statusText.textContent = 'Error';
  }
});

loadEvaluationScore();
