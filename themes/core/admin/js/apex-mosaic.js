/**
 * MonkeysCMS — Apex AI for Mosaic Editor
 *
 * Adds AI generation capabilities to the Mosaic visual page builder.
 * Uses MonkeysJS http client for API calls.
 */
import { http } from 'monkeysjs';

// ─── AI Generate Section ────────────────────────────────────────────────────
async function generateSection(prompt, layout = 'full') {
  try {
    const res = await http.post('/api/cms/apex/mosaic/block', {
      prompt,
      block_type: 'text',
      context: `Mosaic section, layout: ${layout}`,
    });
    return res.data;
  } catch (err) {
    console.error('[Apex Mosaic] generateSection error:', err);
    return null;
  }
}

// ─── AI Generate Full Layout ────────────────────────────────────────────────
async function generateLayout(description, contentType = 'page', sectionCount = 4) {
  try {
    const res = await http.post('/api/cms/apex/mosaic/generate', {
      description,
      content_type: contentType,
      section_count: sectionCount,
    });
    return res.data?.layout || null;
  } catch (err) {
    console.error('[Apex Mosaic] generateLayout error:', err);
    return null;
  }
}

// ─── AI Improve Block Content ───────────────────────────────────────────────
async function improveBlock(content, blockType = 'text', instruction = 'Improve clarity and engagement') {
  try {
    const res = await http.post('/api/cms/apex/mosaic/block', {
      prompt: instruction,
      block_type: blockType,
      context: `Improve existing content: ${content}`,
    });
    return res.data?.content || null;
  } catch (err) {
    console.error('[Apex Mosaic] improveBlock error:', err);
    return null;
  }
}

// ─── AI Generate Block Content ──────────────────────────────────────────────
async function generateBlockContent(prompt, blockType = 'text') {
  try {
    const res = await http.post('/api/cms/apex/mosaic/block', {
      prompt,
      block_type: blockType,
    });
    return res.data?.content || null;
  } catch (err) {
    console.error('[Apex Mosaic] generateBlockContent error:', err);
    return null;
  }
}

// ─── Check if AI is available ───────────────────────────────────────────────
async function isAvailable() {
  try {
    const res = await http.get('/api/cms/apex/status');
    return res.data?.configured && res.data?.features?.mosaic_ai;
  } catch {
    return false;
  }
}

// ─── Inject AI Buttons into Mosaic Toolbar ──────────────────────────────────
async function injectToolbar() {
  const available = await isAvailable();
  if (!available) return;

  // Watch for section toolbars being added
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node.nodeType !== 1) continue;

        // Add AI button to section toolbars
        const sectionToolbar = node.querySelector?.('.mosaic-section__toolbar')
          || (node.classList?.contains('mosaic-section__toolbar') ? node : null);

        if (sectionToolbar && !sectionToolbar.querySelector('.apex-mosaic-btn')) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn btn--ghost btn--xs apex-mosaic-btn';
          btn.title = 'AI Generate';
          btn.innerHTML = '<i data-lucide="sparkles" class="w-3 h-3"></i>';
          btn.onclick = async () => {
            const prompt = window.prompt('What should this section contain?');
            if (!prompt) return;

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-3 h-3 spin"></i>';

            const content = await generateSection(prompt);
            if (content?.content) {
              // Find the block content area in this section
              const contentArea = sectionToolbar.closest('.mosaic-section')
                ?.querySelector('.mosaic-block__content, .ProseMirror, textarea');
              if (contentArea) {
                if (contentArea.classList.contains('ProseMirror')) {
                  contentArea.innerHTML = content.content;
                } else {
                  contentArea.value = content.content;
                }
                contentArea.dispatchEvent(new Event('input', { bubbles: true }));
              }
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="sparkles" class="w-3 h-3"></i>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
          };

          sectionToolbar.appendChild(btn);
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      }
    }
  });

  observer.observe(document.body, { childList: true, subtree: true });
}

// ─── Auto-Init ──────────────────────────────────────────────────────────────
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', injectToolbar);
} else {
  injectToolbar();
}

// ─── Public API ─────────────────────────────────────────────────────────────
window.ApexMosaic = {
  generateSection,
  generateLayout,
  improveBlock,
  generateBlockContent,
  isAvailable,
};

export {
  generateSection,
  generateLayout,
  improveBlock,
  generateBlockContent,
  isAvailable,
};
