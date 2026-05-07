/**
 * MonkeysCMS — Apex AI Assistant (Chat-based Content Editor Sidebar)
 */
import { reactive, http, escapeHtml } from 'monkeysjs';

// ─── State ──────────────────────────────────────────────────────────────────
const state = reactive({
  open: false,
  loading: false,
  messages: [],       // {role:'user'|'ai', content:string, html:boolean}
  error: null,
  enabled: false,
  configured: false,
  features: {},
  fieldConfig: {},
  tone: 'professional',
  language: 'en',
  targetField: 'body',
  tokens: { input: 0, output: 0 },
});

// ─── Check AI Status ────────────────────────────────────────────────────────
async function checkStatus() {
  try {
    const res = await http.get('/api/cms/apex/status');
    state.enabled = res.data?.enabled ?? false;
    state.configured = res.data?.configured ?? false;
    state.features = res.data?.features ?? {};
    return state.enabled && state.configured;
  } catch {
    return false;
  }
}

// ─── Read Form Context ──────────────────────────────────────────────────────
function getFormContext() {
  const form = document.getElementById('content-form');
  if (!form) return {};
  const title = form.querySelector('[name="title"]')?.value || '';
  const wys = document.getElementById('wysiwyg-editor');
  const bodyTa = form.querySelector('[name="body"]');
  const body = (wys && !wys.hidden) ? wys.innerHTML : (bodyTa?.value || '');
  const contentType = form.querySelector('[name="content_type"]')?.value || '';
  const bodyFormat = form.querySelector('[name="body_format"]')?.value || 'html';
  return { title, body, contentType, bodyFormat };
}

// ─── Clean AI response ──────────────────────────────────────────────────────
function cleanContent(text) {
  if (!text) return '';
  return text.replace(/^```(?:html|markdown|plain|text)?\s*\n?/i, '')
             .replace(/\n?```\s*$/i, '')
             .trim();
}

// ─── Build conversation context for the API ─────────────────────────────────
function buildConversationContext() {
  // Send last 6 messages as conversation memory
  const history = state.messages.slice(-6).map(m => ({
    role: m.role === 'user' ? 'user' : 'assistant',
    content: m.content.replace(/<[^>]*>/g, '').substring(0, 500),
  }));
  return history;
}

function buildFormSummary() {
  const ctx = getFormContext();
  const parts = [];
  if (ctx.title) parts.push(`Title: "${ctx.title}"`);
  if (ctx.contentType) parts.push(`Content type: ${ctx.contentType}`);
  if (ctx.bodyFormat) parts.push(`Format: ${ctx.bodyFormat}`);
  if (ctx.body) {
    const wordCount = ctx.body.replace(/<[^>]*>/g, '').split(/\s+/).length;
    parts.push(`Body: ${wordCount} words already written`);
  } else {
    parts.push('Body: empty (new content)');
  }
  return parts.join('. ');
}

// ─── API Call ───────────────────────────────────────────────────────────────
async function sendPrompt(userMessage, payload) {
  state.messages.push({ role: 'user', content: userMessage, html: false });
  state.loading = true;
  state.error = null;
  renderMessages();

  // Inject conversation history and form context
  const history = buildConversationContext();
  const formSummary = buildFormSummary();
  
  // Prepend form context to the prompt
  let enrichedPrompt = payload.prompt;
  if (formSummary) {
    enrichedPrompt = `[Current form state: ${formSummary}]\n\n${enrichedPrompt}`;
  }
  // Append recent conversation so the AI has memory
  if (history.length > 1) {
    const historyText = history.slice(0, -1).map(h => 
      `${h.role === 'user' ? 'User' : 'AI'}: ${h.content}`
    ).join('\n');
    enrichedPrompt = `[Conversation so far:\n${historyText}]\n\n${enrichedPrompt}`;
  }

  try {
    const res = await http.post('/api/cms/apex/generate', {
      ...payload,
      prompt: enrichedPrompt,
    });
    const data = res.data;
    if (data.error) {
      state.error = data.error;
      state.messages.push({ role: 'ai', content: `Error: ${data.error}`, html: false });
    } else {
      const content = cleanContent(data.content || '');
      state.messages.push({ role: 'ai', content, html: true });
      state.tokens = data.tokens || { input: 0, output: 0 };
    }
  } catch (err) {
    state.error = err.message;
    state.messages.push({ role: 'ai', content: `Error: ${err.message}`, html: false });
  } finally {
    state.loading = false;
    renderMessages();
  }
}

