function qs(sel, root = document) {
    return root.querySelector(sel);
}

function qsa(sel, root = document) {
    return [...root.querySelectorAll(sel)];
}

function initTables() {
    document.addEventListener('click', (event) => {
        const row = event.target.closest('.js-data-table tbody tr[data-href]');
        if (!row || event.target.closest('input, button, a, label')) {
            return;
        }
        if (row.dataset.href) {
            window.location.href = row.dataset.href;
        }
    });

    const boxes = () => qsa('.js-row-check');

    const sync = () => {
        const selected = boxes().filter((box) => box.checked);
        qsa('.table-toolbar').forEach((toolbar) => {
            toolbar.hidden = selected.length === 0;
            toolbar.classList.toggle('is-visible', selected.length > 0);
        });
        qsa('.js-selected-count').forEach((count) => {
            count.textContent = String(selected.length);
        });
        qsa('.js-check-all').forEach((master) => {
            const enabled = boxes().filter((box) => !box.disabled);
            master.checked = enabled.length > 0 && enabled.every((box) => box.checked);
            master.indeterminate = selected.length > 0 && !master.checked;
        });
    };

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.classList.contains('js-check-all')) {
            boxes().forEach((box) => {
                if (!box.disabled) {
                    box.checked = target.checked;
                }
            });
            sync();
            return;
        }
        if (target.classList.contains('js-row-check')) {
            sync();
        }
    });

    qsa('.js-bulk-form').forEach((bulkForm) => {
        bulkForm.addEventListener('submit', () => {
            qsa('input[name="ids[]"]', bulkForm).forEach((n) => n.remove());
            boxes().filter((box) => box.checked).forEach((box) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = box.value;
                bulkForm.appendChild(hidden);
            });
        });
    });
}

function fieldValue(field) {
    const type = field.dataset.type;
    if (type === 'boolean') {
        return qs('.js-attr-input', field)?.checked ? '1' : '0';
    }
    if (type === 'period') {
        const from = qs('[data-bound="from"]', field)?.value || '';
        const to = qs('[data-bound="to"]', field)?.value || '';
        return JSON.stringify({ from, to });
    }
    return qs('.js-attr-input', field)?.value ?? '';
}

function collectValues(root) {
    const values = {};
    qsa('.js-attr-field', root).forEach((field) => {
        if (qs('.js-attr-field', field)) {
            return;
        }
        values[field.dataset.attributeId] = fieldValue(field);
        field.classList.toggle('empty-value', !String(values[field.dataset.attributeId] || '').trim() || values[field.dataset.attributeId] === '{"from":"","to":""}');
    });
    return values;
}

function initAutosave(root) {
    if (!root || root.dataset.editable !== '1') {
        return;
    }
    const status = qs('#autosave-status');
    let dirty = false;
    let version = Number(root.dataset.version || 1);
    const removed = new Set();

    root.addEventListener('input', () => {
        dirty = true;
        if (status) {
            status.textContent = 'Unsaved changes';
        }
    });
    root.addEventListener('change', () => {
        dirty = true;
    });

    const save = async () => {
        if (!dirty) {
            return;
        }
        dirty = false;
        const payload = { version, values: collectValues(root), remove: [...removed] };
        try {
            const response = await fetch(root.dataset.autosaveUrl || root.dataset.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (response.status === 409) {
                const banner = qs('#lock-conflict');
                const msg = qs('.js-lock-msg', banner);
                if (msg) {
                    msg.textContent = data.error || msg.textContent;
                }
                if (banner) {
                    banner.hidden = false;
                    banner.style.display = 'flex';
                }
                return;
            }
            if (!response.ok) {
                if (status) {
                    status.textContent = data.error || 'Save failed';
                }
                dirty = true;
                return;
            }
            version = data.version;
            root.dataset.version = String(version);
            removed.clear();
            if (status) {
                status.textContent = `Saved ${data.savedAt || ''}`.trim();
            }
        } catch {
            dirty = true;
            if (status) {
                status.textContent = 'Offline, will retry';
            }
        }
    };

    setInterval(save, 8000);
    window.addEventListener('beforeunload', save);
    root._markRemoved = (id) => removed.add(String(id));
}

function renderAttrResult(item, onAdd) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'list-group-item list-group-item-action';
    button.textContent = `${item.name} · ${item.categoryLabel} · ${item.typeLabel}`;
    button.addEventListener('click', () => onAdd(item));
    return button;
}

