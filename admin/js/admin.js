document.addEventListener('DOMContentLoaded', function () {
    const config = window.anyphefoAdminConfig || {};
    const usageMap = config.usageMap || {};
    const strings = Object.assign(
        {
            duplicateTemplateName: 'Template name already exists.',
            addTemplate: 'Add Template',
            createTemplate: 'Create Template',
            editTemplate: 'Edit Template',
            saveChanges: 'Save Changes',
            defaultStatusLabel: 'Publish',
            noContentInFilter: 'No content in this filter.',
            edit: 'Edit',
            all: 'All',
            postLabel: 'Post'
        },
        config.strings || {}
    );

    const modal = document.querySelector('.tm-modal');
    const usageModal = document.querySelector('.tm-usage-modal');
    const templateForm = document.getElementById('tm-template-form');
    const hiddenInput = document.getElementById('tm_storage_type_input');
    const closeButtons = document.querySelectorAll('[data-tm-close-modal]');
    const openButtons = document.querySelectorAll('[data-tm-open-modal]');
    const usageOpenButtons = document.querySelectorAll('[data-tm-usage-open="1"]');
    const usageCloseButtons = document.querySelectorAll('[data-tm-usage-close]');
    const usageList = document.getElementById('tm-usage-list');
    const usageEmpty = document.getElementById('tm-usage-empty');
    const usageFilters = document.getElementById('tm-usage-filters');
    const usageSubtitle = document.getElementById('tm-usage-subtitle');
    const editingSlugInput = document.getElementById('tm_editing_slug');
    const templateNameInput = document.getElementById('tm_template_name');
    const submitLabel = document.getElementById('tm_submit_label');
    const modalTitle = document.getElementById('tm-modal-title');
    const templateNameError = document.getElementById('tm_template_name_error');
    const deleteButtons = document.querySelectorAll('[data-confirm-message]');
    let existingTemplateSlugs = [];

    try {
        existingTemplateSlugs = JSON.parse(templateForm ? (templateForm.dataset.existingSlugs || '[]') : '[]');
    } catch (error) {
        existingTemplateSlugs = [];
    }

    const usageState = {
        items: [],
        filter: 'all'
    };

    function slugifyTemplateName(name) {
        const normalized = name
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return normalized ? ('template-cu-' + normalized + '.php') : 'template-cu-custom-template.php';
    }

    function validateTemplateNameDuplicate() {
        if (!templateNameInput || !templateNameError) {
            return true;
        }

        const rawName = templateNameInput.value.trim();
        if (rawName === '') {
            templateNameError.textContent = '';
            templateNameInput.classList.remove('tm-input-invalid');
            return true;
        }

        const generatedSlug = slugifyTemplateName(rawName);
        const editingSlug = editingSlugInput ? editingSlugInput.value : '';
        const isDuplicate = existingTemplateSlugs.includes(generatedSlug) && generatedSlug !== editingSlug;

        if (isDuplicate) {
            templateNameError.textContent = strings.duplicateTemplateName;
            templateNameInput.classList.add('tm-input-invalid');
            return false;
        }

        templateNameError.textContent = '';
        templateNameInput.classList.remove('tm-input-invalid');
        return true;
    }

    function getComboboxState(name) {
        return {
            box: document.querySelector('[data-tm-combobox="' + name + '"]'),
            hidden: document.getElementById('tm_' + name + '_id'),
            input: document.getElementById('tm_' + name + '_display'),
            menu: document.getElementById('tm_' + name + '_menu')
        };
    }

    function setComboboxValue(name, idValue) {
        const state = getComboboxState(name);
        if (!state.hidden || !state.input || !state.menu) {
            return;
        }

        const targetId = String(idValue);
        const options = state.menu.querySelectorAll('.tm-combobox-option');
        let selectedTitle = '';

        options.forEach(function (option) {
            const isMatch = option.getAttribute('data-id') === targetId;
            option.classList.toggle('is-selected', isMatch);
            if (isMatch) {
                selectedTitle = option.getAttribute('data-title') || option.textContent.trim();
            }
        });

        if (selectedTitle === '' && options.length > 0) {
            selectedTitle = options[0].getAttribute('data-title') || options[0].textContent.trim();
            state.hidden.value = options[0].getAttribute('data-id') || '0';
        } else {
            state.hidden.value = targetId;
        }

        state.input.value = state.hidden.value === '0' ? '' : selectedTitle;
    }

    function initCombobox(name) {
        const state = getComboboxState(name);
        if (!state.box || !state.hidden || !state.input || !state.menu) {
            return;
        }

        const options = state.menu.querySelectorAll('.tm-combobox-option');

        function openMenu() {
            state.box.classList.add('is-open');
        }

        function closeMenu() {
            state.box.classList.remove('is-open');
        }

        function filterOptions() {
            const query = state.input.value.toLowerCase().trim();
            options.forEach(function (option) {
                const title = (option.getAttribute('data-title') || '').toLowerCase();
                option.hidden = query !== '' && !title.includes(query);
            });
        }

        state.input.addEventListener('focus', function () {
            filterOptions();
            openMenu();
        });

        state.input.addEventListener('input', function () {
            filterOptions();
            openMenu();
        });

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                setComboboxValue(name, option.getAttribute('data-id') || '0');
                closeMenu();
            });
        });

        document.addEventListener('click', function (event) {
            if (!state.box.contains(event.target)) {
                closeMenu();
            }
        });

        setComboboxValue(name, state.hidden.value || '0');
    }

    function resetEditMode() {
        if (!editingSlugInput || !modalTitle || !submitLabel || !templateNameInput) {
            return;
        }

        editingSlugInput.value = '';
        modalTitle.textContent = strings.addTemplate;
        submitLabel.textContent = strings.createTemplate;
        templateNameInput.value = '';
        setComboboxValue('header', '0');
        setComboboxValue('footer', '0');

        if (hiddenInput) {
            hiddenInput.value = 'database';
        }

        validateTemplateNameDuplicate();
    }

    function setEditModeFromButton(button) {
        if (!editingSlugInput || !modalTitle || !submitLabel || !templateNameInput) {
            return;
        }

        const isEditing = button.getAttribute('data-editing') === '1';
        if (!isEditing) {
            resetEditMode();
            return;
        }

        editingSlugInput.value = button.getAttribute('data-template-slug') || '';
        templateNameInput.value = button.getAttribute('data-template-name') || '';
        setComboboxValue('header', button.getAttribute('data-header-id') || '0');
        setComboboxValue('footer', button.getAttribute('data-footer-id') || '0');

        if (hiddenInput) {
            hiddenInput.value = 'database';
        }

        modalTitle.textContent = strings.editTemplate;
        submitLabel.textContent = strings.saveChanges;
        validateTemplateNameDuplicate();
    }

    function openModal() {
        if (!modal) {
            return;
        }

        modal.setAttribute('data-state', 'open');
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.remove('is-closing');
        modal.classList.add('is-entering');
        document.body.classList.add('tm-modal-open');

        window.setTimeout(function () {
            modal.classList.remove('is-entering');
        }, 180);
    }

    function closeModal() {
        if (!modal || modal.getAttribute('data-state') !== 'open') {
            return;
        }

        modal.classList.remove('is-entering');
        modal.classList.add('is-closing');
        modal.setAttribute('aria-hidden', 'true');

        window.setTimeout(function () {
            const url = new URL(window.location.href);

            modal.setAttribute('data-state', 'closed');
            modal.classList.remove('is-closing');
            document.body.classList.remove('tm-modal-open');
            url.searchParams.delete('action');
            url.searchParams.delete('template');
            window.history.replaceState({}, '', url);
        }, 140);
    }

    function toStatusLabel(status) {
        if (!status) {
            return strings.defaultStatusLabel;
        }

        const normalized = String(status).replace(/-/g, ' ');
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    function renderUsageList() {
        if (!usageList || !usageEmpty) {
            return;
        }

        const items = usageState.items.filter(function (item) {
            return usageState.filter === 'all' || item.post_type === usageState.filter;
        });

        usageList.innerHTML = '';

        if (items.length === 0) {
            usageEmpty.style.display = 'block';
            usageEmpty.textContent = strings.noContentInFilter;
            return;
        }

        usageEmpty.style.display = 'none';

        items.forEach(function (item) {
            const li = document.createElement('li');
            const titleWrap = document.createElement('div');
            const title = document.createElement('strong');
            const meta = document.createElement('span');

            li.className = 'tm-usage-item';
            titleWrap.className = 'tm-usage-item-main';
            meta.className = 'tm-usage-item-meta';

            title.textContent = item.title || '';
            meta.textContent = (item.type || strings.postLabel) + ' - ' + toStatusLabel(item.status);

            titleWrap.appendChild(title);
            titleWrap.appendChild(meta);
            li.appendChild(titleWrap);

            if (item.edit_link) {
                const action = document.createElement('a');

                action.href = item.edit_link;
                action.className = 'tm-usage-edit';
                action.textContent = strings.edit;
                li.appendChild(action);
            }

            usageList.appendChild(li);
        });
    }

    function renderUsageFilters() {
        if (!usageFilters) {
            return;
        }

        usageFilters.innerHTML = '';

        const allButton = document.createElement('button');
        allButton.type = 'button';
        allButton.className = 'tm-usage-filter' + (usageState.filter === 'all' ? ' is-active' : '');
        allButton.textContent = strings.all;
        allButton.addEventListener('click', function () {
            usageState.filter = 'all';
            renderUsageFilters();
            renderUsageList();
        });
        usageFilters.appendChild(allButton);

        const types = {};
        usageState.items.forEach(function (item) {
            if (!types[item.post_type]) {
                types[item.post_type] = item.type || item.post_type;
            }
        });

        Object.keys(types).forEach(function (postTypeKey) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tm-usage-filter' + (usageState.filter === postTypeKey ? ' is-active' : '');
            button.textContent = types[postTypeKey];
            button.addEventListener('click', function () {
                usageState.filter = postTypeKey;
                renderUsageFilters();
                renderUsageList();
            });
            usageFilters.appendChild(button);
        });
    }

    function openUsageModal(templateSlug, templateName) {
        if (!usageModal || !usageList || !usageEmpty || !usageSubtitle) {
            return;
        }

        usageState.items = Array.isArray(usageMap[templateSlug]) ? usageMap[templateSlug] : [];
        usageState.filter = 'all';
        usageSubtitle.textContent = templateName;
        renderUsageFilters();
        renderUsageList();

        usageModal.setAttribute('data-state', 'open');
        usageModal.setAttribute('aria-hidden', 'false');
    }

    function closeUsageModal() {
        if (!usageModal) {
            return;
        }

        usageModal.setAttribute('data-state', 'closed');
        usageModal.setAttribute('aria-hidden', 'true');
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.getAttribute('data-editing') === '1') {
                setEditModeFromButton(button);
            } else {
                resetEditMode();
            }

            openModal();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    usageOpenButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openUsageModal(
                button.getAttribute('data-template-slug') || '',
                button.getAttribute('data-template-name') || ''
            );
        });
    });

    usageCloseButtons.forEach(function (button) {
        button.addEventListener('click', closeUsageModal);
    });

    deleteButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message = button.getAttribute('data-confirm-message') || '';

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (usageModal && usageModal.getAttribute('data-state') === 'open') {
                closeUsageModal();
            } else {
                closeModal();
            }
        }
    });

    initCombobox('header');
    initCombobox('footer');

    if (templateNameInput) {
        templateNameInput.addEventListener('input', validateTemplateNameDuplicate);
        templateNameInput.addEventListener('blur', validateTemplateNameDuplicate);
        validateTemplateNameDuplicate();
    }

    if (templateForm) {
        templateForm.addEventListener('submit', function (event) {
            if (!validateTemplateNameDuplicate()) {
                event.preventDefault();
            }
        });
    }

    if (hiddenInput) {
        hiddenInput.value = 'database';
    }

    if (modal && modal.getAttribute('data-state') === 'open') {
        document.body.classList.add('tm-modal-open');
    }
});