// ─── Actions ────────────────────────────────────────────────────────────────
function getPromptText() {
  return document.getElementById('apex-chat-input')?.value?.trim() || '';
}
function clearPrompt() {
  const el = document.getElementById('apex-chat-input');
  if (el) el.value = '';
}

const actions = {
  async generate() {
    const ctx = getFormContext();
    let prompt = getPromptText();
    // If no prompt typed, auto-generate based on context
    if (!prompt) {
      if (ctx.title) {
        prompt = `Write content for: ${ctx.title}`;
      } else {
        state.error = 'Type a prompt or add a title first';
        renderMessages();
        return;
      }
    }
    clearPrompt();
    await sendPrompt(prompt, {
      prompt, title: ctx.title, action: 'generate',
      content_type: ctx.contentType, format: ctx.bodyFormat,
      content: ctx.body || undefined, // include existing body as context
      tone: state.tone, language: state.language,
    });
  },
  async rewrite() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'No content in body to rewrite'; renderMessages(); return; }
    const extra = getPromptText(); clearPrompt();
    const wordCount = ctx.body.replace(/<[^>]*>/g, '').split(/\s+/).length;
    await sendPrompt(extra || `Rewrite body content (${wordCount} words)`, {
      prompt: extra || 'Rewrite this content while keeping the same meaning',
      content: ctx.body, action: 'rewrite', title: ctx.title,
      content_type: ctx.contentType, format: ctx.bodyFormat,
      tone: state.tone, language: state.language,
    });
  },
  async summarize() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'No content to summarize'; renderMessages(); return; }
    const extra = getPromptText(); clearPrompt();
    await sendPrompt(extra || 'Summarize the current article', {
      prompt: extra || 'Create a concise summary of this content',
      content: ctx.body, action: 'summarize', title: ctx.title,
      content_type: ctx.contentType, format: ctx.bodyFormat,
      tone: state.tone, language: state.language,
    });
  },
  async expand() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'No content to expand'; renderMessages(); return; }
    const extra = getPromptText(); clearPrompt();
    await sendPrompt(extra || 'Expand the article with more detail', {
      prompt: extra || 'Expand with more detail, examples, and depth',
      content: ctx.body, action: 'expand', title: ctx.title,
      content_type: ctx.contentType, format: ctx.bodyFormat,
      tone: state.tone, language: state.language,
    });
  },
  async translate() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'No content to translate'; renderMessages(); return; }
    const langLabel = document.querySelector('#apex-language option:checked')?.text || state.language;
    clearPrompt();
    await sendPrompt(`Translate article to ${langLabel}`, {
      prompt: `Translate to ${state.language}`,
      content: ctx.body, action: 'translate', title: ctx.title,
      target_language: state.language, content_type: ctx.contentType, format: ctx.bodyFormat,
    });
  },
  async grammar() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'No content to check'; renderMessages(); return; }
    clearPrompt();
    await sendPrompt('Fix grammar and spelling in body', {
      prompt: 'Fix grammar and spelling errors only, do not change content',
      content: ctx.body, action: 'grammar_check', title: ctx.title,
      content_type: ctx.contentType, format: ctx.bodyFormat,
    });
  },
  async seo() {
    const ctx = getFormContext();
    if (!ctx.body && !ctx.title) { state.error = 'Add title/content first'; renderMessages(); return; }
    clearPrompt();
    await sendSeoCall(ctx);
  },
  async tags() {
    const ctx = getFormContext();
    if (!ctx.body) { state.error = 'Add content first'; renderMessages(); return; }
    clearPrompt();
    await sendTagsCall(ctx);
  },
  async autoFill() {
    const ctx = getFormContext();
    let topic = getPromptText();
    if (!topic && ctx.title) topic = ctx.title;
    if (!topic) { state.error = 'Type a topic or add a title first'; renderMessages(); return; }
    clearPrompt();

    state.messages.push({ role: 'user', content: `Auto-fill all fields for: "${topic}"`, html: false });
    state.loading = true;
    state.error = null;
    renderMessages();

    try {
      const res = await http.post('/api/cms/apex/generate', {
        prompt: `Generate a complete ${ctx.contentType || 'article'} about: "${topic}".

You MUST respond with valid JSON only. No markdown, no code fences, just raw JSON.
Return this exact structure:
{
  "title": "A compelling title",
  "body": "<h2>Heading</h2><p>Full article body in HTML...</p>",
  "summary": "A 1-2 sentence summary",
  "meta_title": "SEO optimized title (max 60 chars)",
  "meta_description": "SEO meta description (max 155 chars)"
}`,
        action: 'generate',
        content_type: ctx.contentType,
        format: ctx.bodyFormat || 'html',
        tone: state.tone,
        language: state.language,
      });

      const raw = cleanContent(res.data?.content || '');
      let fields;
      try {
        fields = JSON.parse(raw);
      } catch {
        // AI didn't return valid JSON — try to extract it
        const jsonMatch = raw.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
          fields = JSON.parse(jsonMatch[0]);
        } else {
          throw new Error('AI did not return structured data. Try again.');
        }
      }

      // Store in state for per-field apply
      state._autoFillData = fields;
      
      // Build a rich message showing each field
      let html = '<div class="apex-autofill">';
      html += '<p style="margin:0 0 0.5rem;color:#a78bfa;font-weight:600;">📝 Generated content for all fields:</p>';
      
      const fieldMap = [
        { key: 'title', label: 'Title', icon: 'type' },
        { key: 'body', label: 'Body', icon: 'file-text' },
        { key: 'summary', label: 'Summary', icon: 'align-left' },
        { key: 'meta_title', label: 'Meta Title', icon: 'search' },
        { key: 'meta_description', label: 'Meta Desc', icon: 'search' },
      ];

      for (const f of fieldMap) {
        if (!fields[f.key]) continue;
        const preview = f.key === 'body'
          ? fields[f.key].replace(/<[^>]*>/g, '').substring(0, 120) + '…'
          : fields[f.key].substring(0, 100);
        html += `<div class="apex-autofill__field">
          <div class="apex-autofill__preview"><strong>${f.label}:</strong> ${escapeHtml(preview)}</div>
          <button type="button" class="apex-autofill__apply" data-field="${f.key}">Apply</button>
        </div>`;
      }
      html += '<button type="button" class="apex-autofill__all">✨ Apply All Fields</button>';
      html += '</div>';

      state.messages.push({ role: 'ai', content: html, html: true });
      state.tokens = res.data?.tokens || { input: 0, output: 0 };
    } catch (err) {
      state.error = err.message;
      state.messages.push({ role: 'ai', content: `Error: ${err.message}`, html: false });
    } finally {
      state.loading = false;
      renderMessages();
      bindAutoFillButtons();
    }
  },
};