async function lookupAttributes(prefix, category) {
    const url = new URL(window.CVHUB.lookupUrl, window.location.origin);
    if (prefix) {
        url.searchParams.set('q', prefix);
    }
    if (category) {
        url.searchParams.set('category', category);
    }
    const response = await fetch(url);
    return response.ok ? response.json() : [];
}

function initAttributePicker({ prefix, category, results, onAdd }) {
    if (!prefix || !results) {
        return;
    }
    const run = async () => {
        const items = await lookupAttributes(prefix.value, category?.value || '');
        results.replaceChildren(...items.slice(0, 12).map((item) => renderAttrResult(item, onAdd)));
    };
    prefix.addEventListener('input', () => {
        if (prefix.value.length >= 1) {
            run();
        }
    });
    category?.addEventListener('change', run);
}

function addTemplateRow(tbody, item) {
    if (qs(`[data-id="${item.id}"]`, tbody)) {
        return;
    }
    const tr = document.createElement('tr');
    tr.dataset.id = item.id;
    tr.innerHTML = `<td>${item.name}</td><td>${item.typeLabel || item.type}</td><td><button type="button" class="btn btn-sm btn-link text-danger">Remove</button></td>`;
    tr.querySelector('button').addEventListener('click', () => tr.remove());
    tbody.appendChild(tr);
}

function initPositionForm() {
    const form = qs('.js-position-form');
    if (!form) {
        return;
    }
    const tbody = qs('#template-table tbody');
    const initialIds = JSON.parse(qs('[name$="[templateJson]"]', form).value || '[]');
    initialIds.forEach((id) => addTemplateRow(tbody, { id, name: `#${id}`, type: '' }));
    lookupAttributes('', '').then((items) => {
        const map = Object.fromEntries(items.map((item) => [item.id, item]));
        qsa('tr[data-id]', tbody).forEach((row) => {
            const item = map[Number(row.dataset.id)];
            if (item) {
                row.children[0].textContent = item.name;
                row.children[1].textContent = item.typeLabel;
            }
        });
    });

    initAttributePicker({
        prefix: qs('#attr-prefix'),
        category: qs('#attr-category'),
        results: qs('#attr-results'),
        onAdd: (item) => addTemplateRow(tbody, item),
    });

    const rulesBox = qs('#rules-box');
    const addRuleBtn = qs('#add-rule');
    const initialRules = JSON.parse(qs('[name$="[rulesJson]"]', form).value || '[]');

    const addRule = (rule = {}) => {
        const wrap = document.createElement('div');
        wrap.className = 'row g-2 mb-2 js-rule';
        wrap.innerHTML = `
            <div class="col-md-4"><input class="form-control js-rule-attr" placeholder="Attribute id" value="${rule.attributeId || ''}"></div>
            <div class="col-md-3"><input class="form-control js-rule-op" placeholder="eq / gt / contains" value="${rule.operator || 'eq'}"></div>
            <div class="col-md-4"><input class="form-control js-rule-value" placeholder="Value" value="${rule.value || ''}"></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100">&times;</button></div>`;
        wrap.querySelector('button').addEventListener('click', () => wrap.remove());
        rulesBox.appendChild(wrap);
    };
    initialRules.forEach(addRule);
    addRuleBtn?.addEventListener('click', () => addRule());

    const tagInput = qs('#project-tags');
    const tagsField = qs('[name$="[tagsJson]"]', form);
    const initialTags = JSON.parse(tagsField.value || '[]');
    if (tagInput && window.TomSelect) {
        const select = new TomSelect(tagInput, {
            persist: false,
            create: true,
            valueField: 'value',
            labelField: 'value',
            searchField: 'value',
            plugins: ['remove_button'],
            load(query, callback) {
                if (!query.length) {
                    return callback();
                }
                fetch(`${window.CVHUB.tagSuggestUrl}?q=${encodeURIComponent(query)}`)
                    .then((r) => r.json())
                    .then(callback)
                    .catch(() => callback());
            },
        });
        initialTags.forEach((tag) => select.addOption({ value: tag }) || select.addItem(tag));
        form.addEventListener('submit', () => {
            tagsField.value = JSON.stringify(select.items);
            qs('[name$="[templateJson]"]', form).value = JSON.stringify(
                qsa('tr[data-id]', tbody).map((row) => Number(row.dataset.id)),
            );
            qs('[name$="[rulesJson]"]', form).value = JSON.stringify(
                qsa('.js-rule', rulesBox).map((row) => ({
                    attributeId: Number(qs('.js-rule-attr', row).value),
                    operator: qs('.js-rule-op', row).value,
                    value: qs('.js-rule-value', row).value,
                })),
            );
        });
    }
}

