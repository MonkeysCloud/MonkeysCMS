/**
 * term-tree-dnd.js — Nestable drag-and-drop for taxonomy terms.
 *
 * Drag vertically to reorder, drag horizontally to nest/un-nest.
 * Dragging a parent moves all its children along with it.
 * Auto-saves and reloads on completion.
 */
const TermTreeDnD = (() => {
  let vocabId = 0;
  let tbody = null;
  let dragGroup = [];     // rows being dragged (parent + descendants)
  let placeholder = null;
  let baseDepth = 0;
  const INDENT_PX = 28;
  const NEST_THRESHOLD = 40;

  function init(opts) {
    vocabId = opts.vocabId;
    tbody = document.querySelector('#terms-table tbody');
    if (!tbody || tbody.querySelectorAll('.term-row').length === 0) return;

    tbody.querySelectorAll('.term-row').forEach(row => {
      const handle = row.querySelector('.term-drag');
      if (handle) {
        handle.addEventListener('mousedown', startDrag);
        handle.addEventListener('touchstart', startDrag, { passive: false });
      }
    });
  }

  function getDepth(row) {
    return parseInt(row.dataset.depth || '0');
  }

  /** Collect a row + all its descendants (deeper rows immediately following it). */
  function collectGroup(row) {
    const depth = getDepth(row);
    const group = [row];
    let next = row.nextElementSibling;
    while (next && next.classList.contains('term-row') && getDepth(next) > depth) {
      group.push(next);
      next = next.nextElementSibling;
    }
    return group;
  }

  function startDrag(e) {
    e.preventDefault();
    const row = e.target.closest('.term-row');
    if (!row) return;

    dragGroup = collectGroup(row);
    baseDepth = getDepth(row);

    // Hide dragged rows
    dragGroup.forEach(r => {
      r.classList.add('term-row--dragging');
    });

    // Placeholder
    placeholder = document.createElement('tr');
    placeholder.className = 'term-row--placeholder';
    placeholder.innerHTML = '<td colspan="5"><div class="placeholder-bar"></div></td>';
    tbody.insertBefore(placeholder, row);

    const startX = (e.clientX ?? e.touches[0].clientX);

    const onMove = (ev) => {
      ev.preventDefault();
      const cx = (ev.clientX ?? ev.touches?.[0]?.clientX ?? 0);
      const cy = (ev.clientY ?? ev.touches?.[0]?.clientY ?? 0);
      const dx = cx - startX;

      // Find where to place the placeholder among visible rows
      const visibles = [...tbody.querySelectorAll('.term-row:not(.term-row--dragging)')];
      let before = null;
      for (const r of visibles) {
        const rect = r.getBoundingClientRect();
        if (cy < rect.top + rect.height / 2) { before = r; break; }
      }

      if (before) {
        tbody.insertBefore(placeholder, before);
      } else if (visibles.length > 0) {
        // After the last visible row
        const last = visibles[visibles.length - 1];
        tbody.insertBefore(placeholder, last.nextSibling);
      }

      // Depth from horizontal drag
      let newDepth = baseDepth + Math.round(dx / NEST_THRESHOLD);
      // Max depth = prev visible row's depth + 1
      const prev = prevVisible(placeholder);
      const maxD = prev ? getDepth(prev) + 1 : 0;
      newDepth = Math.max(0, Math.min(newDepth, maxD));

      placeholder.dataset.depth = newDepth;
      placeholder.querySelector('.placeholder-bar').style.marginLeft = (newDepth * INDENT_PX) + 'px';
    };

    const onEnd = () => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onEnd);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('touchend', onEnd);

      if (!placeholder) return;

      const newDepth = parseInt(placeholder.dataset.depth || '0');
      const depthDelta = newDepth - baseDepth;

      // Re-insert all rows at the placeholder position
      let insertAfter = placeholder;
      dragGroup.forEach(r => {
        r.classList.remove('term-row--dragging');
        const oldD = getDepth(r);
        const updatedD = Math.max(0, oldD + depthDelta);
        r.dataset.depth = updatedD;
        r.classList.toggle('term-row--child', updatedD > 0);
        
        // Update visual padding inline
        const wrap = r.querySelector('.term-name-wrap');
        if (wrap) {
          wrap.style.paddingLeft = (updatedD * INDENT_PX) + 'px';
          
          // Update arrow icon
          const existingIcon = wrap.querySelector('.term-indent-icon');
          if (updatedD > 0 && !existingIcon) {
            const icon = document.createElement('i');
            icon.setAttribute('data-lucide', 'corner-down-right');
            icon.className = 'w-3 h-3 text-muted term-indent-icon';
            wrap.insertBefore(icon, wrap.firstChild);
            if (window.lucide) lucide.createIcons({ nameAttr: 'data-lucide' });
          } else if (updatedD === 0 && existingIcon) {
            existingIcon.remove();
          }
        }
        
        // Insert after the previous element
        insertAfter.after(r);
        insertAfter = r;
      });

      placeholder.remove();
      placeholder = null;
      dragGroup = [];

      // Save to server and update local weights
      saveTree();
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('touchend', onEnd);
  }

  function prevVisible(node) {
    let el = node.previousElementSibling;
    while (el) {
      if (el.classList.contains('term-row') && !el.classList.contains('term-row--dragging')) return el;
      el = el.previousElementSibling;
    }
    return null;
  }

  function saveTree() {
    // Build tree from current DOM order
    const rows = [...tbody.querySelectorAll('.term-row')];
    const items = [];
    const stack = []; // {id, depth}

    rows.forEach((row, i) => {
      // Update visual weight on the DOM immediately
      const weightBadge = row.querySelector('.term-weight');
      if (weightBadge) weightBadge.textContent = i;
      
      const id = parseInt(row.dataset.termId);
      const depth = parseInt(row.dataset.depth || '0');

      // Pop stack to find parent
      while (stack.length > 0 && stack[stack.length - 1].depth >= depth) stack.pop();
      const parentId = stack.length > 0 ? stack[stack.length - 1].id : null;

      items.push({ id, weight: i, parent_id: parentId });
      stack.push({ id, depth });
    });

    // Show saving indicator
    showToast('Saving…', 'saving');

    CMS.fetch(`/admin/taxonomy/${vocabId}/terms/reorder`, {
      method: 'POST',
      body: JSON.stringify({ items }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        showToast('✓ Saved', 'saved');
      } else {
        showToast('✗ Error saving', 'error');
      }
    })
    .catch(() => showToast('✗ Error saving', 'error'));
  }

  function showToast(text, type) {
    let el = document.getElementById('dnd-status');
    if (!el) {
      el = document.createElement('div');
      el.id = 'dnd-status';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.className = 'dnd-status dnd-status--' + type;
  }

  return { init };
})();