async function sendSeoCall(ctx) {
  state.messages.push({ role: 'user', content: 'Generate SEO metadata', html: false });
  state.loading = true; renderMessages();
  try {
    const res = await http.post('/api/cms/apex/seo/meta', {
      content: ctx.body || ctx.title, title: ctx.title, content_type: ctx.contentType,
    });
    const d = res.data;
    if (d.error) { state.messages.push({ role: 'ai', content: `Error: ${d.error}`, html: false }); }
    else {
      state.messages.push({ role: 'ai', content:
        `<strong>Meta Title:</strong> ${d.meta_title||'N/A'}<br><strong>Meta Desc:</strong> ${d.meta_description||'N/A'}<br><strong>Keywords:</strong> ${(d.keywords||[]).join(', ')}`,
        html: true });
    }
  } catch(e) { state.messages.push({ role: 'ai', content: `Error: ${e.message}`, html: false }); }
  finally { state.loading = false; renderMessages(); }
}

async function sendTagsCall(ctx) {
  state.messages.push({ role: 'user', content: 'Suggest taxonomy tags', html: false });
  state.loading = true; renderMessages();
  try {
    const res = await http.post('/api/cms/apex/taxonomy/suggest', {
      content: ctx.body, content_type: ctx.contentType,
    });
    const d = res.data;
    if (d?.suggestions) {
      state.messages.push({ role: 'ai', content:
        d.suggestions.map(s => `${s.name||s} (${Math.round((s.confidence||0.5)*100)}%)`).join('<br>'),
        html: true });
    }
  } catch(e) { state.messages.push({ role: 'ai', content: `Error: ${e.message}`, html: false }); }
  finally { state.loading = false; renderMessages(); }
}

