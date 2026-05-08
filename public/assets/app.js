const form = document.querySelector('#chatForm');
const input = document.querySelector('#message');
const messages = document.querySelector('#messages');
const statusText = document.querySelector('#status');
const provider = document.querySelector('#provider');
let conversationId = null;

function addMessage(kind, text, meta = '') {
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
  messages.appendChild(item);
  messages.scrollTop = messages.scrollHeight;
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
    addMessage('assistant', payload.answer, `${payload.provider} / ${payload.model} / ${payload.usage.latency_ms} ms${citations ? ' / ' + citations : ''}`);
    statusText.textContent = 'Ready';
  } catch (error) {
    addMessage('assistant', `The request could not be completed: ${error.message}`);
    statusText.textContent = 'Error';
  }
});