function initProjectTags() {
    const input = qs('#project-tags');
    const form = qs('.js-project-form');
    if (!input || !form || !window.TomSelect) {
        return;
    }
    const hidden = qs('[name$="[tagsJson]"]', form);
    const select = new TomSelect(input, {
        persist: false,
        create: true,
        plugins: ['remove_button'],
        valueField: 'value',
        labelField: 'value',
        searchField: 'value',
        load(query, callback) {
            fetch(`${window.CVHUB.tagSuggestUrl}?q=${encodeURIComponent(query)}`)
                .then((r) => r.json())
                .then(callback)
                .catch(() => callback());
        },
    });
    JSON.parse(hidden.value || '[]').forEach((tag) => {
        select.addOption({ value: tag });
        select.addItem(tag);
    });
    form.addEventListener('submit', () => {
        hidden.value = JSON.stringify(select.items);
    });
}

function initMarkdown() {
    if (!window.EasyMDE) {
        return;
    }
    qsa('textarea.js-markdown').forEach((area) => {
        // eslint-disable-next-line no-new
        new EasyMDE({ element: area, spellChecker: false, status: false });
    });
}

function uploadImage(file, hidden, drop) {
    if (!window.CVHUB.cloudName || !window.CVHUB.uploadPreset) {
        const url = window.prompt('Cloudinary is not configured. Paste an image URL:');
        if (url) {
            hidden.value = url;
            drop.innerHTML = `<img src="${url}" class="preview-img mb-2" alt=""><div>Replace</div>`;
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
        return;
    }
    const body = new FormData();
    body.append('file', file);
    body.append('upload_preset', window.CVHUB.uploadPreset);
    fetch(`https://api.cloudinary.com/v1_1/${window.CVHUB.cloudName}/image/upload`, { method: 'POST', body })
        .then((r) => r.json())
        .then((data) => {
            hidden.value = data.secure_url;
            drop.innerHTML = `<img src="${data.secure_url}" class="preview-img mb-2" alt=""><div>Replace</div>`;
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        });
}

function initImageFields() {
    qsa('.js-image-field').forEach((wrap) => {
        const hidden = qs('.js-attr-input', wrap);
        const drop = qs('.image-drop', wrap);
        drop.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.onchange = () => input.files[0] && uploadImage(input.files[0], hidden, drop);
            input.click();
        });
        drop.addEventListener('dragover', (e) => e.preventDefault());
        drop.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (file) {
                uploadImage(file, hidden, drop);
            }
        });
    });
}