// ─── Auto-fill button bindings ──────────────────────────────────────────────
function bindAutoFillButtons() {
  const container = document.getElementById('apex-messages');
  if (!container || !state._autoFillData) return;

  // Per-field apply buttons
  container.querySelectorAll('.apex-autofill__apply').forEach(btn => {
    btn.onclick = () => {
      const key = btn.dataset.field;
      const val = state._autoFillData[key];
      if (!val) return;

      if (key === 'meta_title') {
        applyContent(val, 'meta_title');
      } else if (key === 'meta_description') {
        applyContent(val, 'meta_description');
      } else {
        applyContent(val, key);
      }
      btn.textContent = '✓';
      btn.disabled = true;
    };
  });

  // Apply all button
  container.querySelectorAll('.apex-autofill__all').forEach(btn => {
    btn.onclick = () => {
      const d = state._autoFillData;
      if (d.title) applyContent(d.title, 'title');
      if (d.body) applyContent(d.body, 'body');
      if (d.summary) applyContent(d.summary, 'summary');
      if (d.meta_title) applyContent(d.meta_title, 'meta_title');
      if (d.meta_description) applyContent(d.meta_description, 'meta_description');
      // Auto-generate slug from title
      if (d.title) {
        const slug = d.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        applyContent(slug, 'slug');
      }
      btn.textContent = '✓ All applied!';
      btn.disabled = true;
      // Mark individual buttons too
      container.querySelectorAll('.apex-autofill__apply').forEach(b => {
        b.textContent = '✓'; b.disabled = true;
      });
    };
  });
}

