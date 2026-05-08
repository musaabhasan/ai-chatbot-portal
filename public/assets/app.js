const form = document.querySelector('#chatForm');
const input = document.querySelector('#message');
const messages = document.querySelector('#messages');
const statusText = document.querySelector('#status');
const provider = document.querySelector('#provider');
const intentSignal = document.querySelector('#intentSignal');
const firewallSignal = document.querySelector('#firewallSignal');
const routeSignal = document.querySelector('#routeSignal');
const evaluationScore = document.querySelector('#evaluationScore');
const providerMode = document.querySelector('#providerMode');
const lastProvider = document.querySelector('#lastProvider');
const latencySignal = document.querySelector('#latencySignal');
const evidenceSignal = document.querySelector('#evidenceSignal');
const tokenSignal = document.querySelector('#tokenSignal');
const costSignal = document.querySelector('#costSignal');
const routeList = document.querySelector('#routeList');
const firewallDetail = document.querySelector('#firewallDetail');
const riskDetail = document.querySelector('#riskDetail');
const activityLog = document.querySelector('#activityLog');
const newChat = document.querySelector('#newChat');

let conversationId = null;

function icon(name) {
  return `<i data-lucide="${name}"></i>`;
}

function renderIcons() {
  if (window.lucide) {
    window.lucide.createIcons();
  }
}

function addActivity(text) {
  const item = document.createElement('li');
  item.textContent = text;
  activityLog.prepend(item);
  while (activityLog.children.length > 5) {
    activityLog.lastElementChild.remove();
  }
}

function addMessage(kind, text, meta = '', chips = []) {
  const item = document.createElement('article');
  item.className = `message ${kind === 'user' ? 'user-message' : 'assistant-message'}`;

  const avatar = document.createElement('div');
  avatar.className = 'message-avatar';
  avatar.innerHTML = icon(kind === 'user' ? 'user' : 'bot');

  const body = document.createElement('div');
  body.className = 'message-body';
  const paragraph = document.createElement('p');
  paragraph.textContent = text;
  body.appendChild(paragraph);

  if (meta) {
    const small = document.createElement('p');
    small.className = 'mt-3 text-xs opacity-70';
    small.textContent = meta;
    body.appendChild(small);
  }

  if (chips.length > 0) {
    const strip = document.createElement('div');
    strip.className = 'message-meta';
    chips.forEach((chip) => {
      const pill = document.createElement('span');
      pill.textContent = chip;
      strip.appendChild(pill);
    });
    body.appendChild(strip);
  }

  if (kind === 'user') {
    item.appendChild(body);
    item.appendChild(avatar);
  } else {
    item.appendChild(avatar);
    item.appendChild(body);
  }

  messages.appendChild(item);
  messages.scrollTop = messages.scrollHeight;
  renderIcons();
}

function formatLabel(value) {
  if (!value) return 'General';
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function renderRoute(route) {
  routeList.replaceChildren();
  const values = Array.isArray(route) && route.length ? route : ['Auto fallback'];
  values.forEach((value) => {
    const pill = document.createElement('span');
    pill.textContent = value;
    routeList.appendChild(pill);
  });
}

async function loadEvaluationScore() {
  try {
    const response = await fetch('/api/evaluation/sample');
    const payload = await response.json();
    if (response.ok) {
      evaluationScore.textContent = `${payload.passed}/${payload.total} checks`;
      addActivity('Evaluation pack loaded');
    }
  } catch (_) {
    evaluationScore.textContent = 'Offline';
  }
}

document.querySelectorAll('[data-prompt]').forEach((button) => {
  button.addEventListener('click', () => {
    input.value = button.dataset.prompt || '';
    input.focus();
  });
});

newChat.addEventListener('click', () => {
  conversationId = null;
  messages.querySelectorAll('.message:not(:first-child)').forEach((message) => message.remove());
  statusText.textContent = 'New session';
  lastProvider.textContent = 'No provider call';
  latencySignal.textContent = '0 ms';
  evidenceSignal.textContent = '0';
  tokenSignal.textContent = '0 / 0';
  costSignal.textContent = '$0.0000';
  intentSignal.textContent = 'Waiting';
  firewallSignal.textContent = 'Normal';
  routeSignal.textContent = 'Auto';
  renderRoute([]);
  addActivity('New session started');
});

provider.addEventListener('change', () => {
  providerMode.textContent = provider.value || 'Auto';
  addActivity(provider.value ? `Provider pinned to ${provider.value}` : 'Provider fallback restored');
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  addMessage('user', text);
  input.value = '';
  statusText.textContent = 'Routing request...';
  addActivity('Request submitted');

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
    const citations = payload.citations || [];
    const citationText = citations.map((item) => `${item.document} chunk ${item.chunk}`).join('; ');
    const intelligence = payload.intelligence || {};
    const intent = intelligence.intent || {};
    const firewall = intelligence.firewall || {};
    const route = intelligence.route || [];
    const usage = payload.usage || {};

    intentSignal.textContent = formatLabel(intent.intent);
    firewallSignal.textContent = formatLabel(firewall.risk_level || 'normal');
    routeSignal.textContent = Array.isArray(route) && route.length ? route[0] : 'Auto';
    lastProvider.textContent = `${payload.provider} / ${payload.model}`;
    latencySignal.textContent = `${usage.latency_ms || 0} ms`;
    evidenceSignal.textContent = String(citations.length);
    tokenSignal.textContent = `${usage.input_tokens || 0} / ${usage.output_tokens || 0}`;
    costSignal.textContent = `$${Number(usage.cost_usd || 0).toFixed(4)}`;
    firewallDetail.textContent = firewall.allowed === false ? 'Blocked' : 'Allowed';
    riskDetail.textContent = formatLabel(firewall.risk_level || 'normal');
    renderRoute(route);

    const chips = [
      intent.intent ? `Intent: ${formatLabel(intent.intent)}` : null,
      firewall.risk_level ? `Risk: ${formatLabel(firewall.risk_level)}` : null,
      Array.isArray(route) && route.length ? `Route: ${route.join(' > ')}` : null,
      citations.length ? `Citations: ${citations.length}` : null,
    ].filter(Boolean);

    addMessage('assistant', payload.answer, `${payload.provider} / ${payload.model} / ${usage.latency_ms || 0} ms${citationText ? ' / ' + citationText : ''}`, chips);
    statusText.textContent = 'Ready';
    addActivity(`Answered via ${payload.provider}`);
  } catch (error) {
    addMessage('assistant', `The request could not be completed: ${error.message}`);
    statusText.textContent = 'Error';
    addActivity('Request failed');
  }
});

loadEvaluationScore();
renderRoute([]);
renderIcons();