function initProfileLibrary() {
    const root = qs('#profile-root');
    if (!root) {
        return;
    }
    initAttributePicker({
        prefix: qs('#info-prefix'),
        category: qs('#info-category'),
        results: qs('#info-results'),
        onAdd: (item) => {
            if (qs(`[data-attribute-id="${item.id}"]`, root)) {
                return;
            }
            const box = document.createElement('div');
            box.className = 'mb-3 js-attr-field empty-value';
            box.dataset.attributeId = item.id;
            box.dataset.type = item.type;
            box.innerHTML = `<div class="d-flex justify-content-between"><label class="form-label">${item.name}</label><button type="button" class="btn btn-link btn-sm text-danger p-0 js-remove-attr">Remove</button></div><input class="form-control js-attr-input">`;
            qs('#info-fields').appendChild(box);
            root.dispatchEvent(new Event('input'));
        },
    });
    qsa('.js-add-attr').forEach((btn) => {
        btn.addEventListener('click', () => {
            qs('#info-prefix').value = btn.dataset.name;
            qs('#info-prefix').dispatchEvent(new Event('input'));
        });
    });
    root.addEventListener('click', (event) => {
        const button = event.target.closest('.js-remove-attr');
        if (!button) {
            return;
        }
        const field = button.closest('.js-attr-field');
        root._markRemoved?.(field.dataset.attributeId);
        field.remove();
        root.dispatchEvent(new Event('input'));
    });
}

function initDiscussion() {
    const feed = qs('#discussion-feed');
    if (!feed) {
        return;
    }
    let after = 0;
    const render = (items) => {
        items.forEach((item) => {
            after = Math.max(after, item.id);
            const article = document.createElement('article');
            const author = feed.dataset.recruiter === '1'
                ? `<a href="${item.profileUrl}">${item.author}</a>`
                : item.author;
            article.innerHTML = `<div class="small text-secondary">${author} · ${item.createdAt}</div><div class="js-md"></div>`;
            article.querySelector('.js-md').innerHTML = window.marked ? window.marked.parse(item.content) : item.content;
            feed.appendChild(article);
        });
    };
    const poll = async () => {
        const url = `${feed.dataset.feedUrl}?after=${after}`;
        const response = await fetch(url);
        if (!response.ok) {
            return;
        }
        render(await response.json());
    };
    poll();
    setInterval(poll, 3000);
}

function initCvTable() {
    const table = qs('.js-cv-table');
    if (!table) {
        return;
    }
    const tbody = qs('tbody', table);
    const input = qs('.js-cv-filter');
    input?.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        qsa('tr', tbody).forEach((row) => {
            if (!row.children.length || row.querySelector('[colspan]')) {
                return;
            }
            row.hidden = Boolean(query) && !row.innerText.toLowerCase().includes(query);
        });
    });
    qsa('th.js-sort', table).forEach((header, index) => {
        header.addEventListener('click', () => {
            const numeric = header.dataset.sortType === 'numeric';
            const direction = header.dataset.dir === 'asc' ? 'desc' : 'asc';
            qsa('th.js-sort', table).forEach((item) => {
                delete item.dataset.dir;
                item.classList.remove('is-asc', 'is-desc');
            });
            header.dataset.dir = direction;
            header.classList.add(direction === 'asc' ? 'is-asc' : 'is-desc');
            const rows = qsa('tr', tbody).filter((row) => row.children.length > 1 && !row.querySelector('[colspan]'));
            const sign = direction === 'asc' ? 1 : -1;
            rows.sort((left, right) => {
                const a = left.children[index]?.dataset.sort ?? '';
                const b = right.children[index]?.dataset.sort ?? '';
                if (numeric) {
                    return (Number(a) - Number(b)) * sign;
                }
                return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }) * sign;
            });
            rows.forEach((row) => tbody.appendChild(row));
        });
    });
}

initTables();
initAutosave(qs('#profile-root'));
initAutosave(qs('#cv-root'));
initPositionForm();
initProjectTags();
initMarkdown();
initImageFields();
initProfileLibrary();
initDiscussion();
initCvTable();
qs('#lock-conflict .js-lock-reload')?.addEventListener('click', () => window.location.reload());