// ─── Apply to Field ─────────────────────────────────────────────────────────
function applyContent(content, fieldName) {
  fieldName = fieldName || state.targetField;
  const form = document.getElementById('content-form');
  if (!form) return;

  if (fieldName === 'body') {
    const wys = document.getElementById('wysiwyg-editor');
    if (wys && !wys.hidden) {
      wys.innerHTML = content;
      wys.dispatchEvent(new Event('input', { bubbles: true }));
    }
    const ta = form.querySelector('[name="body"]');
    if (ta) { ta.value = content; ta.dispatchEvent(new Event('input', { bubbles: true })); }
  } else {
    const field = form.querySelector(`[name="${fieldName}"]`);
    if (field) {
      field.value = content.replace(/<[^>]*>/g, '').trim();
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }
}

// ─── Render Messages ────────────────────────────────────────────────────────
function renderMessages() {
  const container = document.getElementById('apex-messages');
  const errorEl = document.getElementById('apex-error');
  const progressEl = document.getElementById('apex-progress');
  const tokenEl = document.getElementById('apex-token-count');
  if (!container) return;

  // Error
  if (errorEl) {
    errorEl.hidden = !state.error;
    errorEl.textContent = state.error || '';
  }

  // Progress
  if (progressEl) progressEl.hidden = !state.loading;

  // Tokens
  if (tokenEl && (state.tokens.input || state.tokens.output)) {
    tokenEl.textContent = `↑${state.tokens.input} ↓${state.tokens.output} tokens`;
    tokenEl.parentElement.hidden = false;
  }

  // Messages
  let html = '';
  if (state.messages.length === 0 && !state.loading) {
    html = `<div class="apex-empty">
      <div class="apex-empty__icon"><i data-lucide="sparkles" style="width:28px;height:28px;color:#7c3aed;"></i></div>
      <p class="apex-empty__title">AI Content Assistant</p>
      <p class="apex-empty__hint">Type a prompt below or use an action button to get started.</p>
      <div class="apex-empty__examples">
        <button type="button" class="apex-example" onclick="document.getElementById('apex-chat-input').value='Write an article about…';document.getElementById('apex-chat-input').focus();">Write an article about…</button>
        <button type="button" class="apex-example" onclick="document.getElementById('apex-chat-input').value='Create a product description for…';document.getElementById('apex-chat-input').focus();">Product description…</button>
        <button type="button" class="apex-example" onclick="document.getElementById('apex-chat-input').value='Summarize the key points of…';document.getElementById('apex-chat-input').focus();">Summarize key points…</button>
      </div>
    </div>`;
  }
  for (let i = 0; i < state.messages.length; i++) {
    const m = state.messages[i];
    if (m.role === 'user') {
      html += `<div class="apex-msg apex-msg--user"><div class="apex-msg__bubble">${escapeHtml(m.content)}</div></div>`;
    } else {
      const idx = i;
      html += `<div class="apex-msg apex-msg--ai">
        <div class="apex-msg__bubble apex-msg__bubble--ai">${m.html ? m.content : escapeHtml(m.content)}</div>
        <div class="apex-msg__actions">
          <button type="button" class="apex-insert-btn" data-msg-idx="${idx}" title="Insert into target field">
            <i data-lucide="arrow-down-to-line" style="width:12px;height:12px;"></i> Insert
          </button>
          <button type="button" class="apex-copy-btn" data-msg-idx="${idx}" title="Copy to clipboard">
            <i data-lucide="copy" style="width:12px;height:12px;"></i> Copy
          </button>
        </div>
      </div>`;
    }
  }

  if (state.loading) {
    html += `<div class="apex-msg apex-msg--ai"><div class="apex-msg__bubble apex-msg__bubble--ai apex-msg__typing"><span></span><span></span><span></span></div></div>`;
  }

  container.innerHTML = html;
  container.scrollTop = container.scrollHeight;

  // Bind insert/copy buttons
  container.querySelectorAll('.apex-insert-btn').forEach(btn => {
    btn.onclick = () => {
      const idx = parseInt(btn.dataset.msgIdx);
      const msg = state.messages[idx];
      if (msg) applyContent(msg.content, state.targetField);
    };
  });
  container.querySelectorAll('.apex-copy-btn').forEach(btn => {
    btn.onclick = () => {
      const idx = parseInt(btn.dataset.msgIdx);
      const msg = state.messages[idx];
      if (msg) navigator.clipboard.writeText(msg.content.replace(/<[^>]*>/g, ''));
    };
  });

  if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ─── Toggle ─────────────────────────────────────────────────────────────────
function toggle() {
  state.open = !state.open;
  const sidebar = document.getElementById('apex-sidebar');
  if (sidebar) sidebar.classList.toggle('apex-sidebar--open', state.open);
}

// ─── Build Sidebar ──────────────────────────────────────────────────────────
function createSidebar() {
  if (document.getElementById('apex-sidebar')) return;

  const sidebar = document.createElement('div');
  sidebar.id = 'apex-sidebar';
  sidebar.className = 'apex-sidebar';
  sidebar.innerHTML = `
    <div class="apex-sidebar__header">
      <span class="apex-sidebar__title"><i data-lucide="sparkles" class="w-4 h-4"></i> AI Assistant</span>
      <button type="button" class="btn btn--ghost btn--sm" onclick="window.ApexAssistant.toggle()"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>

    <!-- Settings Bar (compact) -->
    <div class="apex-chat-settings">
      <select class="form-select form-select--sm" id="apex-target-field" title="Target field">
        <option value="body" selected>⎯ Body</option>
        <option value="title">⎯ Title</option>
        <option value="summary">⎯ Summary</option>
      </select>
      <select class="form-select form-select--sm" id="apex-tone" title="Tone">
        <option value="professional">Professional</option>
        <option value="casual">Casual</option>
        <option value="technical">Technical</option>
        <option value="creative">Creative</option>
        <option value="formal">Formal</option>
        <option value="friendly">Friendly</option>
      </select>
      <select class="form-select form-select--sm" id="apex-language" title="Language">
        <option value="en" selected>EN</option>
        <option value="es">ES</option>
        <option value="fr">FR</option>
        <option value="de">DE</option>
        <option value="pt">PT</option>
        <option value="it">IT</option>
        <option value="ja">JA</option>
        <option value="zh">ZH</option>
      </select>
    </div>

    <!-- Chat Messages -->
    <div class="apex-sidebar__body">
      <div id="apex-error" class="alert alert--danger" style="margin:0.5rem;font-size:0.75rem;" hidden></div>
      <div id="apex-messages" class="apex-messages"></div>
      <div id="apex-progress" class="apex-progress" hidden>
        <div class="apex-progress__bar"></div>
        <span class="apex-progress__text">Generating…</span>
      </div>
      <div id="apex-tokens" style="padding:0 0.75rem;font-size:0.65rem;color:#666;" hidden>
        <span id="apex-token-count"></span>
      </div>
    </div>

    <!-- Chat Input + Actions (Bottom) -->
    <div class="apex-sidebar__footer">
      <div class="apex-chat-input-wrap">
        <textarea id="apex-chat-input" rows="2" placeholder="Describe what you need…"></textarea>
        <button type="button" class="apex-send-btn" id="apex-send-btn" title="Generate">
          <i data-lucide="send" style="width:16px;height:16px;"></i>
        </button>
      </div>
      <div class="apex-action-bar">
        <button type="button" class="apex-action-pill apex-action-pill--primary" data-action="autoFill" title="Auto-fill all fields"><i data-lucide="wand-2" style="width:13px;height:13px;"></i> Auto-fill All</button>
        <button type="button" class="apex-action-pill" data-action="generate" title="Generate"><i data-lucide="sparkles" style="width:13px;height:13px;"></i> Generate</button>
        <button type="button" class="apex-action-pill" data-action="rewrite" title="Rewrite"><i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Rewrite</button>
        <button type="button" class="apex-action-pill" data-action="summarize" title="Summarize"><i data-lucide="align-left" style="width:13px;height:13px;"></i> Summarize</button>
        <button type="button" class="apex-action-pill" data-action="expand" title="Expand"><i data-lucide="expand" style="width:13px;height:13px;"></i> Expand</button>
        <button type="button" class="apex-action-pill" data-action="translate" title="Translate"><i data-lucide="languages" style="width:13px;height:13px;"></i> Translate</button>
        <button type="button" class="apex-action-pill" data-action="grammar" title="Grammar"><i data-lucide="spell-check" style="width:13px;height:13px;"></i> Grammar</button>
        <button type="button" class="apex-action-pill" data-action="seo" title="SEO"><i data-lucide="search" style="width:13px;height:13px;"></i> SEO</button>
        <button type="button" class="apex-action-pill" data-action="tags" title="Tags"><i data-lucide="tags" style="width:13px;height:13px;"></i> Tags</button>
      </div>
    </div>
  `;
  document.body.appendChild(sidebar);

  // Bind settings
  document.getElementById('apex-tone').onchange = e => { state.tone = e.target.value; };
  document.getElementById('apex-language').onchange = e => { state.language = e.target.value; };
  document.getElementById('apex-target-field').onchange = e => { state.targetField = e.target.value; };

  // Bind send button + Enter key
  document.getElementById('apex-send-btn').onclick = () => actions.generate();
  document.getElementById('apex-chat-input').onkeydown = e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); actions.generate(); }
  };

  // Bind action pills
  sidebar.querySelectorAll('.apex-action-pill').forEach(btn => {
    btn.onclick = () => { if (actions[btn.dataset.action]) actions[btn.dataset.action](); };
  });

  // Populate custom fields (Filtered by AI config if available)
  const form = document.getElementById('content-form');
  const targetSel = document.getElementById('apex-target-field');
  if (form && targetSel) {
    targetSel.innerHTML = ''; // Clear default options to rebuild based on config

    // Default fields if config isn't fully loaded
    const defaultFields = [
      { name: 'body', label: 'Body' },
      { name: 'title', label: 'Title' },
      { name: 'summary', label: 'Summary' }
    ];

    const fieldsToAdd = [];
    const skip = new Set(['_csrf','content_type','body_format']);

    // Check DOM fields
    form.querySelectorAll('.form-group [name]').forEach(f => {
      if (!skip.has(f.name) && f.type !== 'hidden') {
        const label = f.closest('.form-group')?.querySelector('label')?.textContent?.trim() || f.name;
        
        // If we have fieldConfig, only include if enabled
        if (Object.keys(state.fieldConfig).length > 0) {
          if (state.fieldConfig[f.name] && state.fieldConfig[f.name].enabled) {
             fieldsToAdd.push({ name: f.name, label });
          }
        } else {
          fieldsToAdd.push({ name: f.name, label });
        }
        skip.add(f.name);
      }
    });

    // Make sure default fields are included if no DOM fields found yet (or before config load)
    if (fieldsToAdd.length === 0 && Object.keys(state.fieldConfig).length === 0) {
       defaultFields.forEach(f => fieldsToAdd.push(f));
    }

    // Add options to select
    fieldsToAdd.forEach(f => {
       const opt = document.createElement('option');
       opt.value = f.name; 
       opt.textContent = `⎯ ${f.label}`;
       if (f.name === 'body') opt.selected = true;
       targetSel.appendChild(opt);
    });
  }

  // Header trigger button
  const header = document.querySelector('.admin-page-header');
  if (header) {
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'apex-header-trigger';
    btn.innerHTML = '<i data-lucide="sparkles" style="width:14px;height:14px;"></i> AI Assistant';
    btn.onclick = toggle;
    header.appendChild(btn);
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ─── Field buttons (inline) ─────────────────────────────────────────────────
async function injectFieldButtons() {
  const ctx = getFormContext();
  if (!ctx.contentType) return;
  try {
    const res = await http.get(`/api/cms/apex/field-config/${ctx.contentType}`);
    if (!res.data?.enabled) return;
    state.fieldConfig = res.data.fields || {};
    
    // Update the dropdown based on loaded config
    updateTargetFieldDropdown();

    const form = document.getElementById('content-form');
    if (!form) return;
    for (const [fieldName, fieldConf] of Object.entries(state.fieldConfig)) {
      if (!fieldConf.enabled) continue;
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (!field) continue;
      const wrapper = field.closest('.form-group') || field.parentElement;
      if (!wrapper || wrapper.querySelector('.field-ai-actions')) continue;
      const label = wrapper.querySelector('label');
      const acts = document.createElement('span');
      acts.className = 'field-ai-actions';
      acts.style.cssText = 'display:inline-flex;gap:0.25rem;margin-left:0.5rem;vertical-align:middle;';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'field-ai-btn-mini';
      btn.innerHTML = '<i data-lucide="sparkles" style="width:11px;height:11px;"></i>';
      btn.title = `AI: ${fieldName}`;
      btn.onclick = () => {
        state.targetField = fieldName;
        const sel = document.getElementById('apex-target-field');
        if (sel) { sel.value = fieldName; }
        if (!state.open) toggle();
      };
      acts.appendChild(btn);
      if (label) label.appendChild(acts);
      else wrapper.insertBefore(acts, field);
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
  } catch (err) { console.warn('AI field config:', err); }
}

function updateTargetFieldDropdown() {
  const form = document.getElementById('content-form');
  const targetSel = document.getElementById('apex-target-field');
  if (!form || !targetSel) return;

  targetSel.innerHTML = '';
  const fieldsToAdd = [];
  const skip = new Set(['_csrf','content_type','body_format']);
  const seen = new Set();

  // Scan ALL named elements in the form (not just .form-group ones)
  form.querySelectorAll('[name]').forEach(f => {
    if (skip.has(f.name) || seen.has(f.name)) return;
    if (f.type === 'hidden') return;
    seen.add(f.name);

    const label = f.closest('.form-group')?.querySelector('label')?.textContent?.trim()
               || f.closest('.card')?.querySelector('.card__title')?.textContent?.trim()
               || f.name.charAt(0).toUpperCase() + f.name.slice(1);

    if (Object.keys(state.fieldConfig).length > 0) {
      if (state.fieldConfig[f.name] && state.fieldConfig[f.name].enabled) {
         fieldsToAdd.push({ name: f.name, label });
      }
    } else {
      fieldsToAdd.push({ name: f.name, label });
    }
  });

  fieldsToAdd.forEach(f => {
     const opt = document.createElement('option');
     opt.value = f.name; 
     opt.textContent = `⎯ ${f.label}`;
     if (f.name === 'body') opt.selected = true;
     targetSel.appendChild(opt);
  });
}

// ─── Init ───────────────────────────────────────────────────────────────────
async function init() {
  const isForm = document.getElementById('content-form');
  if (!isForm) return;
  const ready = await checkStatus();
  if (!ready) return;
  createSidebar();
  injectFieldButtons();
  const provEl = document.getElementById('apex-provider-name');
  if (provEl) {
    const res = await http.get('/api/cms/apex/status');
    provEl.textContent = res.data?.provider || '';
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

window.ApexAssistant = { state, actions, toggle, applyContent, checkStatus, init };
export { state, actions, toggle, init };
